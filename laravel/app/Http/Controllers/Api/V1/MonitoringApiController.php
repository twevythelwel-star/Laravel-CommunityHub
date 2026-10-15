<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Api\Monitoring\ApiTelemetry;
use App\Services\Api\Monitoring\HealthCheck;
use Illuminate\Http\JsonResponse;

class MonitoringApiController extends Controller
{
    /** health() stays public for probes; metrics() is the System Admin's. */
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'active', 'can:operatePlatform'])->only('metrics');
    }

    /**
     * System health check endpoint: 200 when healthy, 503 when degraded.
     */
    public function health(HealthCheck $health): JsonResponse
    {
        ['healthy' => $healthy, 'checks' => $checks] = $health->run();

        return ApiResponse::success(
            [
                'status' => $healthy ? 'healthy' : 'degraded',
                'timestamp' => now()->toIso8601String(),
                'checks' => $checks,
            ],
            $healthy ? 'System is fully operational.' : 'System components degraded.',
            $healthy ? 200 : 503
        );
    }

    /**
     * API Telemetry & Performance Metrics.
     */
    public function metrics(ApiTelemetry $telemetry): JsonResponse
    {
        return ApiResponse::success($telemetry->lastDay(), 'API telemetry and performance metrics retrieved.');
    }
}
