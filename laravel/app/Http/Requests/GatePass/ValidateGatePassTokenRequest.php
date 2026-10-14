<?php

namespace App\Http\Requests\GatePass;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidateGatePassTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:4096'],
            'gate' => ['nullable', 'string', Rule::in(['GATE-01', 'GATE-02'])],
        ];
    }
}
