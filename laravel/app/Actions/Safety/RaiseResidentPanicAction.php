<?php

namespace App\Actions\Safety;

use App\Enums\UserRole;
use App\Events\Realtime\OperationsCommandCenterEvent;
use App\Events\Realtime\ResidentPanicAlertEvent;
use App\Models\InAppNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

/**
 * A resident's SOS: broadcast to the panic and command-centre channels,
 * an in-app critical notice to every active security officer and
 * administrator, and an entry in the security log.
 */
class RaiseResidentPanicAction
{
    /**
     * @param  array{emergency_type: string, latitude?: mixed, longitude?: mixed, notes?: ?string}  $validated
     * @return string The alert id, e.g. SOS-20261015-A1B2C3.
     */
    public function execute(User $resident, array $validated): string
    {
        $alertId = sprintf('SOS-%s-%s', date('Ymd'), strtoupper(bin2hex(random_bytes(3))));
        $location = $this->location($resident);
        $type = $validated['emergency_type'];
        $notes = $validated['notes'] ?? null;

        ResidentPanicAlertEvent::dispatch(
            alertId: $alertId,
            residentId: $resident->id,
            residentName: $resident->name,
            lot: $location,
            phone: $resident->phone,
            emergencyType: $type,
            latitude: isset($validated['latitude']) ? (float) $validated['latitude'] : null,
            longitude: isset($validated['longitude']) ? (float) $validated['longitude'] : null,
            notes: $notes,
            metadata: [
                'role' => $resident->role->value,
                'email' => $resident->email,
            ]
        );

        OperationsCommandCenterEvent::dispatch(
            alertId: $alertId,
            type: 'emergency',
            severity: 'critical',
            headline: sprintf('🚨 RESIDENT SOS: %s ALERT (%s)', strtoupper($type), $resident->name),
            location: $location,
            operatorName: $resident->name,
            details: [
                'resident_id' => $resident->id,
                'resident_phone' => $resident->phone,
                'emergency_type' => $type,
                'notes' => $notes,
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
            ]
        );

        $this->notifyResponders($resident, $alertId, $location, $type, $notes);

        Log::channel('security')->critical('Resident emergency panic triggered', [
            'alert_id' => $alertId,
            'user_id' => $resident->id,
            'user_name' => $resident->name,
            'location' => $location,
            'type' => $type,
            'notes' => $notes,
        ]);

        return $alertId;
    }

    private function location(User $resident): string
    {
        return $resident->lot
            ? sprintf('%s, %s', $resident->lot, $resident->street ?? 'Community')
            : ($resident->street ?? 'Main Community Premises');
    }

    /** @return Collection<int, User> */
    private function responders(): Collection
    {
        return User::query()
            ->whereIn('role', [UserRole::Security->value, UserRole::Admin->value, UserRole::SystemAdmin->value])
            ->where('status', 'Active')
            ->get();
    }

    private function notifyResponders(User $resident, string $alertId, string $location, string $type, ?string $notes): void
    {
        foreach ($this->responders() as $responder) {
            InAppNotification::create([
                'user_id' => $responder->id,
                'tenant_id' => $resident->tenant_id ?? null,
                'category' => 'emergency',
                'title' => sprintf('🚨 [CRITICAL SOS] %s Alert - %s', strtoupper($type), $resident->name),
                'body' => sprintf(
                    'Resident %s (%s) triggered an SOS panic at %s. Contact: %s. %s',
                    $resident->name,
                    $resident->role->value,
                    $location,
                    $resident->phone ?? 'No phone listed',
                    ! empty($notes) ? 'Notes: '.$notes : ''
                ),
                'action_url' => '/dashboard/command-center',
                'priority' => 'critical',
                'data' => [
                    'alert_id' => $alertId,
                    'resident_id' => $resident->id,
                    'location' => $location,
                    'emergency_type' => $type,
                ],
            ]);
        }
    }
}
