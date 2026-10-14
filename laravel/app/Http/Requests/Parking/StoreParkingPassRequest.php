<?php

namespace App\Http\Requests\Parking;

use Illuminate\Foundation\Http\FormRequest;

class StoreParkingPassRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        return $user->role->isResident() || $user->can('manageSecurity');
    }

    public function rules(): array
    {
        return [
            'category' => ['required', 'string', 'in:resident,visitor,contractor,temporary,accessible,loading_zone'],
            'license_plate' => ['required', 'string', 'max:32'],
            'vehicle_id' => ['nullable', 'exists:vehicles,id'],
            'property' => ['nullable', 'string', 'max:64'],
            'assigned_bay' => ['nullable', 'string', 'max:64'],
            'holder_name' => ['required', 'string', 'max:128'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date'],
            'max_duration_minutes' => ['nullable', 'integer'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
