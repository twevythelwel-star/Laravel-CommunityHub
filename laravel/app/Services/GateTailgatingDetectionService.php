<?php

namespace App\Services;

use App\Models\GatePass;
use App\Models\GateSensorEvent;
use App\Models\InAppNotification;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class GateTailgatingDetectionService
{
    /**
     * Process full gate sequence:
     * 1. Credential Scan
     * 2. Gate Open
     * 3. Optical / Sensor Transit Detection (people / vehicle count)
     * 4. Gate Closed
     * 5. Anomaly & Tailgating Evaluation
     *
     * @param  array<string, mixed>  $sequence
     * @return array<string, mixed>
     */
    public function processGateTransitSequence(array $sequence, ?User $guard = null): array
    {
        $now = CarbonImmutable::now();
        $gate = $sequence['gate'] ?? 'GATE-01';
        $direction = $sequence['direction'] ?? 'in';
        $passId = $sequence['pass_id'] ?? null;
        $plate = ! empty($sequence['license_plate']) ? strtoupper(trim((string) $sequence['license_plate'])) : null;

        $authorizedOccupants = max(1, (int) ($sequence['authorized_occupants'] ?? 1));
        $detectedOccupants = max(0, (int) ($sequence['detected_occupants'] ?? 1));

        $gatePass = $passId ? GatePass::where('pass_id', $passId)->first() : null;
        $vehicle = $plate ? Vehicle::where('license_plate', $plate)->first() : null;

        $isTailgating = $detectedOccupants > $authorizedOccupants;
        $severity = 'INFO';
        $alertTitle = null;
        $alertMessage = null;

        if ($isTailgating) {
            $excess = $detectedOccupants - $authorizedOccupants;
            $severity = $excess >= 2 ? 'CRITICAL' : 'WARNING';
            $alertTitle = "⚠️ Possible Tailgating Event Detected at {$gate}";
            $alertMessage = sprintf(
                'Credential authorized %d person(s), but camera/optical sensor detected %d people (+%d unauthorized) during single barrier cycle.',
                $authorizedOccupants,
                $detectedOccupants,
                $excess
            );

            // Log security warning
            Log::channel('security')->warning('Tailgating anomaly flagged', [
                'gate' => $gate,
                'pass_id' => $passId,
                'license_plate' => $plate,
                'authorized' => $authorizedOccupants,
                'detected' => $detectedOccupants,
                'excess' => $excess,
            ]);

            // Notify security personnel if guard on duty
            if ($guard) {
                InAppNotification::create([
                    'user_id' => $guard->id,
                    'category' => 'security',
                    'title' => $alertTitle,
                    'body' => $alertMessage,
                    'action_url' => '/dashboard/gate-scanner',
                    'priority' => 'high',
                ]);
            }
        }

        $event = GateSensorEvent::create([
            'gate_pass_id' => $gatePass?->id,
            'pass_id' => $passId,
            'vehicle_id' => $vehicle?->id,
            'license_plate' => $plate,
            'gate' => $gate,
            'direction' => $direction,
            'sequence_state' => $isTailgating ? 'ALERT' : 'COMPLETED',
            'authorized_occupants' => $authorizedOccupants,
            'detected_occupants' => $detectedOccupants,
            'is_tailgating' => $isTailgating,
            'severity' => $severity,
            'alert_title' => $alertTitle,
            'alert_message' => $alertMessage,
            'sensor_metadata' => [
                'sensor_type' => $sequence['sensor_type'] ?? 'Overhead Optical Stereoscopic 3D Sensor + ANPR Plate Camera',
                'confidence' => $sequence['confidence'] ?? 98.4,
                'transit_duration_ms' => $sequence['transit_duration_ms'] ?? 3420,
                'sequence_steps' => [
                    ['step' => 'CREDENTIAL_SCANNED', 'timestamp' => $now->subSeconds(4)->toIso8601String(), 'status' => 'OK'],
                    ['step' => 'GATE_BARRIER_OPENED', 'timestamp' => $now->subSeconds(3)->toIso8601String(), 'status' => 'OK'],
                    ['step' => 'OPTICAL_TRANSIT_DETECTED', 'timestamp' => $now->subSeconds(1)->toIso8601String(), 'detected_count' => $detectedOccupants],
                    ['step' => 'GATE_BARRIER_CLOSED', 'timestamp' => $now->toIso8601String(), 'status' => 'OK'],
                ],
            ],
            'resolution_status' => $isTailgating ? 'UNRESOLVED' : 'CLEAR',
            'occurred_at' => $now,
        ]);

        return [
            'success' => true,
            'id' => $event->id,
            'event' => $event,
            'gate' => $gate,
            'direction' => $direction,
            'passId' => $passId,
            'pass_id' => $passId,
            'licensePlate' => $plate,
            'license_plate' => $plate,
            'authorizedOccupants' => $authorizedOccupants,
            'authorized_occupants' => $authorizedOccupants,
            'detectedOccupants' => $detectedOccupants,
            'detected_occupants' => $detectedOccupants,
            'isTailgating' => $isTailgating,
            'is_tailgating' => $isTailgating,
            'severity' => $severity,
            'alertTitle' => $alertTitle,
            'alert_title' => $alertTitle,
            'alertMessage' => $alertMessage,
            'alert_message' => $alertMessage,
            'resolutionStatus' => $event->resolution_status,
            'resolution_status' => $event->resolution_status,
            'occurredAt' => $event->occurred_at->format('g:i:s A'),
            'sensorMetadata' => $event->sensor_metadata,
            'sequence' => $event->sensor_metadata['sequence_steps'] ?? [],
        ];
    }

    /**
     * Get recent sensor & tailgating events.
     *
     * @return Collection<int, GateSensorEvent>
     */
    public function getRecentEvents(int $limit = 10): Collection
    {
        return GateSensorEvent::with(['gatePass', 'vehicle', 'resolvedBy'])
            ->latest('occurred_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Resolve or investigate a tailgating anomaly.
     */
    public function resolveEvent(int $eventId, User $guard, string $status, ?string $notes = null): GateSensorEvent
    {
        $event = GateSensorEvent::findOrFail($eventId);

        $event->update([
            'resolution_status' => $status,
            'resolved_by' => $guard->id,
            'resolution_notes' => $notes ?: "Resolution updated by {$guard->name}",
        ]);

        return $event;
    }
}
