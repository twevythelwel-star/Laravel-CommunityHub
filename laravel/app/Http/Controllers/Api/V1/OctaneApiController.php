<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Performance\OctanePerformanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OctaneApiController extends Controller
{
    public function __construct(
        protected OctanePerformanceService $octane
    ) {
        // Also on the routes; declared here so a regrouped routes/api.php cannot open it.
        $this->middleware(['auth:sanctum', 'active', 'can:operatePlatform']);
    }

    /**
     * Get Octane runtime status and memory telemetry.
     */
    public function status(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Octane runtime metrics retrieved successfully.',
            'data' => $this->octane->getRuntimeMetrics(),
        ]);
    }

    /**
     * Get comparison of FrankenPHP, Swoole, and RoadRunner application servers.
     */
    public function servers(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Supported application servers catalog retrieved successfully.',
            'data' => $this->octane->getApplicationServers(),
        ]);
    }

    /**
     * Get architectural recommendations for long-lived workers.
     */
    public function suitability(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Long-lived worker suitability analysis retrieved successfully.',
            'data' => $this->octane->getWorkloadSuitabilityAnalysis(),
        ]);
    }

    /**
     * Run a concurrency benchmark using Octane::concurrently.
     */
    public function benchmarkConcurrency(Request $request): JsonResponse
    {
        $taskCount = min(max((int) $request->input('tasks', 4), 2), 10);

        $tasks = [];
        for ($i = 1; $i <= $taskCount; $i++) {
            $taskName = "Task #{$i}";
            $tasks["task_{$i}"] = function () use ($taskName) {
                // Simulate compute or async I/O
                usleep(15000); // 15ms simulate

                return [
                    'task' => $taskName,
                    'status' => 'completed',
                    'timestamp' => microtime(true),
                    'checksum' => hash('crc32', $taskName),
                ];
            };
        }

        $result = $this->octane->runConcurrently($tasks);

        return response()->json([
            'success' => true,
            'message' => "Successfully evaluated {$taskCount} concurrent tasks.",
            'data' => $result,
        ]);
    }
}
