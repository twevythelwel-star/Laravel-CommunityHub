<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Delegation\ActivateEmergencyRequest;
use App\Http\Requests\Delegation\ApproveDelegationRequest;
use App\Http\Requests\Delegation\DeactivateEmergencyRequest;
use App\Http\Requests\Delegation\ReissuePassRequest;
use App\Http\Requests\Delegation\RejectDelegationRequest;
use App\Http\Requests\Delegation\RevokeDelegationRequest;
use App\Http\Requests\Delegation\SaveContinuityPlanRequest;
use App\Http\Requests\Delegation\StoreDelegatedAccessRequest;
use App\Http\Requests\Delegation\UpdateDelegationRulesRequest;
use App\Models\DelegatedAccess;
use App\Services\Delegation\DelegatedAccessService;
use App\Services\GatePassEngine;
use App\Services\People\DelegatedAccessPresenter;
use App\Services\People\PropertyPeopleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DelegatedAccessController extends Controller
{
    public function __construct(
        protected GatePassEngine $engine,
        protected PropertyPeopleService $propertyPeopleService,
        protected DelegatedAccessPresenter $presenter
    ) {}

    /**
     * Display the Delegated Access Center ("Unit 14 — People & Access" or "Unit 14 — My Access Network").
     */
    public function index(Request $request): Response|JsonResponse
    {
        $data = $this->presenter->present($request);

        if ($request->wantsJson()) {
            return response()->json($data);
        }

        return Inertia::render('Dashboard/Delegation/Index', $data);
    }

    /**
     * Store a new emergency contact, occupant, or delegated access grant.
     */
    public function store(StoreDelegatedAccessRequest $request, DelegatedAccessService $service): RedirectResponse|JsonResponse
    {
        $delegation = $service->createDelegation($request->user(), $request->validated());

        return $this->reply($request, "Authorization created for {$delegation->name}.", fn () => [
            'message' => "Authorization successfully created for {$delegation->name}.",
            'delegation' => $delegation->load(['property', 'delegate', 'gatePasses']),
        ], 201);
    }

    /**
     * Update access rules and permissions for an authorized person.
     */
    public function updateRules(UpdateDelegationRulesRequest $request, DelegatedAccess $delegation, DelegatedAccessService $service): RedirectResponse|JsonResponse
    {
        $this->authorize('manage', $delegation);

        $service->updateRules($delegation, $request->validated(), $request->user());

        return $this->reply($request, "Access rules updated for {$delegation->name}.", fn () => [
            'delegation' => $delegation->fresh(['gatePasses']),
        ]);
    }

    /**
     * Reissue or rotate a fresh QR credential pass for an authorized person.
     */
    public function reissuePass(ReissuePassRequest $request, DelegatedAccess $delegation): RedirectResponse|JsonResponse
    {
        $this->authorize('manage', $delegation);

        $delegation->revokePasses($request->user());
        $pass = $delegation->issueDelegateGatePass($this->engine, $request->user());

        return $this->reply($request, "Fresh QR credential issued for {$delegation->name}.", fn () => [
            'message' => "Fresh QR credential ({$pass->pass_id}) issued for {$delegation->name}.",
            'pass' => $pass,
        ]);
    }

    /**
     * Activate emergency mode for an authorized delegate.
     */
    public function activateEmergency(ActivateEmergencyRequest $request, DelegatedAccess $delegation, DelegatedAccessService $service): RedirectResponse|JsonResponse
    {
        $this->authorize('manage', $delegation);

        $validated = $request->validated();
        $service->activateEmergencyAccess(
            delegation: $delegation,
            reason: $validated['reason'],
            actor: $request->user(),
            durationDays: $validated['duration_days'] ?? 7,
            notes: $validated['notes'] ?? null
        );

        return $this->reply($request, "Emergency access activated for {$delegation->name}.", fn () => [
            'delegation' => $delegation->fresh(['events', 'gatePasses']),
        ]);
    }

    /**
     * Deactivate emergency mode for an authorized delegate.
     */
    public function deactivateEmergency(DeactivateEmergencyRequest $request, DelegatedAccess $delegation, DelegatedAccessService $service): RedirectResponse|JsonResponse
    {
        $this->authorize('manage', $delegation);

        $service->deactivateEmergencyAccess($delegation, $request->user(), $request->validated('reason'));

        return $this->reply($request, "Emergency access deactivated for {$delegation->name}.", fn () => [
            'delegation' => $delegation->fresh(['events', 'gatePasses']),
        ]);
    }

    /**
     * Revoke a delegation permanently.
     */
    public function revoke(RevokeDelegationRequest $request, DelegatedAccess $delegation, DelegatedAccessService $service): RedirectResponse|JsonResponse
    {
        $this->authorize('manage', $delegation);

        $service->revokeDelegation($delegation, $request->user(), $request->validated('reason'));

        return $this->reply($request, "Delegation for {$delegation->name} has been revoked.", fn () => [
            'delegation' => $delegation->fresh(),
        ]);
    }

    /**
     * Configure the homeowner's Emergency & Incapacity Continuity Plan.
     */
    public function saveContinuityPlan(SaveContinuityPlanRequest $request, DelegatedAccessService $service): RedirectResponse|JsonResponse
    {
        $plan = $service->saveContinuityPlan($request->user(), $request->validated());

        return $this->reply($request, 'Emergency Continuity Plan updated.', fn () => [
            'message' => 'Emergency Continuity Plan saved successfully.',
            'plan' => $plan->fresh(['primaryDelegate', 'secondaryDelegate']),
        ]);
    }

    /**
     * Retrieve the audit trail events for a delegation.
     */
    public function auditEvents(DelegatedAccess $delegation): JsonResponse
    {
        $this->authorize('manage', $delegation);

        return response()->json([
            'delegation_id' => $delegation->id,
            'name' => $delegation->name,
            'events' => $delegation->events()->with('actor:id,name,email')->get(),
        ]);
    }

    /**
     * An administrator approves a delegation awaiting approval; only then is its pass issued.
     */
    public function approve(ApproveDelegationRequest $request, DelegatedAccess $delegation, DelegatedAccessService $service): RedirectResponse|JsonResponse
    {
        if ($delegation->approval_status !== 'pending') {
            abort(422, 'This delegation is not awaiting approval.');
        }

        $service->approveDelegation($delegation, $request->user());

        return $request->wantsJson()
            ? response()->json(['success' => true, 'delegation' => $delegation->fresh(['gatePasses'])])
            : back()->with('success', "Delegation for {$delegation->name} approved.");
    }

    /**
     * An administrator rejects a delegation awaiting approval.
     */
    public function reject(RejectDelegationRequest $request, DelegatedAccess $delegation, DelegatedAccessService $service): RedirectResponse|JsonResponse
    {
        if ($delegation->approval_status !== 'pending') {
            abort(422, 'This delegation is not awaiting approval.');
        }

        $validated = $request->validated();
        $service->rejectDelegation($delegation, $request->user(), $validated['reason'] ?? null);

        return $request->wantsJson()
            ? response()->json(['success' => true])
            : back()->with('success', "Delegation for {$delegation->name} rejected.");
    }

    /**
     * The invitee, signed in, accepts the invitation sent to their email.
     */
    public function accept(Request $request, string $token, DelegatedAccessService $service): RedirectResponse|JsonResponse
    {
        $delegation = DelegatedAccess::where('invite_token', $token)->first();
        if (! $delegation) {
            throw new \Illuminate\Auth\Access\AuthorizationException('This invitation is not for your account, or has already been used.');
        }

        $this->authorize('accept', $delegation);

        $delegation = $service->acceptInvite($token, $request->user());

        return $request->wantsJson()
            ? response()->json(['success' => true, 'delegation_id' => $delegation->id])
            : redirect()->route('dashboard.delegation')->with('success', "You are now linked to {$delegation->grantor?->name}'s authorization.");
    }

    /**
     * JSON for the access centre's API calls ({success, message, ...}), a
     * flash message for a form post. The JSON message defaults to the flash.
     *
     * @param  callable(): array<string, mixed>  $json
     */
    private function reply(Request $request, string $flash, callable $json, int $status = 200): RedirectResponse|JsonResponse
    {
        return $request->wantsJson()
            ? response()->json(['success' => true, 'message' => $flash, ...$json()], $status)
            : back()->with('success', $flash);
    }
}
