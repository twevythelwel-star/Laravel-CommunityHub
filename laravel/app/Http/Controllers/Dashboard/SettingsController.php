<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateSettingsRequest;
use App\Models\BrandingSetting;
use App\Models\Community;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Backs src/app/dashboard/settings/page.tsx, theme-customizer.tsx and
 * branding-settings.tsx.
 *
 * Three separate localStorage stores are replaced here: per-user UI preferences
 * (`user_preferences`), estate-wide branding and the theme palette (both in
 * `branding_settings`). Only the first is personal; the other two are community
 * settings and need the `manageUsers` gate.
 */
class SettingsController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $preferences = $user->preferences;
        $branding = BrandingSetting::query()->first();
        $community = Community::default();

        return Inertia::render('Dashboard/Settings', [
            'preferences' => [
                'theme' => $preferences?->theme ?? 'system',
                'mapShowBoundary' => $preferences?->map_show_boundary ?? true,
                'mapShowLandmarks' => $preferences?->map_show_landmarks ?? true,
                // Previously three uncontrolled switches with no save handler.
                'notifyEmail' => $preferences?->notify_email ?? true,
                'notifyPush' => $preferences?->notify_push ?? false,
                'notifySms' => $preferences?->notify_sms ?? false,
            ],

            'branding' => [
                'appName' => $branding?->app_name ?? config('app.name'),
                'communityName' => $community?->name ?? 'Cypress Bay',
                'logoUrl' => $branding?->logo_url,
                'primaryColor' => $branding?->primary_color,
                'accentColor' => $branding?->accent_color,
                'backgroundColor' => $branding?->background_color,
                'defaultTheme' => $branding?->default_theme ?? 'system',
                'themeTokens' => array_merge(
                    $branding?->theme_tokens ?? [],
                    ['communityName' => $community?->name ?? 'Cypress Bay']
                ),
            ],

            'can' => [
                // Branding, theme and the community boundary are estate-wide.
                'brand' => $user->can('manageUsers'),
                'manageBoundary' => $user->can('manageBoundary'),
            ],
        ]);
    }

    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validated();

        $personal = array_intersect_key($validated, array_flip([
            'theme', 'map_show_boundary', 'map_show_landmarks',
            'notify_email', 'notify_push', 'notify_sms',
        ]));

        if ($personal !== []) {
            $user->preferences()->updateOrCreate(['user_id' => $user->id], $personal);
        }

        $branding = array_intersect_key($validated, array_flip([
            'app_name', 'logo_url', 'primary_color', 'accent_color',
            'background_color', 'default_theme', 'theme_tokens',
        ]));

        $communityName = $validated['community_name']
            ?? $validated['theme_tokens']['communityName']
            ?? null;

        if ($branding !== [] || $communityName !== null) {
            $this->authorize('manageUsers');

            if ($branding !== []) {
                BrandingSetting::current()->update($branding);
            }

            if ($communityName !== null && trim($communityName) !== '') {
                $cleanName = trim($communityName);
                Community::query()->update(['name' => $cleanName]);

                $currentBranding = BrandingSetting::current();
                $tokens = $currentBranding->theme_tokens ?? [];
                $tokens['communityName'] = $cleanName;
                $currentBranding->update(['theme_tokens' => $tokens]);
            }

            $user->recordActivity('Updated community branding and name');
        }

        if (isset($validated['password'])) {
            $user->update(['password' => $validated['password']]);
            $user->recordActivity('Changed password');
        }

        return back()->with('success', 'Settings saved.');
    }
}
