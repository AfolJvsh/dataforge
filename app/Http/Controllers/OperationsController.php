<?php

namespace App\Http\Controllers;

use App\Models\{DataImport, ImportChunk, ImportExecution};
use App\Services\OperationalMetrics;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\Redis;

final class OperationsController
{
    public function metrics(Request $request, string $organizationId, OperationalMetrics $metrics): JsonResponse
    {
        abort_unless($request->user()->organizations()->whereKey($organizationId)->exists(), 403);
        $since = now()->subHours(24);
        $importIds = DataImport::where('organization_id', $organizationId)->withTrashed()->pluck('id');
        $executionIds = ImportExecution::whereIn('import_id', $importIds)->where('created_at', '>=', $since)->pluck('id');
        $executions = ImportExecution::whereIn('id', $executionIds)->get();
        $chunks = ImportChunk::whereIn('execution_id', $executionIds)->get(['duration_ms', 'db_write_ms', 'peak_memory_bytes', 'status']);

        $durations = $chunks->pluck('duration_ms')->filter(fn ($v) => $v !== null)->map(fn ($v) => (int) $v)->sort()->values();
        $dbWrites = $chunks->pluck('db_write_ms')->filter(fn ($v) => $v !== null)->map(fn ($v) => (int) $v)->sort()->values();
        $queueLags = $executions->map(fn ($e) => (int) data_get($e->metrics_json, 'queue_lag_ms', 0))->filter()->sort()->values();
        $processed = (int) $executions->sum('processed_rows');
        $invalid = (int) $executions->sum('invalid_rows');
        $duplicates = (int) $executions->sum('duplicate_rows');
        $seconds = max(.001, $executions->filter(fn ($e) => $e->started_at && $e->completed_at)
            ->sum(fn ($e) => $e->started_at->diffInMilliseconds($e->completed_at) / 1000));

        $queues = [];
        foreach (['imports-analyze', 'imports-plan', 'imports'] as $name) {
            $queues[$name] = (int) Redis::llen('queues:'.$name);
        }

        return response()->json([
            'window' => '24h',
            'imports_created' => DataImport::where('organization_id', $organizationId)->where('created_at', '>=', $since)->count(),
            'executions' => $executions->count(),
            'active_executions' => ImportExecution::whereIn('import_id', $importIds)->whereIn('status', ['queued', 'processing'])->count(),
            'queue_depth' => $queues,
            'queue_lag_ms' => ['p50' => $this->pct($queueLags, .5), 'p95' => $this->pct($queueLags, .95)],
            'rows_per_second' => round($processed / $seconds, 2),
            'chunk_duration_ms' => ['p50' => $this->pct($durations, .5), 'p95' => $this->pct($durations, .95)],
            'db_batch_latency_ms' => ['p50' => $this->pct($dbWrites, .5), 'p95' => $this->pct($dbWrites, .95)],
            'error_rate' => $processed ? round($invalid / $processed, 6) : 0,
            'duplicate_rate' => $processed ? round($duplicates / $processed, 6) : 0,
            'storage_failures' => $metrics->storageFailures($organizationId),
            'peak_worker_memory_bytes' => (int) ($chunks->max('peak_memory_bytes') ?? 0),
        ]);
    }

    private function pct($values, float $p): ?int
    {
        if ($values->isEmpty()) return null;
        return (int) $values[(int) floor(($values->count() - 1) * $p)];
    }
}
