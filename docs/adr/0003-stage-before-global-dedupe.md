# ADR 0003 — Stage before global dedupe

**Status:** Accepted

Per-worker `SELECT then INSERT` duplicate checks are race-prone and make `keep_first/keep_last` depend on worker completion timing. DataForge therefore stages valid transformed rows with their source row numbers and dedupe keys, then performs one deterministic PostgreSQL merge after every chunk is terminal.

This costs temporary storage but gives deterministic semantics under horizontal worker concurrency and makes retry/idempotency reasoning substantially simpler.
