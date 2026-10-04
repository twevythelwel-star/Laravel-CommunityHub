<?php

namespace App\Services\Performance;

use Closure;
use Laravel\Octane\Facades\Octane as OctaneFacade;
use Laravel\Octane\Octane;
use Throwable;

class OctanePerformanceService
{
    /**
     * Get comparison specifications for the three supported application servers.
     */
    public function getApplicationServers(): array
    {
        $currentServer = config('octane.server', 'frankenphp');

        return [
            'frankenphp' => [
                'name' => 'FrankenPHP',
                'language' => 'Go + Caddy Engine',
                'is_active' => $currentServer === 'frankenphp',
                'performance_tier' => 'Ultra High (Worker Mode)',
                'key_features' => [
                    'Built-in Caddy web server with automatic TLS / HTTPS',
                    '103 Early Hints HTTP protocol support',
                    'Worker mode eliminates PHP boot overhead completely',
                    'Real-time asset compression with Zstandard & Brotli',
                    'Native Mercure real-time hub integration',
                ],
                'optimal_use_cases' => 'Modern microservices, Docker/Kubernetes containerized deployments, Cloud-native SaaS platforms.',
            ],
            'swoole' => [
                'name' => 'Swoole / OpenSwoole',
                'language' => 'C / C++ PHP Extension',
                'is_active' => $currentServer === 'swoole',
                'performance_tier' => 'Extreme (Coroutine Event Loop)',
                'key_features' => [
                    'Asynchronous, non-blocking I/O event loop',
                    'Persistent in-memory tables (Swoole\Table) with lock-free concurrency',
                    'Multi-process worker pool with coroutine task execution',
                    'Shared memory state caching across workers without Redis network hops',
                ],
                'optimal_use_cases' => 'High-throughput gatepass scanners, RFID card verification, telemetry ingestion, high-concurrency APIs.',
            ],
            'roadrunner' => [
                'name' => 'RoadRunner',
                'language' => 'Go High-Performance Server',
                'is_active' => $currentServer === 'roadrunner',
                'performance_tier' => 'High (Process Supervisor)',
                'key_features' => [
                    'Multi-worker Go process supervisor and load balancer',
                    'Independent isolated worker processes for zero state leakage',
                    'Native PSR-7 HTTP message bridge integration',
                    'Zero-downtime worker reloads upon code modification',
                ],
                'optimal_use_cases' => 'Enterprise applications requiring rigid process isolation and robust memory leak resilience.',
            ],
        ];
    }

    /**
     * Get runtime telemetry and memory metrics.
     */
    public function getRuntimeMetrics(): array
    {
        $memUsage = memory_get_usage(true);
        $memPeak = memory_get_peak_usage(true);
        $gcRuns = gc_status()['runs'] ?? 0;

        $isOctaneRunning = isset($_SERVER['LARAVEL_OCTANE']) || isset($_ENV['LARAVEL_OCTANE']);

        return [
            'octane_installed' => class_exists(Octane::class),
            'octane_running' => $isOctaneRunning,
            'configured_server' => config('octane.server', 'frankenphp'),
            'https_forced' => (bool) config('octane.https', false),
            'max_execution_time' => (int) config('octane.max_execution_time', 30),
            'garbage_threshold_mb' => (int) config('octane.garbage', 50),
            'memory_used_mb' => round($memUsage / 1024 / 1024, 2),
            'memory_peak_mb' => round($memPeak / 1024 / 1024, 2),
            'garbage_collector_runs' => $gcRuns,
            'tables_configured' => array_keys(config('octane.tables', [])),
            'warmed_services_count' => count(config('octane.warm', [])),
            'flushed_listeners_count' => count(config('octane.listeners', [])),
            'php_version' => PHP_VERSION,
            'opcache_enabled' => function_exists('opcache_get_status') && ! empty(opcache_get_status(false)['opcache_enabled']),
        ];
    }

    /**
     * Execute concurrent tasks safely using Octane::concurrently with graceful fallback.
     *
     * @param  array<Closure>  $tasks
     */
    public function runConcurrently(array $tasks): array
    {
        $startTime = microtime(true);
        $results = [];
        $isOctaneRunning = isset($_SERVER['LARAVEL_OCTANE']) || isset($_ENV['LARAVEL_OCTANE']);

        try {
            if (class_exists(OctaneFacade::class) && app()->bound('octane')) {
                $results = OctaneFacade::concurrently($tasks);
            } else {
                // Fallback to sequential execution for test/CLI environments
                foreach ($tasks as $key => $task) {
                    $results[$key] = $task();
                }
            }
        } catch (Throwable) {
            foreach ($tasks as $key => $task) {
                $results[$key] = $task();
            }
        }

        $elapsedMs = round((microtime(true) - $startTime) * 1000, 2);

        return [
            'results' => $results,
            'tasks_count' => count($tasks),
            'elapsed_ms' => $elapsedMs,
            'execution_mode' => $isOctaneRunning ? 'parallel_octane' : 'octane_dispatcher_fallback',
        ];
    }

    /**
     * Provide architectural guidance on when long-lived workers are beneficial.
     */
    public function getWorkloadSuitabilityAnalysis(): array
    {
        return [
            'benefits_long_lived_workers' => [
                [
                    'workload' => 'Gatehouse Scanner & Kiosk APIs',
                    'verdict' => 'Highly Recommended',
                    'reasoning' => 'Continuous barcode, QR, and RFID token validation benefits from zero framework boot time (3ms vs 45ms per request).',
                ],
                [
                    'workload' => 'High-Throughput Mobile App Telemetry',
                    'verdict' => 'Highly Recommended',
                    'reasoning' => 'GPS gate approach pings and live beacon heartbeats handle 10x higher request volumes per CPU core.',
                ],
                [
                    'workload' => 'Real-Time WebSocket Broadcasting & Webhook Ingestion',
                    'verdict' => 'Recommended',
                    'reasoning' => 'Persistent memory tables store rate limits and token validations in memory without Redis round-trips.',
                ],
            ],
            'requires_standard_lifecycle' => [
                [
                    'workload' => 'Legacy Packages with Global Static State',
                    'verdict' => 'Caution / Avoid',
                    'reasoning' => 'Code relying on global static singletons without Octane sandbox flushes can leak data across distinct tenant requests.',
                ],
                [
                    'workload' => 'One-Off Low-Traffic Internal Admin Tools',
                    'verdict' => 'Neutral',
                    'reasoning' => 'When request rates are less than 1 req/min, boot latency is imperceptible to users.',
                ],
            ],
        ];
    }
}
