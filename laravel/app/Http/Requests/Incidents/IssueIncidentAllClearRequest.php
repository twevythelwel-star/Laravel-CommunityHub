<?php

namespace App\Http\Requests\Incidents;

use Illuminate\Foundation\Http\FormRequest;

class IssueIncidentAllClearRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($incident = $this->route('incident')) && (bool) $this->user()?->can('issueAllClear', $incident);
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
