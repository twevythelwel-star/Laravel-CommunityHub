<?php

namespace App\Http\Requests\Vehicles;

use App\Models\HouseholdMember;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'license_plate' => ['sometimes', 'string', 'max:32'],
            'jurisdiction' => ['nullable', 'string', 'max:64'],
            'make' => ['sometimes', 'string', 'max:64'],
            'model' => ['sometimes', 'string', 'max:64'],
            'color' => ['sometimes', 'string', 'max:48'],
            'year' => ['nullable', 'integer', 'min:1950', 'max:2030'],
            'parking_location' => ['nullable', 'string', 'max:128'],
            'household_member_id' => ['nullable', Rule::exists('occupants', 'id')->where('type', HouseholdMember::TYPE)],
            'is_ev' => ['nullable', 'boolean'],
            'is_temporary' => ['nullable', 'boolean'],
            'valid_until' => ['nullable', 'date'],
            'anpr_enabled' => ['nullable', 'boolean'],
            'status' => ['nullable', 'string', 'in:active,inactive,suspended,revoked'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
