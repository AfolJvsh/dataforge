# DataForge — Data Import & Transformation SaaS

## 1. Project objective

Build a multi-tenant data-ingestion platform for large CSV, XLSX, and JSON/NDJSON files. Users upload data, inspect inferred fields, map source columns, configure transformations/validation/deduplication, and run resumable background imports with progress and error reporting.

The engineering signal is scalable file/data processing—not spreadsheet CRUD.

## 2. Engineering signals

- Large uploads.
- Object storage.
- Streaming/chunk parsing.
- Bounded memory use.
- Background batch processing.
- Bulk database operations.
- Schema mapping.
- Transformation pipelines.
- Validation.
- Deduplication.
- Retries and partial failure.
- Progress aggregation.
- Cancellation and resumability.
- Import idempotency.
- Performance benchmarking.

## 3. Recommended stack

- Laravel.
- React + Inertia.
- PostgreSQL.
- Redis queues.
- S3-compatible object storage.
- Docker.

Do not add FastAPI initially. Add Python later only if a measured workload or Python-native data capability clearly justifies a separate processing service.

## 4. Product flow

```text
Upload file
  ↓
Object Storage
  ↓
Analyze headers/sample
  ↓
Infer source schema
  ↓
Map source → destination fields
  ↓
Configure transformations/validation/dedupe
  ↓
Dry-run preview
  ↓
Create ImportExecution snapshot
  ↓
Plan chunks
  ↓
Queue workers
  ↓
Transform → validate → dedupe → bulk persist
  ↓
Aggregate progress/errors
  ↓
Complete / complete-with-errors / fail
```

## 5. Upload strategy

Preferred:

```text
Browser → presigned upload → object storage
                          ↓
                  finalize-upload API
                          ↓
                   AnalyzeFileJob
```

Record:
- Original filename.
- Storage key.
- Size.
- Checksum.
- Detected/claimed content type.
- Tenant ownership.

Never trust extension alone.

## 6. Domain model

### imports
`id(UUID), organization_id, name, source_type, storage_key, original_filename, file_size, checksum, status, source_schema_json, detected_row_count`

### destination_schemas
`id, organization_id, name, version`

### destination_fields
`id, schema_id, key, type, nullable, constraints_json`

### import_mappings
`import_id, destination_field_id, source_column, transform_pipeline_json`

### import_executions
`id, import_id, mapping_snapshot_json, status, total_rows, processed_rows, successful_rows, invalid_rows, duplicate_rows, failed_rows, started_at, completed_at, cancel_requested_at`

### import_chunks
`id, execution_id, chunk_index, range_metadata_json, status, attempts, processed_rows`

### import_row_errors
`id, execution_id, source_row_number, error_code, field, message, raw_row_json_or_pointer`

### imported_records / target tables
For portfolio MVP, either use a realistic fixed destination schema or a JSONB-backed generic record store plus indexed identity fields.

## 7. Import states

```text
draft
 → uploading
 → analyzing
 → ready
 → queued
 → processing
      ↘ completed
      ↘ completed_with_errors
      ↘ failed
      ↘ cancelled
```

Do not add “pause” unless it has real semantics. Cancellation + resumable incomplete chunks is enough initially.

## 8. File parsing strategy

### CSV
- Stream rows.
- Support common delimiter/encoding policy.
- Validate headers before execution.
- Never load full file into memory.

### XLSX
- Use row/chunk iteration.
- Explicit sheet selection.
- Do not pretend arbitrary byte ranges are meaningful like CSV.

### JSON
For genuinely large input, prioritize NDJSON. If supporting a huge single JSON array, use a streaming parser or enforce explicit limits.

## 9. Schema inference

Use a bounded sample only.

Suggested inferred types:
- String.
- Integer.
- Decimal.
- Boolean.
- Date/datetime.

Email/phone-like formats can be suggestions, not authoritative types.

The user must still control final mapping/validation.

## 10. Transformation engine

Build composable deterministic transforms:
- Trim.
- Lowercase/uppercase.
- Normalize whitespace.
- Concatenate.
- Split.
- Parse date.
- Normalize phone.
- Parse number.
- Default value.
- Enumerated value mapping.
- Regex replace.

Contract:

```text
Transform
- validateConfig()
- apply(value, rowContext)
```

Persist transform definitions as data. Do not permit arbitrary uploaded code.

## 11. Validation engine

Rules:
- Required.
- Type.
- Min/max.
- String length.
- Regex.
- Enum.
- Date range.
- Optional cross-field rule.

Return structured errors by row, field, and rule so the UI can aggregate them.

## 12. Deduplication

Support:
- Duplicate within current file.
- Duplicate against existing destination data.
- Composite keys.
- Keep first.
- Keep last.
- Reject duplicate.
- Upsert/update as later mode.

Avoid one DB query per row. Prefer batching, indexed keys, staging tables, or set-based SQL.

Advanced/high-value implementation: stage normalized rows and use set-based SQL for dedupe + final insert/upsert.

## 13. Chunk-processing model

```text
ImportExecution
  ├── Chunk 0 → queue
  ├── Chunk 1 → queue
  ├── Chunk 2 → queue
  └── ...

Worker:
  read bounded rows
    ↓
  transform
    ↓
  validate
    ↓
  batch dedupe
    ↓
  bulk write
    ↓
  persist row errors
    ↓
  increment durable progress
```

Chunk planning is format-specific and should live behind a reader/chunk-planner abstraction.

## 14. Database-write strategy

Do not perform one insert/query for every row on large imports.

Prefer:
- Bounded in-memory batches.
- Bulk insert into staging/final tables.
- Set-based dedupe.
- Bulk upsert where needed.
- Transaction per bounded batch/chunk.

Benchmark batch sizes and document the choice.

## 15. Progress tracking

Show:
- Total rows/chunks when known.
- Processed.
- Successful.
- Invalid.
- Duplicate.
- Failed.
- Rows/sec.
- Completed chunks/total.

Redis can make live counters cheap, but durable execution counters/status must survive Redis loss/restart.

## 16. Cancellation

- Set `cancel_requested_at`.
- Workers check between batches.
- Queued/running work exits cooperatively at safe boundaries.
- Do not kill a transaction halfway through an unbounded operation.

## 17. Resumability/idempotency

- Snapshot mapping/rules at execution start.
- Completed chunks are not rerun during resume.
- Failed/incomplete chunks can be requeued.
- Unique execution + chunk index.
- Row/staging identity prevents duplicate output if a chunk is redelivered.
- Use database uniqueness, not application `exists()` checks alone.

## 18. Error model

Classify:
- Fatal file corruption.
- Header/schema problem.
- Row validation error.
- Transform error.
- Duplicate.
- Storage/network problem.
- Transient database problem.

Most row-level failures should be recorded while the import continues. Infrastructure failures should retry the chunk. Fatal file errors should stop the execution.

Generate an error export with:
`row_number, field, error_code, reason, selected source values`.

## 19. UI/screens

- Imports list.
- New import/upload.
- File analysis/sample.
- Column mapping.
- Transform configuration.
- Validation rules.
- Deduplication rules.
- Dry-run preview.
- Execution progress.
- Execution summary.
- Row error explorer.
- Error export.
- Import history.

The execution summary should show throughput and error distribution—not only a progress bar.

## 20. Milestones

### M0 — Foundation
- Docker.
- Auth/organizations.
- PostgreSQL/Redis.
- Local object-storage setup.
- CI.

### M1 — Upload + inspect
- CSV upload.
- Object storage.
- Checksum/metadata.
- Header/sample extraction.
- Schema inference.

**Exit:** large CSV can be stored/analyzed without loading entire file into application memory.

### M2 — Mapping + preview
- Destination schema.
- Field mappings.
- Transform engine.
- Validation engine.
- Sample dry run.

### M3 — Background chunk import
- Execution snapshot.
- Chunk planner.
- Queue processing.
- Bulk writes.
- Progress.
- Finalizer.

**Exit:** 100k-row dataset processes without HTTP timeout or unbounded memory.

### M4 — Reliability
- Chunk retries.
- Idempotent chunk redelivery.
- Cancellation.
- Resume incomplete chunks.
- Error persistence/export.

### M5 — Deduplication/performance
- Composite dedupe keys.
- Batch/set-based dedupe.
- Appropriate indexes.
- Batch-size benchmarks.
- Peak-memory/throughput report.

### M6 — Additional formats
- XLSX streaming.
- NDJSON.
- Sheet selection.
- Format-specific validation.

### M7 — Portfolio polish
- Dataset generator.
- 100k and optionally 1m-row benchmark fixtures.
- Architecture diagram.
- Failure-recovery demo.
- ADRs.

## 21. Testing plan

### Unit
- Transforms.
- Validators.
- Type inference.
- Dedupe-key generation.

### Feature
- Upload finalization.
- Tenant isolation.
- Mapping/rule configuration.
- Cancel endpoint.

### Integration
- Malformed CSV.
- Quoted/newline fields.
- Supported-encoding edge cases.
- Duplicate chunk delivery.
- Worker crash + resume.
- Object-storage failure.
- Transient DB failure.

### Performance
Generate datasets with:
- 100k rows.
- 1m rows if practical.
- Narrow/wide rows.
- High duplicate ratio.
- High error ratio.

Report:
- Rows/sec.
- Peak memory.
- p50/p95 chunk duration.
- DB batch latency.

## 22. Observability

Metrics:
- Queue lag.
- Rows/sec.
- Chunk duration.
- Import duration.
- Error rate.
- Duplicate rate.
- Storage failures.
- DB batch latency.
- Worker memory.

Log context:
`organization_id, import_id, execution_id, chunk_id`.

## 23. Security

- Tenant-isolated storage prefixes.
- Presigned upload constraints.
- File size limits.
- Content/MIME validation.
- No arbitrary transform code.
- Sanitize data previews.
- PII-aware logging—do not dump full source rows into generic logs.
- Defend against spreadsheet formula injection in generated CSV error exports.
- Retention/deletion controls.

## 24. Optional FastAPI phase

Add a Python service only if benchmarks/features justify it, for example:
- Python-native enrichment/data library.
- Specialized ML transformation.
- A measured processing bottleneck suited to separate workers.

Laravel remains orchestration/source-of-truth; define a strict versioned processing contract.

## 25. Repository docs

- `docs/import-pipeline.md`
- `docs/chunking-and-resume.md`
- `docs/idempotency.md`
- `docs/deduplication.md`
- `docs/performance.md`
- Dataset generator under `tools/`.

## 26. Portfolio definition of done

A reviewer can upload a 100k+ row file, inspect/map fields, configure transforms/validation/dedupe, run the import asynchronously, watch progress, cancel/resume safely, force a chunk retry without duplicates, inspect failures, download an error report, and reproduce the documented performance benchmark.

## 27. Do not build yet

- Kafka.
- Spark.
- Data lake architecture.
- Arbitrary customer DB connectors.
- Dozens of destinations.
- Full ETL DAG builder.
- Real-time streaming ingestion.
- AI auto-cleaning.

First prove large-file correctness, bounded resource use, and recoverable batch processing.
