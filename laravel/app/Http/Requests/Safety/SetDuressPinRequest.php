<?php

namespace App\Http\Requests\Safety;

use Illuminate\Foundation\Http\FormRequest;

class SetDuressPinRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        return $user->role->isResident() || $user->role->isAdministrative();
    }

    public function rules(): array
    {
        return [
            'pin' => ['required', 'digits:6', 'confirmed'],
        ];
    }
}
