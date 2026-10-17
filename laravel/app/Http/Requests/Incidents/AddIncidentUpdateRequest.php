<?php

namespace App\Http\Requests\Incidents;

use App\Models\CommunityIncident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddIncidentUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageSecurity') ?? false;
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:2000'],
            'status' => [
                'nullable',
                'string',
                Rule::in([
                    CommunityIncident::STATUS_REPORTED,
                    CommunityIncident::STATUS_INVESTIGATING,
                    CommunityIncident::STATUS_ACTIVE,
                    CommunityIncident::STATUS_CONTAINED,
                    CommunityIncident::STATUS_ALL_CLEAR,
                    CommunityIncident::STATUS_CLOSED,
                ]),
            ],
            'is_internal' => ['nullable', 'boolean'],
            'broadcast_update' => ['nullable', 'boolean'],
            'broadcast_channels' => ['nullable', 'array'],
            'broadcast_channels.*' => ['string', 'in:in_app,push,sms,whatsapp,email'],
        ];
    }
}
