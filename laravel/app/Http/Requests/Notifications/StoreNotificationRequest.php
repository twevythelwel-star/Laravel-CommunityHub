<?php

namespace App\Http\Requests\Notifications;

use App\Enums\UserRole;
use App\Models\Community;
use App\Models\Notification;
use Illuminate\Foundation\Http\FormRequest;

class StoreNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        $communityId = $this->input('community_id');
        $community = $communityId ? Community::find($communityId) : null;

        return $user->can('create', [Notification::class, $community]);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'content' => ['required', 'string', 'max:5000'],
            'community_id' => ['nullable', 'integer', 'exists:communities,id'],
            'target_type' => ['nullable', 'string', 'in:entire_community,street,block,section,custom_group,roles'],
            'target_criteria' => ['nullable', 'array'],
            'target_roles' => ['nullable', 'array'],
            'target_roles.*' => ['string', 'in:'.implode(',', array_column(UserRole::cases(), 'value'))],
            'category' => ['nullable', 'string', 'in:general,maintenance,security,emergency,event,financial'],
            'priority' => ['nullable', 'string', 'in:low,normal,urgent,critical'],
            'channels' => ['nullable', 'array'],
            'channels.*' => ['string', 'in:in_app,email,sms,whatsapp'],
            'scheduled_for' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $user = $this->user();
            $category = $this->input('category');
            $priority = $this->input('priority');

            if ($category === 'emergency' || $priority === 'critical') {
                if (! in_array($user?->role, [UserRole::SystemAdmin, UserRole::Admin], true)) {
                    $validator->errors()->add('priority', 'Emergency category and critical priority are reserved exclusively for authorized administrators.');
                }
            }
        });
    }

    public function targetRoles(): ?array
    {
        $roles = array_values(array_unique($this->validated('target_roles') ?? []));

        return $roles === [] ? null : $roles;
    }

    public function channels(): array
    {
        $channels = $this->validated('channels') ?? ['in_app'];

        if (! in_array('in_app', $channels, true)) {
            $channels[] = 'in_app';
        }

        return array_values(array_unique($channels));
    }
}
