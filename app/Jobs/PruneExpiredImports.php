<?php

namespace App\Jobs;

use App\Models\DataImport;
use App\Services\OperationalMetrics;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\{Log, Storage};
use Throwable;

final class PruneExpiredImports implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(OperationalMetrics $metrics): void
    {
        DataImport::whereNotNull('retention_until')->where('retention_until', '<=', now())
            ->orderBy('retention_until')->chunkById(100, function ($imports) use ($metrics) {
                foreach ($imports as $import) {
                    if ($import->executions()->whereIn('status', ['queued', 'processing'])->exists()) continue;
                    try {
                        Storage::disk(config('filesystems.default'))->delete($import->storage_key);
                        $import->delete();
                    } catch (Throwable $e) {
                        $metrics->storageFailure($import->organization_id);
                        Log::warning('dataforge.retention.storage_failure', [
                            'organization_id' => $import->organization_id,
                            'import_id' => $import->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });
    }
}
