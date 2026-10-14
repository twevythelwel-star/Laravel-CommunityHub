<?php

namespace App\Http\Requests\Access;

use Illuminate\Foundation\Http\FormRequest;

class ReportLostCredentialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'pass_id' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
