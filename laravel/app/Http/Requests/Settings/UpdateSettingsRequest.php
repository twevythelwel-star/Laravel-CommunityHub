<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        $brandingKeys = [
            'app_name', 'logo_url', 'primary_color', 'accent_color',
            'background_color', 'default_theme', 'theme_tokens',
        ];

        $hasBranding = false;
        foreach ($brandingKeys as $k) {
            if ($this->has($k)) {
                $hasBranding = true;
                break;
            }
        }

        $hasCommunityName = $this->has('community_name') || $this->input('theme_tokens.communityName') !== null;

        if ($hasBranding || $hasCommunityName) {
            return $user->can('manageUsers');
        }

        return true;
    }

    public function rules(): array
    {
        return [
            // ── Personal preferences ──
            'theme' => ['sometimes', 'in:light,dark,system'],
            'map_show_boundary' => ['sometimes', 'boolean'],
            'map_show_landmarks' => ['sometimes', 'boolean'],
            'notify_email' => ['sometimes', 'boolean'],
            'notify_push' => ['sometimes', 'boolean'],
            'notify_sms' => ['sometimes', 'boolean'],

            // ── Estate-wide branding & community name, admin only ──
            'app_name' => ['sometimes', 'string', 'max:80'],
            'community_name' => ['sometimes', 'string', 'max:120'],
            'logo_url' => ['nullable', 'string', 'max:2048'],
            'primary_color' => ['nullable', 'string', 'max:32'],
            'accent_color' => ['nullable', 'string', 'max:32'],
            'background_color' => ['nullable', 'string', 'max:32'],
            'default_theme' => ['sometimes', 'in:light,dark,system'],
            'theme_tokens' => ['sometimes', 'array'],
            'theme_tokens.*' => ['nullable'],

            // ── Password change ──
            'current_password' => ['sometimes', 'required_with:password', 'current_password'],
            'password' => ['sometimes', 'confirmed', Password::defaults()],
        ];
    }
}
