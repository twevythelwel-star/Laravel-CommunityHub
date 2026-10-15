<?php

namespace App\Http\Requests\Safety;

use Illuminate\Foundation\Http\FormRequest;

class SetDuressPinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('keepSafetyProfile');
    }

    public function rules(): array
    {
        return [
            'pin' => ['required', 'digits:6', 'confirmed'],
        ];
    }
}
