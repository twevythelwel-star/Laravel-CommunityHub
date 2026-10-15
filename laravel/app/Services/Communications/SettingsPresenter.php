<?php

namespace App\Services\Communications;

use App\Models\BrandingSetting;
use App\Models\Community;
use App\Models\User;

/**
 * The props of the settings page (Dashboard/Settings): the user's own
 * preferences, with their defaults, and the estate's branding, which only
 * those who may manage users can change.
 */
class SettingsPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function props(User $user): array
    {
        $preferences = $user->preferences;
        $branding = BrandingSetting::query()->first();
        $communityName = Community::default()?->name ?? 'Cypress Bay';

        return [
            'preferences' => [
                'theme' => $preferences?->theme ?? 'system',
                'mapShowBoundary' => $preferences?->map_show_boundary ?? true,
                'mapShowLandmarks' => $preferences?->map_show_landmarks ?? true,
                'notifyEmail' => $preferences?->notify_email ?? true,
                'notifyPush' => $preferences?->notify_push ?? false,
                'notifySms' => $preferences?->notify_sms ?? false,
            ],
            'branding' => [
                'appName' => $branding?->app_name ?? config('app.name'),
                'communityName' => $communityName,
                'logoUrl' => $branding?->logo_url,
                'primaryColor' => $branding?->primary_color,
                'accentColor' => $branding?->accent_color,
                'backgroundColor' => $branding?->background_color,
                'defaultTheme' => $branding?->default_theme ?? 'system',
                'themeTokens' => [...($branding?->theme_tokens ?? []), 'communityName' => $communityName],
            ],
            'can' => [
                // Branding, theme and the community boundary are estate-wide.
                'brand' => $user->can('manageUsers'),
                'manageBoundary' => $user->can('manageBoundary'),
            ],
        ];
    }
}
