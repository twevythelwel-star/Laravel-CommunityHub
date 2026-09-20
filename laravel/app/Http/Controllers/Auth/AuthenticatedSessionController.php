<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\BrandingSetting;
use App\Services\GatePassEngine;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Replaces the mock login in src/context/auth-context.tsx, which matched a
 * username against a hardcoded object and accepted any password. Credentials
 * are now verified against hashed passwords, rate-limited, and the session is
 * regenerated to prevent fixation.
 */
class AuthenticatedSessionController extends Controller
{
    /** The sign-in form is the same Blade view served at "/". */
    public function create(): View
    {
        return view('blade.landing', [
            'branding' => BrandingSetting::current(),
        ]);
    }

    public function store(Request $request, GatePassEngine $engine): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'Those credentials do not match our records.',
            ]);
        }

        $user = Auth::user();

        if (! $user->isActive()) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'This account has been deactivated. Contact community administration.',
            ]);
        }

        $request->session()->regenerate();

        // Every active account carries a pass; issue one on first sign-in.
        $engine->issuePassFor($user);

        $user->recordActivity('Signed in');

        return redirect()->intended(route('dashboard.index'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->user()?->recordActivity('Signed out');

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('landing');
    }
}
