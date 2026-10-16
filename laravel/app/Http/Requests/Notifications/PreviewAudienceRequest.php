<?php

namespace App\Http\Requests\Notifications;

use App\Models\Community;
use App\Models\Notification as AnnouncementModel;
use Illuminate\Foundation\Http\FormRequest;

class PreviewAudienceRequest extends FormRequest
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

        return $user->can('create', [AnnouncementModel::class, $community]);
    }

    public function rules(): array
    {
        return [
            'target_type' => ['required', 'string', 'in:entire_community,street,block,section,custom_group,roles'],
            'target_criteria' => ['nullable', 'array'],
            'community_id' => ['nullable', 'integer', 'exists:communities,id'],
        ];
    }
}
