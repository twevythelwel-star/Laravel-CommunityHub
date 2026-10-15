<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * One form for three kinds of setting: the user's own preferences, the
 * estate's branding and name (manageUsers only), and the user's password.
 */
class UpdateSettingsRequest extends FormRequest
{
    private const PERSONAL = [
        'theme', 'map_show_boundary', 'map_show_landmarks',
        'notify_email', 'notify_push', 'notify_sms',
    ];

    private const BRANDING = [
        'app_name', 'logo_url', 'primary_color', 'accent_color',
        'background_color', 'default_theme', 'theme_tokens',
    ];

    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        $changesEstate = $this->hasAny(self::BRANDING)
            || $this->has('community_name')
            || $this->input('theme_tokens.communityName') !== null;

        return ! $changesEstate || $user->can('manageUsers');
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

    /** @return array<string, mixed> The user's own preferences in the request. */
    public function personalPreferences(): array
    {
        return array_intersect_key($this->validated(), array_flip(self::PERSONAL));
    }

    /** @return array<string, mixed> The estate branding in the request. */
    public function branding(): array
    {
        return array_intersect_key($this->validated(), array_flip(self::BRANDING));
    }

    /** The community's new name, given directly or as the communityName theme token. */
    public function communityName(): ?string
    {
        return $this->validated('community_name') ?? $this->validated('theme_tokens.communityName');
    }
}
