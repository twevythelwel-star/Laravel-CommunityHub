<?php

namespace App\Services\Safety;

use App\Enums\GateId;
use App\Models\DistressAlert;
use App\Models\DistressAlertEvent;
use App\Models\SafetyProfile;
use Illuminate\Support\Collection;

/**
 * The props of security's silent-assistance queue (Dashboard/SilentAssistance):
 * the latest 100 requests, open and acknowledged first, each with the person,
 * where they are, the audit trail and, while the request is active, their
 * safety profile.
 */
class DistressAlertQueuePresenter
{
    /**
     * @return array{alerts: Collection<int, array<string, mixed>>}
     */
    public function props(): array
    {
        $alerts = DistressAlert::with(['user.vehicles', 'visitor.property', 'visitor.homeowner', 'gatePass', 'safetyProfile', 'acknowledger', 'resolver', 'escalator', 'auditEvents.actor'])
            ->orderByRaw("case when status in ('open', 'acknowledged') then 0 else 1 end")
            ->latest('triggered_at')
            ->limit(100)
            ->get();

        $profiles = SafetyProfile::whereIn('user_id', $alerts->pluck('user_id')->filter()->unique())->get()->keyBy('user_id');

        return [
            'alerts' => $alerts->map(fn (DistressAlert $a) => $this->alert($a, $a->safetyProfile ?? $profiles[$a->user_id] ?? null)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function alert(DistressAlert $a, ?SafetyProfile $profile): array
    {
        $isActive = in_array($a->status, ['open', 'acknowledged'], true);

        return [
            'id' => $a->id,
            'incidentId' => $a->incident_id,
            'auditTrail' => $a->auditEvents->map(fn ($event) => [
                'eventType' => $event->event_type,
                'occurredAt' => $event->occurred_at?->toIso8601String(),
                'actor' => $event->actor?->display_name,
                'snapshot' => $event->snapshot ?? [],
            ])->values(),
            'status' => $a->status,
            'statusLabel' => $a->status === 'acknowledged' ? 'Security responding' : ($a->status === 'open' ? 'Security alerted' : ucfirst(str_replace('_', ' ', $a->status))),
            'assistance' => $a->assistance_type,
            'severity' => $a->severity_level,
            'severityLabel' => $a->severityLabel(),
            'channel' => $a->channelLabel(),
            'gate' => $this->gateLabel($a->gate),
            'event' => $a->event_type ? strtoupper(str_replace('_', ' ', $a->event_type)) : null,
            'eventAt' => $a->event_at?->toIso8601String(),
            'credentialReference' => $a->credential_reference,
            'vehicles' => $a->vehicle_context ?? [],
            'latitude' => $a->latitude,
            'longitude' => $a->longitude,
            'notes' => $a->notes,
            'triggeredAt' => $a->triggered_at->toIso8601String(),
            'escalationLevel' => $a->escalation_level,
            'escalationLabel' => $a->escalationLabel(),
            'escalatedAt' => $a->escalated_at?->toIso8601String(),
            'escalatedBy' => $a->escalator?->display_name,
            'canEscalateToSecurity' => $a->canEscalateToSecurity(),
            'canEscalateToEmergencyServices' => $a->canEscalateToEmergencyServices(),
            'isEmergencyServicesEligible' => $a->isEmergencyServicesEligible(),
            'resident' => $this->person($a),
            // The profile is for responding to an active request; once
            // closed, the medical notes and contact are no longer shown.
            'profile' => $isActive && $profile ? $this->profile($a, $profile) : null,
            'acknowledgedBy' => $a->acknowledger?->display_name,
            'acknowledgedAt' => $a->acknowledged_at?->toIso8601String(),
            'resolvedBy' => $a->resolver?->display_name,
            'resolvedAt' => $a->resolved_at?->toIso8601String(),
            'resolutionNotes' => $a->resolution_notes,
        ];
    }

    /**
     * Who asked for help: a resident, a visitor, a pass holder or someone
     * acting for an owning entity.
     *
     * @return array<string, mixed>
     */
    private function person(DistressAlert $a): array
    {
        return [
            'name' => $a->personName() ?? $a->user?->display_name ?? $a->visitor?->name ?? $a->gatePass?->holder_name,
            'person' => $a->personName(),
            'capacity' => $a->capacityLabel(),
            'representativeRole' => $a->representativeRole(),
            'ownerEntity' => $a->ownerEntityName(),
            'ownerEntityType' => $a->ownerEntityType(),
            'isEntityRepresentative' => $a->isEntityRepresentative(),
            'phone' => $a->user?->phone,
            'property' => $a->propertyLabel() ?? $a->user?->propertyLabel() ?? $a->gatePass?->property ?? $a->visitor?->property?->label() ?? $a->visitor?->homeowner?->propertyLabel(),
            'holderType' => $a->auditEvents->firstWhere('event_type', DistressAlertEvent::ACTIVATED)?->snapshot['holderType'] ?? null,
        ];
    }

    /**
     * The safety profile, with the personal fields (contacts, medical notes)
     * only for a profile that belongs to a user account.
     *
     * @return array<string, mixed>
     */
    private function profile(DistressAlert $a, SafetyProfile $profile): array
    {
        $personal = $profile->user_id !== null;
        $preferredVehicle = $a->user?->vehicles?->firstWhere('id', $profile->preferred_vehicle_id);

        return [
            'safetyCredentialId' => $profile->safety_credential_id,
            'emergencyContactName' => $personal ? $profile->emergency_contact_name : null,
            'emergencyContactPhone' => $personal ? $profile->emergency_contact_phone : null,
            'emergencyContactRelationship' => $personal ? $profile->emergency_contact_relationship : null,
            'preferredAssistance' => $profile->preferred_assistance,
            'assistancePreferences' => collect($profile->assistance_preferences ?: [$profile->preferred_assistance])
                ->filter(fn ($type) => in_array($type, ['security', 'police', 'medical', 'fire', 'emergency_services'], true))
                ->values()
                ->all() ?: ['security'],
            'emergencyContacts' => $personal ? $this->emergencyContacts($profile) : [],
            'preferredGate' => $this->gateLabel($profile->preferred_gate),
            'preferredVehicle' => $preferredVehicle
                ? $preferredVehicle->fullDescription().' · '.$preferredVehicle->formattedPlate()
                : null,
            'medicalNotes' => $personal ? $profile->medical_notes : null,
        ];
    }

    /**
     * The contact list, or the single legacy contact when no list was saved.
     *
     * @return list<array<string, mixed>>
     */
    private function emergencyContacts(SafetyProfile $profile): array
    {
        return $profile->emergency_contacts ?: array_values(array_filter([[
            'name' => $profile->emergency_contact_name,
            'phone' => $profile->emergency_contact_phone,
            'relationship' => $profile->emergency_contact_relationship,
        ]], fn ($contact) => filled($contact['name']) || filled($contact['phone'])));
    }

    /** A gate's configured name, else its enum label, else the raw id. */
    private function gateLabel(?string $gate): ?string
    {
        return $gate
            ? config('gatepass.gates.'.$gate, GateId::tryFrom($gate)?->label() ?? $gate)
            : null;
    }
}
