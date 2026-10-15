<?php

namespace App\Actions\Safety;

use App\Models\User;
use App\Services\Safety\SilentAssistanceService;

/**
 * Saves a resident's safety profile. Fields left out of the request keep
 * their saved value; assistance preferences are narrowed to what the user
 * may ask for (never empty), and the first contact is mirrored into the
 * older emergency_contact_* columns.
 */
class UpdateSafetyProfileAction
{
    public function __construct(private readonly SilentAssistanceService $safety) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function execute(User $user, array $validated): void
    {
        $allowed = $this->safety->optionsForUser($user);
        $profile = $this->safety->profileFor($user);

        $preferences = array_values(array_intersect(
            $validated['assistance_preferences'] ?? $profile->assistance_preferences ?? [$profile->preferred_assistance],
            $allowed,
        )) ?: [$allowed[0] ?? 'security'];
        abort_unless(count(array_diff($preferences, $allowed)) === 0, 422);

        $contacts = $this->contacts($validated) ?? $profile->emergencyContactList();
        $primary = $contacts[0] ?? [];
        $keep = fn (string $field) => array_key_exists($field, $validated) ? $validated[$field] : $profile->{$field};

        $profile->update([
            'preferred_assistance' => in_array($profile->preferred_assistance, $preferences, true) ? $profile->preferred_assistance : $preferences[0],
            'assistance_preferences' => array_values(array_unique($preferences)),
            'emergency_contacts' => $contacts,
            'emergency_contact_name' => $primary['name'] ?? null,
            'emergency_contact_phone' => $primary['phone'] ?? null,
            'emergency_contact_relationship' => $primary['relationship'] ?? null,
            'preferred_gate' => $keep('preferred_gate'),
            'preferred_vehicle_id' => $keep('preferred_vehicle_id'),
            'medical_notes' => $keep('medical_notes'),
            'silent_alerts_enabled' => $validated['silent_alerts_enabled'],
        ]);
    }

    /**
     * The contacts the request set: a list, or (from older clients) a single
     * name and phone, where blanking either clears the list. Null when the
     * request did not touch contacts.
     *
     * @param  array<string, mixed>  $validated
     * @return list<array<string, mixed>>|null
     */
    private function contacts(array $validated): ?array
    {
        if (array_key_exists('emergency_contacts', $validated)) {
            return $validated['emergency_contacts'];
        }

        if (! array_key_exists('emergency_contact_name', $validated)) {
            return null;
        }

        return filled($validated['emergency_contact_name']) && filled($validated['emergency_contact_phone'] ?? null) ? [[
            'name' => $validated['emergency_contact_name'],
            'phone' => $validated['emergency_contact_phone'],
            'relationship' => $validated['emergency_contact_relationship'] ?? null,
        ]] : [];
    }
}
