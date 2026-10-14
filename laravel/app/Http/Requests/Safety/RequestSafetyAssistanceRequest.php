<?php

namespace App\Http\Requests\Safety;

use App\Models\DistressAlert;
use App\Services\Safety\SilentAssistanceService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RequestSafetyAssistanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        return $user->role->isResident()
            || $user->role->isAdministrative()
            || $user->activeAuthorizedAppointments()->exists();
    }

    public function rules(SilentAssistanceService $safety): array
    {
        $user = $this->user();
        $options = $user ? $safety->optionsForUser($user) : [];

        return [
            'assistance' => ['required', Rule::in($options)],
            'severity' => ['sometimes', Rule::in([
                DistressAlert::SEVERITY_ASSISTANCE,
                DistressAlert::SEVERITY_URGENT_ASSISTANCE,
                DistressAlert::SEVERITY_SILENT_EMERGENCY,
            ])],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
