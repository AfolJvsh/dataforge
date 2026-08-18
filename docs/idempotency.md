# Import idempotency

DataForge separates **chunk delivery idempotency** from **global row deduplication**.

## Chunk redelivery

`(execution_id, chunk_index)` is unique. A completed chunk is terminal. Staging records have stable execution/source-row identity and inserts are conflict-safe, so a queue redelivery cannot create two logical copies of one staged source row.

## Finalization

Workers do not race each other to the destination table. Valid rows are staged first; one finalizer performs a set-based merge after all chunks settle. Therefore worker completion order does not decide `keep_first`, `keep_last/upsert`, or `reject` behavior.

## Resume

Resume reuses the same execution and immutable mapping snapshot. It never creates a new interpretation of already processed chunks.
