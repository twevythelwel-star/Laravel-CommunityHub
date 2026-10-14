<?php

namespace App\Http\Requests\GatePass;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmGatePassScanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'accept' => ['required', 'boolean'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
