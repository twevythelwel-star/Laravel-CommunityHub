<?php

declare(strict_types=1);

namespace App\Services\Api\Monitoring;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * The probes behind /api/health: database, cache, local storage and runtime.
 * A failed database or cache marks the system degraded; storage is reported
 * but does not, as before.
 */
class HealthCheck
{
    /**
     * @return array{healthy: bool, checks: array<string, array<string, mixed>>}
     */
    public function run(): array
    {
        $checks = [
            'database' => $this->probe(fn () => [
                'status' => 'ok',
                'latency_ms' => $this->database(),
            ]),
            'cache' => $this->probe(fn () => [
                'status' => $this->cacheRoundTrips() ? 'ok' : 'error',
                'driver' => config('cache.default'),
            ]),
            'storage' => $this->probe(fn () => [
                'status' => $this->storageWritable() ? 'ok' : 'error',
            ]),
            'system' => [
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2).' MB',
                'server_time' => now()->toIso8601String(),
            ],
        ];

        return [
            'healthy' => ! isset($checks['database']['message']) && ! isset($checks['cache']['message']),
            'checks' => $checks,
        ];
    }

    /**
     * @param  callable(): array<string, mixed>  $check
     * @return array<string, mixed>
     */
    private function probe(callable $check): array
    {
        try {
            return $check();
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /** Round-trip latency of a trivial query, in milliseconds. */
    private function database(): float
    {
        DB::connection()->getPdo();

        $start = microtime(true);
        DB::select('SELECT 1');

        return round((microtime(true) - $start) * 1000, 2);
    }

    private function cacheRoundTrips(): bool
    {
        $key = 'health_check_ping_'.microtime(true);
        Cache::put($key, 'pong', 5);
        $value = Cache::get($key);
        Cache::forget($key);

        return $value === 'pong';
    }

    private function storageWritable(): bool
    {
        $written = Storage::disk('local')->put('.health_test', 'test');
        Storage::disk('local')->delete('.health_test');

        return (bool) $written;
    }
}
