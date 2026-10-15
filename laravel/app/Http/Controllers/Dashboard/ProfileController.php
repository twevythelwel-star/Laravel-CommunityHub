<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\UpdatePaymentPreferencesRequest;
use App\Http\Requests\Profile\SetAiConsentRequest;
use App\Http\Requests\Profile\UpdateAvatarRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Requests\Profile\UpdateThemePresetRequest;
use App\Models\BrandingSetting;
use App\Services\Billing\PaymentPreferences;
use App\Services\People\ProfilePagePresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Backs src/app/dashboard/profile/page.tsx and ProfileCustomQRCode.tsx.
 *
 * `name` is the legal name and stays read-only, matching the original contract
 * in auth-context.tsx where only displayName was user-editable.
 */
class ProfileController extends Controller
{
    /** The profile page; see ProfilePagePresenter for what each panel holds. */
    public function edit(Request $request, ProfilePagePresenter $presenter): Response
    {
        return Inertia::render('Dashboard/Profile', $presenter->props($request->user()));
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $request->user()->update($validated);

        return back()->with('success', 'Profile updated.');
    }

    public function updateAvatar(UpdateAvatarRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $path = $validated['avatar']->store('avatars', 'public');

        $request->user()->update(['avatar_url' => '/storage/'.$path]);

        return back()->with('success', 'Avatar updated.');
    }

    /** Persists the AI consent decision previously kept only in localStorage. */
    public function setAiConsent(SetAiConsentRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $request->user()->update(['ai_consent' => $validated['consent']]);

        return back();
    }

    /** Updates payment preferences and notification channels; see PaymentPreferences. */
    public function updatePaymentPreferences(UpdatePaymentPreferencesRequest $request, PaymentPreferences $preferences): RedirectResponse
    {
        $preferences->save($request->user(), $request->validated());

        return back()->with('success', 'Payment preferences updated successfully.');
    }

    /**
     * Allows any authenticated user (Homeowner, Resident, Security, Staff, Admin)
     * to personalize the application theme preset for their individual profile.
     */
    public function updateThemePreset(UpdateThemePresetRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $preset = BrandingSetting::isValidPreset($validated['theme_preset'] ?? null) ? $validated['theme_preset'] : null;

        $user = $request->user();
        // Keep the loaded relation current: the shared Inertia props read it.
        $user->setRelation('preferences', $user->preferences()->updateOrCreate([], ['theme_preset' => $preset]));

        return back()->with('success', $preset ? 'Personal theme preset updated.' : 'Theme reset to community default.');
    }
}
