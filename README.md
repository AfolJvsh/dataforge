# DataForge

DataForge is a production-minded data import SaaS for **large CSV, NDJSON and XLSX workloads**. Its engineering focus is bounded-memory parsing, asynchronous chunk work, immutable execution configuration, deterministic deduplication, resumability and measurable throughput.

## Implemented system

- Direct uploads plus S3/MinIO presigned-upload preparation/finalization.
- File/content sanity checking and SHA-256 source identity.
- Bounded source analysis, schema inference and XLSX sheet selection.
- Versioned destination schemas and persisted source → destination mappings.
- Data-driven transforms and validation rules with dry-run previews.
- Immutable execution snapshots.
- CSV/NDJSON seekable byte-range chunk planning; XLSX row-range planning.
- Independently queued chunk workers with durable claims, attempts and cancellation checks.
- Execution staging followed by deterministic PostgreSQL set-based `keep_first`, `keep_last/upsert` or `reject` dedupe.
- Row-level error capture, safe CSV export, cancellation and resume of unfinished chunks.
- Durable progress, rows/sec, chunk p50/p95 and peak worker-memory metrics.
- A functional React/Inertia UI for upload → map → preview → execute → inspect/recover.
- Reproducible 10k/100k/1m benchmark tooling for all three formats.

## Architecture

`upload → object storage → analyze → versioned mapping → execution snapshot → chunk plan → Redis workers → staging → deterministic merge → imported records`

Redis delivers work; PostgreSQL owns correctness and progress.

## Run

```bash
cp .env.example .env
docker compose up --build -d
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
```

Open `http://localhost:8000`, create a workspace and upload CSV, NDJSON or XLSX.

## Benchmark

```bash
python tools/benchmark_import.py --format csv --rows 100000 --chunk-size 2000
```

See `docs/performance.md` for the full matrix.

## Review path

1. `app/Domain/Imports/*SourceReader.php` — bounded format-specific reading/planning.
2. `app/Jobs/ProcessImportChunk.php` — durable chunk claim, transform/validation and staging.
3. `app/Services/ImportFinalizer.php` — concurrency-independent global dedupe/merge.
4. `app/Services/BuildImportSnapshot.php` — immutable execution meaning.
5. `docs/import-pipeline.md`, `docs/failure-recovery.md`, `docs/adr/0003-stage-before-global-dedupe.md`.

## Validation

```bash
php tests/standalone.php
composer test
npm run build
python -m py_compile tools/*.py
```
