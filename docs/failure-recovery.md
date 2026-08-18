# Failure and recovery drills

1. **Duplicate queue delivery:** dispatch the same chunk twice. Its DB claim/idempotent staging keys prevent double output.
2. **Kill a worker mid-chunk:** the chunk is left non-terminal. Resume resets only failed/cancelled/processing chunks; completed chunks remain intact.
3. **Cancel a large import:** workers check cancellation periodically, stop safely, and the execution becomes `cancelled` once no active chunk remains.
4. **Resume:** only unfinished chunks are requeued. Existing staging rows use `insertOrIgnore`, so already-committed rows are safe.
5. **Malformed row:** the row error is persisted with selected source fields; the rest of the chunk continues.
6. **Malformed file/content mismatch:** upload finalization rejects content before analysis.
7. **Object storage interruption:** queue retries occur; PostgreSQL execution/chunk state remains the source of truth.
8. **Concurrent duplicate keys across chunks:** final set-based ranking chooses by source row number, not worker completion order.
