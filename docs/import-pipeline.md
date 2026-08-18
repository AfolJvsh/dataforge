# Import pipeline

```mermaid
flowchart LR
  B[Browser / API] --> O[(S3 / MinIO)]
  O --> A[AnalyzeImport]
  A --> M[Persisted schema + mapping]
  M --> E[Immutable execution snapshot]
  E --> P[Format-specific chunk planner]
  P --> Q[(Redis queues)]
  Q --> W1[Chunk worker]
  Q --> W2[Chunk worker]
  Q --> WN[Chunk worker]
  W1 --> S[(Execution staging)]
  W2 --> S
  WN --> S
  S --> F[Set-based finalizer / dedupe]
  F --> R[(Imported records)]
  W1 --> X[(Row errors)]
```

HTTP requests never parse the full source. CSV and NDJSON readers stream and use seekable byte ranges when storage provides them. XLSX is a ZIP/XML format, so the worker materializes the workbook to bounded disk storage and iterates the selected worksheet with `XMLReader`; it is chunked by row range rather than pretending compressed XML has safe byte offsets.

Every execution freezes schema version, mappings, transform stages, validation rules, dedupe policy, source options and chunk size. Editing an import after execution starts cannot change the meaning of work already in the queue.

Workers write valid transformed rows to an execution staging table. Finalization uses PostgreSQL set operations/window functions to apply deterministic `keep_first`, `keep_last/upsert`, or `reject` semantics. Therefore worker completion order cannot change dedupe results.
