<?php

namespace App\Http\Requests\Incidents;

use Illuminate\Foundation\Http\FormRequest;

class IssueIncidentAllClearRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageSecurity') ?? false;
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:1000'],
            'broadcast_all_clear' => ['nullable', 'boolean'],
            'broadcast_channels' => ['nullable', 'array'],
            'broadcast_channels.*' => ['string', 'in:in_app,push,sms,whatsapp,email'],
        ];
    }
}
