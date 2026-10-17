<?php

namespace App\Http\Requests\Incidents;

use App\Models\CommunityIncident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', CommunityIncident::class);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'category' => [
                'required',
                'string',
                Rule::in([
                    CommunityIncident::CATEGORY_SECURITY,
                    CommunityIncident::CATEGORY_FIRE,
                    CommunityIncident::CATEGORY_MEDICAL,
                    CommunityIncident::CATEGORY_UTILITY_OUTAGE,
                    CommunityIncident::CATEGORY_SEVERE_WEATHER,
                    CommunityIncident::CATEGORY_INFRASTRUCTURE,
                    CommunityIncident::CATEGORY_GENERAL,
                ]),
            ],
            'severity' => [
                'required',
                'string',
                Rule::in([
                    CommunityIncident::SEVERITY_LOW,
                    CommunityIncident::SEVERITY_MEDIUM,
                    CommunityIncident::SEVERITY_HIGH,
                    CommunityIncident::SEVERITY_CRITICAL,
                ]),
            ],
            'location' => ['required', 'string', 'max:255'],
            'summary' => ['required', 'string', 'max:2000'],
            'sensitive_details' => ['nullable', 'string', 'max:5000'],
            'broadcast_urgent_notice' => ['nullable', 'boolean'],
            'broadcast_channels' => ['nullable', 'array'],
            'broadcast_channels.*' => ['string', 'in:in_app,push,sms,whatsapp,email'],
        ];
    }
}
