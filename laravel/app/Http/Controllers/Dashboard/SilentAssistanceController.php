<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Safety\EscalateToEmergencyServicesRequest;
use App\Http\Requests\Safety\EscalateToSecurityRequest;
use App\Http\Requests\Safety\ResolveDistressAlertRequest;
use App\Models\DistressAlert;
use App\Services\Safety\DistressAlertQueuePresenter;
use App\Services\Safety\SilentAssistanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Security's side of silent assistance: open requests, with the resident's
 * safety profile, to acknowledge and resolve. Behind `can:manageSecurity`.
 */
class SilentAssistanceController extends Controller
{
    public function __construct(private readonly SilentAssistanceService $safety) {}

    /** The request queue; see DistressAlertQueuePresenter for what each row holds. */
    public function index(DistressAlertQueuePresenter $presenter): Response
    {
        return Inertia::render('Dashboard/SilentAssistance', $presenter->props());
    }

    public function acknowledge(Request $request, DistressAlert $alert): RedirectResponse
    {
        $this->safety->acknowledge($alert, $request->user());

        return back()->with('success', 'Request acknowledged.');
    }

    public function resolve(ResolveDistressAlertRequest $request, DistressAlert $alert): RedirectResponse
    {
        $validated = $request->validated();

        $this->safety->resolve($alert, $request->user(), $validated['outcome'], $validated['notes']);

        return back()->with('success', 'Request closed.');
    }

    public function escalateToSecurity(EscalateToSecurityRequest $request, DistressAlert $alert): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $this->safety->escalateToSecurity($alert, $request->user(), $validated['notes'] ?? null);
        } catch (\DomainException $e) {
            throw ValidationException::withMessages(['escalation' => $e->getMessage()]);
        }

        return back()->with('success', 'Escalated to security level.');
    }

    public function escalateToEmergencyServices(EscalateToEmergencyServicesRequest $request, DistressAlert $alert): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $this->safety->escalateToEmergencyServices($alert, $request->user(), $validated['service'], $validated['notes'] ?? null);
        } catch (\DomainException $e) {
            throw ValidationException::withMessages(['escalation' => $e->getMessage()]);
        }

        return back()->with('success', 'Escalated to emergency services.');
    }
}
