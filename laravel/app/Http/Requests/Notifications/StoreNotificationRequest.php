<?php

namespace App\Http\Requests\Notifications;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

class StoreNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('broadcastNotices') ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'content' => ['required', 'string', 'max:5000'],
            'target_roles' => ['nullable', 'array'],
            'target_roles.*' => ['string', 'in:'.implode(',', array_column(UserRole::cases(), 'value'))],
        ];
    }

    /**
     * The audience, or null for everyone.
     *
     * An empty array matches neither branch of Notification::forRole(), not
     * the whereNull nor the whereJsonContains, so a notice stored with []
     * would be invisible to everybody, including its author.
     *
     * @return list<string>|null
     */
    public function targetRoles(): ?array
    {
        $roles = array_values(array_unique($this->validated('target_roles') ?? []));

        return $roles === [] ? null : $roles;
    }
}
