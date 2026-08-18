# DataForge Planner Implementation Status

This file maps the implementation to `docs/PLANNER.md`. The planner remains the product/engineering source of truth; this matrix points reviewers to the executable evidence.

| Milestone | Status | Implementation evidence |
|---|---|---|
| M0 — Foundation | Complete | `compose.yaml`, `.github/workflows/ci.yml`, auth/organization flow, PostgreSQL/Redis/MinIO configuration |
| M1 — Upload + inspect | Complete | `UploadController`, `AnalyzeImport`, `FileTypeInspector`, `SchemaInferer`, `CsvSourceReader`, object-storage checksum/metadata flow |
| M2 — Mapping + preview | Complete | destination schema/field models, `MappingController`, `TransformPipeline`, `RowValidator`, preview operations in `ImportOperationsController` |
| M3 — Background chunk import | Complete | `PlanImportExecution`, independently queued `ProcessImportChunk` jobs, staging records, `ImportFinalizer`, progress metrics |
| M4 — Reliability | Complete | chunk claim/status model, redelivery idempotency, error fingerprints, cancellation/resume APIs, safe error CSV export |
| M5 — Dedupe/performance | Complete | `DedupeKey`, staging-before-final-merge strategy, set-based finalization, execution throughput/peak-memory metrics, benchmark harness |
| M6 — Additional formats | Complete | streaming CSV, NDJSON and XLSX readers with explicit XLSX sheet selection |
| M7 — Portfolio polish | Complete | dataset generator, 10k/100k/1m benchmark tooling, ADRs, failure-recovery/runbook docs, architecture/pipeline docs |

## Critical invariants covered

- HTTP requests never process the complete import workload.
- Valid rows are staged before global dedupe so worker completion order cannot change dedupe semantics.
- Chunk redelivery cannot duplicate staged rows or row-error records.
- CSV/NDJSON readers can use seekable offsets; XLSX remains row-range based after disk-backed materialization.
- Cancellation is cooperative and resumable; completed chunks are not repeated.
- Spreadsheet-formula prefixes are neutralized in exported error CSVs.
- Retention pruning refuses to delete active imports.

## Validation evidence

- Standalone domain suite: `tests/standalone.php`.
- Database-backed feature coverage: `tests/Feature/`.
- Workload tools: `tools/generate_dataset.py`, `tools/benchmark_import.py`.
- CI installs the extensions required by the XLSX implementation and runs Laravel tests against PostgreSQL/Redis services.
