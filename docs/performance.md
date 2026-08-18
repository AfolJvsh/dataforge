# Performance and benchmark protocol

The benchmark is reproducible against the real HTTP/queue pipeline.

```bash
python tools/benchmark_import.py --format csv --rows 10000
python tools/benchmark_import.py --format csv --rows 100000
python tools/benchmark_import.py --format csv --rows 1000000
python tools/benchmark_import.py --format ndjson --rows 100000
python tools/benchmark_import.py --format xlsx --rows 100000
```

Each run generates deterministic duplicate/invalid data, registers or logs into the app, uploads the real file, waits for analysis, creates a versioned destination schema, persists a mapping, starts an execution and polls until terminal state. Results are written under `storage/benchmarks/`.

Capture:
- wall-clock execution time;
- application rows/sec;
- p50/p95 chunk duration;
- peak worker memory from chunk records;
- processed/success/invalid/duplicate counts;
- chunk status distribution;
- error-code distribution.

Run 10k/100k/1m with the same chunk size before changing tuning. Then vary `--chunk-size` (500, 2,000, 5,000) and worker count independently. The goal is bounded memory and predictable recovery, not the largest possible single-process throughput number.
