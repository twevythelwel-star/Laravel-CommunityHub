<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\GateTailgatingDetectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GateSensorEventController extends Controller
{
    public function __construct(
        protected GateTailgatingDetectionService $tailgatingService
    ) {}

    public function sequence(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'gate' => ['nullable', 'string', 'max:32'],
            'direction' => ['nullable', 'string', 'in:in,out'],
            'pass_id' => ['nullable', 'string', 'max:64'],
            'license_plate' => ['nullable', 'string', 'max:32'],
            'authorized_occupants' => ['nullable', 'integer', 'min:1'],
            'detected_occupants' => ['required', 'integer', 'min:0'],
            'sensor_type' => ['nullable', 'string', 'max:128'],
            'confidence' => ['nullable', 'numeric'],
            'transit_duration_ms' => ['nullable', 'integer'],
        ]);

        $result = $this->tailgatingService->processGateTransitSequence($validated, $request->user());

        return response()->json($result);
    }

    public function resolve(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event_id' => ['required', 'exists:gate_sensor_events,id'],
            'status' => ['required', 'string', 'in:CLEAR,INVESTIGATING,CONFIRMED_VIOLATION,DISMISSED'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $event = $this->tailgatingService->resolveEvent(
            (int) $validated['event_id'],
            $request->user(),
            $validated['status'],
            $validated['notes'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'Sensor event status updated.',
            'event' => $event,
        ]);
    }

    public function recent(): JsonResponse
    {
        $events = $this->tailgatingService->getRecentEvents(15);

        return response()->json([
            'events' => $events,
        ]);
    }
}
