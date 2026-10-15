<?php

namespace App\Services\Safety;

use App\Enums\GateId;
use App\Models\DistressAlert;
use App\Models\SafetyProfile;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The props of a user's own safety page (Dashboard/Safety).
 *
 * Residents and administrators keep a full profile (contacts, medical
 * notes, preferred gate and vehicle) and see their past requests; anyone
 * else who may ask for help (an appointee) gets only the request options.
 */
class SafetyPagePresenter
{
    public function __construct(private readonly SilentAssistanceService $safety) {}

    /**
     * @return array<string, mixed>
     */
    public function props(User $user): array
    {
        $context = $this->safety->accessContextFor($user);
        $options = $this->safety->optionsForUser($user);
        $profile = $this->safety->profileFor($user);
        $full = $user->role->isResident() || $user->role->isAdministrative();

        return [
            'incidents' => $full ? $this->incidents($user) : collect(),
            'holderLabel' => $context['label'],
            'assistanceOptions' => $options,
            'profile' => $this->profile($profile, $options, $full),
            'publicEmergencyNumber' => config('safety.public_emergency_number'),
            'gateOptions' => $full ? $this->gateOptions() : [],
            'vehicleOptions' => $full ? $user->vehicles->map(fn ($vehicle) => [
                'value' => (string) $vehicle->id,
                'label' => $vehicle->fullDescription().' · '.$vehicle->formattedPlate(),
            ])->values() : [],
            'severityOptions' => $full ? [
                ['value' => DistressAlert::SEVERITY_ASSISTANCE, 'label' => 'Assistance'],
                ['value' => DistressAlert::SEVERITY_URGENT_ASSISTANCE, 'label' => 'Urgent Assistance'],
                ['value' => DistressAlert::SEVERITY_SILENT_EMERGENCY, 'label' => 'Silent Emergency'],
            ] : [],
            'profileEnabled' => $full,
            'holdSeconds' => (int) config('safety.qr_hold_seconds', 3),
            'armedMinutes' => (int) config('safety.armed_minutes', 15),
        ];
    }

    private function incidents(User $user): Collection
    {
        return $this->safety->accountIncidents($user)->map(fn (DistressAlert $alert) => [
            'incidentId' => $alert->incident_id,
            'assistance' => $alert->assistance_type,
            'status' => $alert->status,
            'assistanceLabel' => ucfirst($alert->assistance_type),
            'activatedAt' => $alert->triggered_at?->toIso8601String(),
            'closedAt' => $alert->resolved_at?->toIso8601String(),
            'auditTrail' => $alert->auditEvents->map(fn ($event) => [
                'eventType' => $event->event_type,
                'occurredAt' => $event->occurred_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    /**
     * Personal fields only for a full profile; the rest is what any requester
     * needs to see about how their request will be handled.
     *
     * @param  list<string>  $options
     * @return array<string, mixed>
     */
    private function profile(SafetyProfile $profile, array $options, bool $full): array
    {
        return [
            'emergencyContactName' => $full ? $profile->emergency_contact_name : null,
            'emergencyContactPhone' => $full ? $profile->emergency_contact_phone : null,
            'emergencyContactRelationship' => $full ? $profile->emergency_contact_relationship : null,
            'preferredAssistance' => $profile->preferred_assistance,
            'assistancePreferences' => $full
                ? (collect($profile->assistance_preferences ?: [$profile->preferred_assistance])
                    ->filter(fn ($type) => in_array($type, $options, true))
                    ->values()
                    ->all() ?: ['security'])
                : ['security'],
            'emergencyContacts' => $full ? $profile->emergencyContactList() : [],
            'preferredGate' => $full ? $profile->preferred_gate : null,
            'preferredVehicleId' => $full ? $profile->preferred_vehicle_id : null,
            'medicalNotes' => $full ? $profile->medical_notes : null,
            'silentAlertsEnabled' => $profile->silent_alerts_enabled,
            'hasDuressPin' => $full && $profile->hasDuressPin(),
        ];
    }

    /** @return Collection<int, array{value: string, label: string}> */
    private function gateOptions(): Collection
    {
        return collect(GateId::cases())
            ->reject(fn (GateId $gate) => $gate === GateId::Any)
            ->map(fn (GateId $gate) => ['value' => $gate->value, 'label' => config('gatepass.gates.'.$gate->value, $gate->label())])
            ->values();
    }
}
