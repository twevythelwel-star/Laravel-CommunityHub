<?php

namespace App\Http\Requests\Safety;

use App\Enums\GateId;
use App\Services\Safety\SilentAssistanceService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSafetyProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        return $user->role->isResident() || $user->role->isAdministrative() || $user->role->isOperational();
    }

    protected function prepareForValidation(): void
    {
        $incomingContacts = $this->input('emergency_contacts');
        if (is_array($incomingContacts)) {
            $this->merge(['emergency_contacts' => collect($incomingContacts)
                ->filter(fn ($contact) => is_array($contact) && (filled($contact['name'] ?? null) || filled($contact['phone'] ?? null)))
                ->values()
                ->all()]);
        }
    }

    public function rules(SilentAssistanceService $safety): array
    {
        $user = $this->user();
        $allowedOptions = $user ? $safety->optionsForUser($user) : [];

        return [
            'emergency_contact_name' => ['nullable', 'string', 'max:120'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:40'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:60'],
            'preferred_assistance' => ['sometimes', 'required', Rule::in($allowedOptions)],
            'assistance_preferences' => ['sometimes', 'array', 'min:1', 'max:5'],
            'assistance_preferences.*' => ['required', Rule::in($allowedOptions)],
            'emergency_contacts' => ['sometimes', 'array', 'max:5'],
            'emergency_contacts.*.name' => ['required', 'string', 'max:120'],
            'emergency_contacts.*.phone' => ['required', 'string', 'max:40'],
            'emergency_contacts.*.relationship' => ['nullable', 'string', 'max:60'],
            'preferred_gate' => ['nullable', Rule::in(array_map(fn (GateId $gate) => $gate->value, array_filter(GateId::cases(), fn (GateId $gate) => $gate !== GateId::Any)))],
            'preferred_vehicle_id' => ['nullable', 'integer', Rule::exists('vehicles', 'id')->where('user_id', $user?->id)],
            'medical_notes' => ['nullable', 'string', 'max:2000'],
            'silent_alerts_enabled' => ['required', 'boolean'],
        ];
    }
}
