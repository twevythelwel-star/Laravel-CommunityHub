<?php

namespace App\Http\Requests\Notifications;

use Illuminate\Foundation\Http\FormRequest;

class MarkInAppNotificationReadRequest extends FormRequest
{
    public function authorize(): bool
    {
        $notification = $this->route('notification');

        // Only the owner may mark their notification as read
        return $notification === null || (int) $notification->user_id === (int) $this->user()?->id;
    }

    public function rules(): array
    {
        return [];
    }
}
