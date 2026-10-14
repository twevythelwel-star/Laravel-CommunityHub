<?php

namespace App\Http\Requests\Safety;

use Illuminate\Foundation\Http\FormRequest;

class ResidentPanicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('triggerEmergencyPanic') ?? false;
    }

    public function rules(): array
    {
        return [
            'emergency_type' => ['required', 'string', 'in:medical,security,fire,general'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
