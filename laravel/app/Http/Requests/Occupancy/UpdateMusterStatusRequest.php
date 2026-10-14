<?php

namespace App\Http\Requests\Occupancy;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMusterStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        return $user->role->isAdministrative() || $user->role->isSecurity() || $user->role->isStaff();
    }

    public function rules(): array
    {
        return [
            'session_id' => ['required', 'integer', 'exists:muster_sessions,id'],
            'occupant_id' => ['required', 'string'],
            'status' => ['required', 'string', 'in:safe,missing,evacuated,needs_assistance,checked_out'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
