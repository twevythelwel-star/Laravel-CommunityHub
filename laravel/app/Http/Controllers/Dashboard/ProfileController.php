<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\GatePassEngine;
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
    public function edit(Request $request, GatePassEngine $engine): Response
    {
        $user = $request->user();
        $pass = $engine->issuePassFor($user);
        $category = $pass->category;

        return Inertia::render('Dashboard/Profile', [
            'profile' => [
                'uid' => $user->uid,
                'name' => $user->name,
                'displayName' => $user->display_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role->value,
                'title' => $user->title,
                'lot' => $user->lot,
                'street' => $user->street,
                'avatarUrl' => $user->avatar_url,
                'aiConsent' => $user->ai_consent,
                'property' => $user->propertyLabel(),
            ],
            'passVisual' => [
                'passId' => $pass->pass_id,
                'config' => $engine->categoryConfig($category),
                'variant' => $engine->assignedColorVariant($category, $pass->pass_id, $pass->rotation_seq),
                'shape' => $category->shape()->value,
            ],
            'activity' => $user->activityLog()->limit(20)->get()->map(fn ($a) => [
                'id' => $a->id,
                'action' => $a->action,
                'timestamp' => $a->occurred_at->toIso8601String(),
            ]),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'display_name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
        ]);

        $request->user()->update($validated);

        return back()->with('success', 'Profile updated.');
    }

    public function updateAvatar(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $path = $validated['avatar']->store('avatars', 'public');

        $request->user()->update(['avatar_url' => '/storage/'.$path]);

        return back()->with('success', 'Avatar updated.');
    }

    /** Persists the AI consent decision previously kept only in localStorage. */
    public function setAiConsent(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'consent' => ['required', 'boolean'],
        ]);

        $request->user()->update(['ai_consent' => $validated['consent']]);

        return back();
    }
}
