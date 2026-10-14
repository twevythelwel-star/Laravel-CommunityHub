<?php

namespace App\Http\Requests\Occupancy;

use Illuminate\Foundation\Http\FormRequest;

class ResolveMusterRequest extends FormRequest
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
            'resolution_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
