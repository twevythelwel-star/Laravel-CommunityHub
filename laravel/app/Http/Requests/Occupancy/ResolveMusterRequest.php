<?php

namespace App\Http\Requests\Occupancy;

use Illuminate\Foundation\Http\FormRequest;

class ResolveMusterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageOccupancy') ?? false;
    }

    public function rules(): array
    {
        return [
            'session_id' => ['required', 'integer', 'exists:muster_sessions,id'],
            'resolution_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
