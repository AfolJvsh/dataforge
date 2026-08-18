<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

final class OperationalMetrics
{
    public function storageFailure(string $organizationId): void
    {
        $key = "dataforge:metrics:storage_failures:$organizationId";
        Redis::incr($key);
        Redis::expire($key, 60 * 60 * 24 * 31);
    }

    public function storageFailures(string $organizationId): int
    {
        return (int) (Redis::get("dataforge:metrics:storage_failures:$organizationId") ?? 0);
    }
}
