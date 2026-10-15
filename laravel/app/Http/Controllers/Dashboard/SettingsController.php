<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\Communications\UpdateCommunityBrandingAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateSettingsRequest;
use App\Services\Communications\SettingsPresenter;
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
 * settings and need the `manageUsers` gate, which UpdateSettingsRequest checks
 * whenever a request touches them.
 */
class SettingsController extends Controller
{
    public function index(Request $request, SettingsPresenter $presenter): Response
    {
        return Inertia::render('Dashboard/Settings', $presenter->props($request->user()));
    }

    public function update(UpdateSettingsRequest $request, UpdateCommunityBrandingAction $updateBranding): RedirectResponse
    {
        $user = $request->user();

        if ($personal = $request->personalPreferences()) {
            $user->preferences()->updateOrCreate(['user_id' => $user->id], $personal);
        }

        $branding = $request->branding();
        $communityName = $request->communityName();
        if ($branding !== [] || $communityName !== null) {
            $updateBranding->execute($user, $branding, $communityName);
        }

        if ($password = $request->validated('password')) {
            $user->update(['password' => $password]);
            $user->recordActivity('Changed password');
        }

        return back()->with('success', 'Settings saved.');
    }
}
