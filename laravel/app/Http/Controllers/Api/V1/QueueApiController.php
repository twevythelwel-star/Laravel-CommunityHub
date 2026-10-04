<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Queue\QueueMonitoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QueueApiController extends Controller
{
    public function __construct(
        protected QueueMonitoringService $queueService
    ) {
        // Also on the routes; declared here so a regrouped routes/api.php cannot open it.
        $this->middleware(['auth:sanctum', 'active', 'can:operatePlatform']);
    }

    /**
     * Get queue telemetry and metrics across priority pools.
     */
    public function stats(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Queue metrics retrieved successfully.',
            'data' => $this->queueService->getQueueMetrics(),
        ]);
    }

    /**
     * Get catalog of the 11 supported enterprise background processing jobs.
     */
    public function jobs(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Queue job catalog retrieved successfully.',
            'data' => $this->queueService->getJobCatalog(),
        ]);
    }

    /**
     * Get Horizon supervisor and Redis infrastructure status.
     */
    public function horizonStatus(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Horizon status retrieved successfully.',
            'data' => $this->queueService->getHorizonStatus(),
        ]);
    }

    /**
     * Dispatch an enterprise background processing job into the queue.
     */
    public function dispatchJob(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:email,sms,report,export,import,image,pdf,notification,ai,sync,webhook'],
            'payload' => ['nullable', 'array'],
        ]);

        $type = $validated['type'];
        $payload = $validated['payload'] ?? [];
        $payload['user_id'] = $payload['user_id'] ?? $request->user()?->id;

        $this->queueService->dispatchJob($type, $payload);

        $catalog = $this->queueService->getJobCatalog();
        $jobInfo = $catalog[$type] ?? [];

        return response()->json([
            'success' => true,
            'message' => "Background job [{$type}] successfully queued on [{$jobInfo['queue']}] priority pool.",
            'data' => [
                'job_type' => $type,
                'job_name' => $jobInfo['name'] ?? $type,
                'target_queue' => $jobInfo['queue'] ?? 'default',
                'priority' => $jobInfo['priority'] ?? 'normal',
                'dispatched_at' => now()->toIso8601String(),
            ],
        ]);
    }
}
