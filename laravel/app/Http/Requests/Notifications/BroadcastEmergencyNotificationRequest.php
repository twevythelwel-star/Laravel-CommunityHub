<?php

namespace App\Http\Requests\Notifications;

use Illuminate\Foundation\Http\FormRequest;

class BroadcastEmergencyNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && ($user->can('broadcastNotices') || $user->can('manageSecurity'));
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'content' => ['required', 'string', 'max:1000'],
            'severity' => ['required', 'string', 'in:info,advisory,emergency'],
            'audience' => ['required', 'string', 'in:all,building,property,residents,staff,security,legacy_contacts,long_term_guests,visitors'],
            'target_building' => ['nullable', 'required_if:audience,building', 'string', 'max:100'],
            'target_property' => ['nullable', 'required_if:audience,property', 'string', 'max:100'],
            'send_push' => ['nullable', 'boolean'],
            'send_sms' => ['nullable', 'boolean'],
            'send_whatsapp' => ['nullable', 'boolean'],
            'send_email' => ['nullable', 'boolean'],
            'send_in_app' => ['nullable', 'boolean'],
        ];
    }
}
