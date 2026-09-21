<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Deactivated accounts are logged out on their next request rather than being
 * allowed to keep an existing session alive. The original app only hid the UI.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->isActive()) {
            $expiredMessage = $user->temporaryStayExpirationMessage();
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors(['email' => $expiredMessage ?: 'This account has been deactivated. Contact community administration.']);
        }

        return $next($request);
    }
}
