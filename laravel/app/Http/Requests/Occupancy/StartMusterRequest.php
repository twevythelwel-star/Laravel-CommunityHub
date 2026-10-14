<?php

namespace App\Http\Requests\Occupancy;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartMusterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageOccupancy') ?? false;
    }

    public function rules(): array
    {
        return [
            'incident_type' => ['required', 'string', 'in:fire,hurricane,earthquake,flood,security_incident,drill,other'],
            'title' => ['required', 'string', 'max:160'],
            'assembly_point' => [Rule::requiredIf(blank(config('occupancy.assembly_point'))), 'nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
