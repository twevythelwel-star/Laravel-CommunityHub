<?php

namespace App\Http\Requests\Access;

use Illuminate\Foundation\Http\FormRequest;

class AttachComplianceDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageSecurity') ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'document_type' => ['required', 'string', 'in:lease,authorization_letter,id_verification,insurance,contractor_certificate,other'],
            'holder_name' => ['required', 'string', 'max:255'],
            'expires_at' => ['required', 'date'],
            'gate_pass_id' => ['nullable', 'integer', 'exists:gate_passes,id'],
        ];
    }
}
