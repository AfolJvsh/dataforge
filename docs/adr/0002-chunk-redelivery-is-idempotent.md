# ADR 0002 — Chunk redelivery must be idempotent
**Status:** Accepted

Queue delivery is at-least-once. DataForge therefore treats duplicate chunk execution as normal rather than exceptional. `(execution_id, chunk_index)` uniquely identifies a planned chunk, while `(execution_id, source_row_number)` uniquely identifies output from a source row. Database uniqueness is the final correctness boundary.
