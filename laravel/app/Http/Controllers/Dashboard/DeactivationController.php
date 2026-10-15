<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\GatePass\DeactivateAccountAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\StoreDeactivationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Backs src/app/dashboard/deactivation/page.tsx, which previously wrote a flag
 * to localStorage — clearable by the user and invisible to administrators.
 * Deactivation now revokes the gate pass, ends the session, and is logged.
 */
class DeactivationController extends Controller
{
    public function show(Request $request): Response
    {
        return Inertia::render('Dashboard/Deactivation', [
            'user' => [
                'displayName' => $request->user()->display_name,
                'email' => $request->user()->email,
            ],
        ]);
    }

    public function store(StoreDeactivationRequest $request, DeactivateAccountAction $deactivate): SymfonyResponse
    {
        $user = $request->user();

        $deactivate->execute($user, $user, $request->validated('reason'));
        $user->recordActivity('Deactivated own account');

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->flash('success', 'Your account has been deactivated.');

        /*
         | The landing page is Blade, not Inertia. A plain redirect answering an
         | Inertia request would be rendered inside the SPA's error modal, so
         | ask the client for a full page visit instead — the same thing
         | AuthenticatedSessionController::destroy() does on sign-out.
         */
        return Inertia::location(route('landing'));
    }
}
