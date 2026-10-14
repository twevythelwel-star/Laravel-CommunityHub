<?php

namespace App\Http\Requests\Safety;

use Illuminate\Foundation\Http\FormRequest;

class EscalateToEmergencyServicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageSecurity') ?? false;
    }

    public function rules(): array
    {
        return [
            'service' => ['required', 'in:police,medical,fire'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'confirmed' => config('safety.require_emergency_confirmation', true) ? ['accepted'] : ['nullable'],
        ];
    }

    public function messages(): array
    {
        return [
            'confirmed.accepted' => 'Confirm before escalating to emergency services.',
        ];
    }
}
