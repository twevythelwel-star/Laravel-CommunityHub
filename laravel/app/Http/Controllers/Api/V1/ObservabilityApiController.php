<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Observability\ObservabilityService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class ObservabilityApiController extends Controller
{
    public function __construct(
        protected ObservabilityService $observabilityService
    ) {
        // Also on the routes; declared here so a regrouped routes/api.php cannot open it.
        $this->middleware(['auth:sanctum', 'active', 'can:operatePlatform']);
    }

    /**
     * Complete observability status and 7-pillar overview.
     */
    public function status(): JsonResponse
    {
        $overview = $this->observabilityService->getFullObservabilityOverview();

        return ApiResponse::success(
            $overview,
            'Observability module overview retrieved.'
        );
    }

    /**
     * Subsystem deep health check diagnostics.
     */
    public function health(): JsonResponse
    {
        $checks = $this->observabilityService->getHealthChecks();
        $system = $this->observabilityService->getSystemStatus();

        $statusCode = $system['is_healthy'] ? 200 : 503;

        return ApiResponse::success(
            [
                'system' => $system,
                'subsystems' => $checks,
                'checked_at' => now()->toIso8601String(),
            ],
            $system['is_healthy'] ? 'All core subsystems healthy.' : 'Subsystems reporting degraded health.',
            $statusCode
        );
    }

    /**
     * Performance metrics (Pulse / Telescope inspired).
     */
    public function performance(): JsonResponse
    {
        $performance = $this->observabilityService->getPerformanceMetrics();

        return ApiResponse::success(
            $performance,
            'Performance and operational telemetry retrieved.'
        );
    }

    /**
     * Application logs with filtering and search.
     */
    public function logs(Request $request): JsonResponse
    {
        $level = $request->query('level');
        $search = $request->query('search');
        $limit = min((int) $request->query('limit', 50), 100);

        $logs = $this->observabilityService->getApplicationLogs($level, $search, $limit);

        return ApiResponse::success(
            [
                'count' => count($logs),
                'filter' => ['level' => $level, 'search' => $search],
                'entries' => $logs,
            ],
            'Application logs retrieved.'
        );
    }

    /**
     * Audit logs and entity changes.
     */
    public function auditLogs(Request $request): JsonResponse
    {
        $event = $request->query('event');
        $userId = $request->query('user_id');
        $limit = min((int) $request->query('limit', 50), 100);

        $auditLogs = $this->observabilityService->getAuditLogs($event, $userId, $limit);

        return ApiResponse::success(
            [
                'count' => count($auditLogs),
                'logs' => $auditLogs,
            ],
            'Audit logs retrieved.'
        );
    }

    /**
     * Tracked errors and exceptions.
     */
    public function errors(Request $request): JsonResponse
    {
        $limit = min((int) $request->query('limit', 20), 50);
        $errorMetrics = $this->observabilityService->getErrorTrackingMetrics($limit);

        return ApiResponse::success(
            $errorMetrics,
            'Error tracking metrics retrieved.'
        );
    }

    /**
     * Resolve a tracked error.
     */
    public function resolveError(int $id): JsonResponse
    {
        $resolved = $this->observabilityService->resolveError($id, auth()->user());

        if (! $resolved) {
            return ApiResponse::error('Tracked error record not found.', 404);
        }

        return ApiResponse::success(
            [
                'id' => $resolved->id,
                'status' => $resolved->status,
                'resolved_at' => $resolved->resolved_at?->toIso8601String(),
            ],
            'Error marked as resolved.'
        );
    }

    /**
     * Queue depths and Horizon telemetry.
     */
    public function queueMetrics(): JsonResponse
    {
        $queueMetrics = $this->observabilityService->getQueueMetrics();

        return ApiResponse::success(
            $queueMetrics,
            'Queue metrics and Horizon telemetry retrieved.'
        );
    }

    /**
     * Simulate and track an error (for testing / verification).
     */
    public function simulateError(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'nullable|string|max:255',
            'severity' => 'nullable|string|in:notice,warning,error,critical',
        ]);

        $message = $validated['message'] ?? 'Simulated observability anomaly trigger';
        $severity = $validated['severity'] ?? 'error';

        try {
            throw new Exception($message);
        } catch (Throwable $e) {
            $tracked = $this->observabilityService->trackException($e, [
                'triggered_by' => auth()->user()?->name ?? 'API Test Client',
                'ip' => $request->ip(),
            ], $severity);

            return ApiResponse::success(
                [
                    'id' => $tracked->id,
                    'fingerprint' => $tracked->fingerprint,
                    'severity' => $tracked->severity,
                    'status' => $tracked->status,
                    'occurrences_count' => $tracked->occurrences_count,
                ],
                'Simulated exception recorded in ErrorTracker.',
                201
            );
        }
    }

    /**
     * Write an audit log entry (for testing / verification).
     */
    public function writeAudit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|string|max:255',
            'details' => 'nullable|array',
        ]);

        $entry = $this->observabilityService->recordAudit(
            $validated['action'],
            auth()->user(),
            $validated['details'] ?? []
        );

        return ApiResponse::success(
            [
                'id' => $entry?->id,
                'action' => $validated['action'],
                'causer' => auth()->user()?->name ?? 'Anonymous',
            ],
            'Audit log event recorded.',
            201
        );
    }
}
