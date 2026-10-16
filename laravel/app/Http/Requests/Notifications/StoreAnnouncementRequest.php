<?php

namespace App\Http\Requests\Notifications;

use App\Enums\UserRole;
use App\Models\Community;
use App\Models\Notification as AnnouncementModel;
use Illuminate\Foundation\Http\FormRequest;

class StoreAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        $communityId = $this->input('community_id');
        $community = $communityId
            ? Community::find($communityId)
            : Community::query()->where('code', config('gatepass.default_community_id'))->first();

        // Enforce server-side authority check and multi-community boundary isolation
        return $user->can('create', [AnnouncementModel::class, $community]);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'content' => ['required', 'string', 'max:5000'],
            'community_id' => ['nullable', 'integer', 'exists:communities,id'],
            'target_type' => ['required', 'string', 'in:entire_community,street,block,section,custom_group,roles'],
            'target_criteria' => ['nullable', 'array'],
            'target_criteria.street' => ['nullable', 'string', 'max:100'],
            'target_criteria.block' => ['nullable', 'string', 'max:50'],
            'target_criteria.section' => ['nullable', 'string', 'max:50'],
            'target_criteria.user_ids' => ['nullable', 'array'],
            'target_criteria.user_ids.*' => ['integer', 'exists:users,id'],
            'target_criteria.streets' => ['nullable', 'array'],
            'target_criteria.streets.*' => ['string', 'max:100'],
            'target_criteria.blocks' => ['nullable', 'array'],
            'target_criteria.blocks.*' => ['string', 'max:50'],
            'target_roles' => ['nullable', 'array'],
            'target_roles.*' => ['string', 'in:'.implode(',', array_column(UserRole::cases(), 'value'))],
            'channels' => ['nullable', 'array'],
            'channels.*' => ['string', 'in:in_app,email,sms,whatsapp'],
            'scheduled_for' => ['nullable', 'date', 'after:now'],
        ];
    }

    public function validatedChannels(): array
    {
        $channels = $this->validated('channels') ?? ['in_app'];

        // In-app is always the baseline
        if (! in_array('in_app', $channels, true)) {
            $channels[] = 'in_app';
        }

        return array_values(array_unique($channels));
    }
}
