<?php

namespace App\Actions\Realtime;

use App\Models\User;
use App\Services\Realtime\RealtimeBroadcasterService;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

/**
 * Sends one real-time event of the requested type through Reverb, filling
 * in demo values for anything the caller left out. Who may send which type
 * is decided by BroadcastRealtimeEventApiRequest.
 */
class BroadcastRealtimeEventAction
{
    public function __construct(private readonly RealtimeBroadcasterService $realtime) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function execute(User $actor, array $validated): ShouldBroadcast
    {
        return match ($validated['type']) {
            'chat' => $this->realtime->broadcastChatMessage(
                roomId: $validated['room_id'] ?? 'general',
                user: $actor,
                message: $validated['message'] ?? 'Real-time broadcast test message'
            ),
            'telemetry' => $this->realtime->broadcastDashboardTelemetry(
                telemetry: $validated['telemetry'] ?? [
                    'active_visitors_count' => rand(12, 38),
                    'active_passes_count' => rand(40, 95),
                    'system_health' => 'optimal',
                    'gate_clearance_seconds' => rand(15, 30),
                ]
            ),
            'notification' => $this->realtime->broadcastLiveNotification(
                userId: $validated['user_id'] ?? $actor->id,
                title: $validated['title'] ?? 'Real-time Alert',
                body: $validated['body'] ?? 'Live notification dispatched via Laravel Reverb.'
            ),
            'pass' => $this->realtime->broadcastGatePassUpdate(
                passId: $validated['pass_id'] ?? 101,
                passCode: 'GP-'.rand(1000, 9999),
                status: $validated['status'] ?? 'scanned',
                visitorName: 'Visitor Demo',
                gate: 'Main Gate 1',
                ownerId: $actor->id
            ),
            default => $this->realtime->broadcastOperationsAlert(
                type: 'perimeter_monitoring',
                severity: $validated['severity'] ?? 'warning',
                headline: $validated['headline'] ?? 'Sensor Alert: North Sector Perimeter Activity',
                location: 'North Zone Gate 3',
                operatorName: $actor->name
            ),
        };
    }
}
