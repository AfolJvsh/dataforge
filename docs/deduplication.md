# Deterministic deduplication

A SHA-256 dedupe key is built from the configured composite destination fields. Valid rows enter `import_staging_records` with their source row number.

Finalization performs PostgreSQL set operations:

- `keep_first`: rank each dedupe key by ascending source row number and insert rank 1.
- `keep_last` / `upsert`: rank descending and upsert the selected row into the tenant destination.
- `reject`: identify duplicate keys both inside the execution and against existing destination rows, persist duplicate row errors, and insert only unique candidates.

This is intentionally not an in-memory “seen set”; memory use stays bounded and results are deterministic across concurrent workers.
