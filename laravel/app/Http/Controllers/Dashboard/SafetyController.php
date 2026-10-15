<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\Safety\UpdateSafetyProfileAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Safety\HoldSafetyQrRequest;
use App\Http\Requests\Safety\RequestAccountCredentialAssistanceRequest;
use App\Http\Requests\Safety\RequestCredentialAssistanceRequest;
use App\Http\Requests\Safety\RequestSafetyAssistanceRequest;
use App\Http\Requests\Safety\SetDuressPinRequest;
use App\Http\Requests\Safety\UpdateSafetyProfileRequest;
use App\Models\DistressAlert;
use App\Models\User;
use App\Services\Safety\SafetyPagePresenter;
use App\Services\Safety\SilentAssistanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A resident's side of silent assistance: their safety profile, the duress
 * PIN, and the two ways of asking for help from the app.
 *
 * Every request answers the same plain way whatever it does, so nothing on
 * the screen tells someone nearby that help was called.
 */
class SafetyController extends Controller
{
    public function __construct(private readonly SilentAssistanceService $safety) {}

    /** The user's safety page; see SafetyPagePresenter for what each role sees. */
    public function show(Request $request, SafetyPagePresenter $presenter): Response
    {
        return Inertia::render('Dashboard/Safety', $presenter->props($this->resident($request)));
    }

    public function updateProfile(UpdateSafetyProfileRequest $request, UpdateSafetyProfileAction $update): RedirectResponse
    {
        $user = $this->requester($request);
        abort_unless($user->role->isResident() || $user->role->isAdministrative(), 403);

        $update->execute($user, $request->validated());

        return back()->with('success', 'Safety profile saved.');
    }

    public function setDuressPin(SetDuressPinRequest $request): RedirectResponse
    {
        $user = $this->resident($request);
        abort_unless($this->mayUseResidentTriggers($user), 403);

        $validated = $request->validated();

        try {
            $this->safety->setDuressPin($user, $validated['pin']);
        } catch (\DomainException $e) {
            throw ValidationException::withMessages(['pin' => $e->getMessage()]);
        }

        return back()->with('success', 'Duress PIN set.');
    }

    public function clearDuressPin(Request $request): RedirectResponse
    {
        $user = $this->resident($request);
        abort_unless($this->mayUseResidentTriggers($user), 403);
        $this->safety->clearDuressPin($user);

        return back()->with('success', 'Duress PIN removed.');
    }

    /**
     * "Request assistance?" from the app. The answer is a plain
     * acknowledgement whichever kind of help was asked for.
     */
    public function requestAssistance(RequestSafetyAssistanceRequest $request): JsonResponse
    {
        $user = $this->resident($request);

        $validated = $request->validated();

        abort_unless(in_array($validated['assistance'], $this->safety->optionsForUser($user), true), 422);

        $severity = $validated['severity'] ?? DistressAlert::SEVERITY_ASSISTANCE;
        if ($user->role->isResident() || $user->role->isAdministrative()) {
            abort_unless($this->safety->raise($user, 'app', $validated['assistance'], [...$validated, 'severity_level' => $severity]), 422);
        } else {
            $pass = $user->gatePasses()->whereNull('visitor_id')->whereNull('delegated_access_id')->active()->latest('id')->first();
            abort_unless($pass && $user->passCategory() === $pass->category && in_array($pass->category->value, ['STAFF', 'SECURITY', 'HOMEOWNER_STAFF'], true), 404);
            abort_unless($pass && $pass->isActive() && $pass->isWithinValidity(now()), 404);
            $context = $this->safety->guestContextForPass($pass);
            abort_unless($context, 404);
            abort_unless($this->safety->raiseForAccountCredential($user, $pass, $validated['assistance'], [...$validated, 'severity_level' => $severity]), 422);
        }

        return response()->json(['message' => 'Request sent.']);
    }

    public function credentialSafety(Request $request): Response
    {
        $user = $this->requester($request);
        abort_unless($user->role->isOperational(), 403);
        $pass = $user->gatePasses()->whereNull('visitor_id')->whereNull('delegated_access_id')->active()->latest('id')->first();
        abort_unless($pass && $user->passCategory() === $pass->category && in_array($pass->category->value, ['STAFF', 'SECURITY', 'HOMEOWNER_STAFF'], true), 404);
        abort_unless($pass && $pass->isActive() && $pass->isWithinValidity(now()), 404);
        $context = $this->safety->accessContextForPass($pass);
        abort_unless($context, 404);

        return Inertia::render('Dashboard/CredentialSafety', [
            'credential' => 'account',
            'holderLabel' => $context['label'],
            'assistanceOptions' => $context['assistanceOptions'],
            'emergencyNumber' => config('safety.public_emergency_number'),
            'accountHolder' => true,
        ]);
    }

    public function requestAccountCredentialAssistance(RequestAccountCredentialAssistanceRequest $request): JsonResponse
    {
        $user = $this->requester($request);
        abort_unless($user->role->isOperational(), 403);
        $pass = $user->gatePasses()->whereNull('visitor_id')->whereNull('delegated_access_id')->active()->latest('id')->first();
        abort_unless($pass && $user->passCategory() === $pass->category && in_array($pass->category->value, ['STAFF', 'SECURITY', 'HOMEOWNER_STAFF'], true), 404);
        abort_unless($pass && $pass->isActive() && $pass->isWithinValidity(now()), 404);
        $context = $this->safety->guestContextForPass($pass);
        abort_unless($context, 404);
        $validated = $request->validated();

        abort_unless(in_array($validated['assistance'], $context['assistanceOptions'], true), 422);

        abort_unless($this->safety->raiseForAccountCredential($user, $pass, $validated['assistance'], $validated), 422);

        return response()->json(['message' => 'Request received.']);
    }

    /**
     * The discreet QR gesture. No content back, success or not: the screen
     * the resident is holding up must not change.
     */
    public function hold(HoldSafetyQrRequest $request): HttpResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->noContent();
        }

        if ($user && $this->mayUseResidentTriggers($user)) {
            $validated = $request->validated();

            $this->safety->arm($user, $validated);
        }

        return response()->noContent();
    }

    private function resident(Request $request): User
    {
        $user = $request->user();
        abort_unless($this->mayUseResidentTriggers($user) || $user->activeAuthorizedAppointments()->exists(), 403);

        return $user;
    }

    private function requester(Request $request): User
    {
        $user = $request->user();
        abort_unless($this->mayUse($user), 403);

        return $user;
    }

    public function showCredential(Request $request, string $token): Response
    {
        $pass = $this->safety->passForVisitorToken($token);
        abort_unless($pass, 404);
        $context = $this->safety->accessContextForPass($pass);
        abort_unless($context, 404);

        abort_if($context['accountHolder'], 404);

        return Inertia::render('Dashboard/CredentialSafety', [
            'credential' => $token,
            'holderLabel' => $context['label'],
            'assistanceOptions' => $context['assistanceOptions'],
            'emergencyNumber' => config('safety.public_emergency_number'),
        ]);
    }

    public function requestCredentialAssistance(RequestCredentialAssistanceRequest $request, string $token): JsonResponse
    {
        $pass = $this->safety->passForVisitorToken($token);
        abort_unless($pass, 404);
        $context = $this->safety->accessContextForPass($pass);
        abort_unless($context, 404);

        $validated = $request->validated();

        abort_if($context['accountHolder'], 404);
        abort_unless($this->safety->raiseForGuest($pass, $context, $validated['assistance'], $validated), 422);

        return response()->json(['message' => 'Request received.']);
    }

    /** Residents, staff and administrators may use account-based assistance. */
    private function mayUse(User $user): bool
    {
        return $user->role->isResident() || $user->role->isAdministrative() || $user->role->isOperational();
    }

    private function mayUseResidentTriggers(User $user): bool
    {
        return $user->role->isResident() || $user->role->isAdministrative();
    }
}
