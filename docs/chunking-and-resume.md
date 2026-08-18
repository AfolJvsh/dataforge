# Chunking and resume

Chunk metadata is durable PostgreSQL state and is format-specific.

- CSV and NDJSON use seekable byte boundaries when the storage stream supports seeking. Readers realign to record boundaries and never split a quoted CSV record blindly.
- XLSX is ZIP/XML, so compressed byte offsets are not treated as row boundaries. The source is materialized to disk and worksheet XML is streamed with `XMLReader`; chunks are row ranges within the selected sheet.

Each `ImportChunk` is independently dispatched. A worker claims exactly one chunk in a transaction, increments its durable attempt count, and writes valid rows into execution staging. Duplicate job delivery uses `insertOrIgnore` identities and a completed chunk is never logically processed twice.

Cancellation is cooperative: workers re-check `cancel_requested_at` periodically. Resume resets only failed/cancelled/in-progress chunks to pending and dispatches unfinished work. Completed chunks and their staging rows remain intact.
