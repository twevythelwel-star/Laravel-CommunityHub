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
use App\Models\GatePass;
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
 * Who may use what is decided by the keepSafetyProfile,
 * requestSafetyAssistance and useStaffSafetyCredential gates, on the routes
 * and form requests.
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
        return Inertia::render('Dashboard/Safety', $presenter->props($request->user()));
    }

    public function updateProfile(UpdateSafetyProfileRequest $request, UpdateSafetyProfileAction $update): RedirectResponse
    {
        $update->execute($request->user(), $request->validated());

        return back()->with('success', 'Safety profile saved.');
    }

    public function setDuressPin(SetDuressPinRequest $request): RedirectResponse
    {
        try {
            $this->safety->setDuressPin($request->user(), $request->validated('pin'));
        } catch (\DomainException $e) {
            throw ValidationException::withMessages(['pin' => $e->getMessage()]);
        }

        return back()->with('success', 'Duress PIN set.');
    }

    public function clearDuressPin(Request $request): RedirectResponse
    {
        $this->safety->clearDuressPin($request->user());

        return back()->with('success', 'Duress PIN removed.');
    }

    /**
     * "Request assistance?" from the app. The answer is a plain
     * acknowledgement whichever kind of help was asked for.
     *
     * Someone acting under an appointment asks through their own staff
     * credential, as on the credential safety page.
     */
    public function requestAssistance(RequestSafetyAssistanceRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $assistance = $validated['assistance'];
        abort_unless(in_array($assistance, $this->safety->optionsForUser($user), true), 422);

        $context = [...$validated, 'severity_level' => $validated['severity'] ?? DistressAlert::SEVERITY_ASSISTANCE];

        if ($user->can('keepSafetyProfile')) {
            abort_unless($this->safety->raise($user, 'app', $assistance, $context), 422);
        } else {
            $pass = $this->staffPass($user);
            abort_unless($this->safety->guestContextForPass($pass), 404);
            abort_unless($this->safety->raiseForAccountCredential($user, $pass, $assistance, $context), 422);
        }

        return response()->json(['message' => 'Request sent.']);
    }

    public function credentialSafety(Request $request): Response
    {
        $context = $this->safety->accessContextForPass($this->staffPass($request->user()));
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
        $user = $request->user();
        $pass = $this->staffPass($user);
        $context = $this->safety->guestContextForPass($pass);
        abort_unless($context, 404);

        $validated = $request->validated();
        abort_unless(in_array($validated['assistance'], $context['assistanceOptions'], true), 422);
        abort_unless($this->safety->raiseForAccountCredential($user, $pass, $validated['assistance'], $validated), 422);

        return response()->json(['message' => 'Request received.']);
    }

    /**
     * The discreet QR gesture. No content back, success or not: the screen
     * the resident is holding up must not change, so no 403 either.
     */
    public function hold(HoldSafetyQrRequest $request): HttpResponse
    {
        $user = $request->user();

        if ($user?->can('keepSafetyProfile')) {
            $this->safety->arm($user, $request->validated());
        }

        return response()->noContent();
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

    /** The user's own valid staff credential; 404 without one. */
    private function staffPass(User $user): GatePass
    {
        $pass = $this->safety->staffCredentialPassFor($user);
        abort_unless($pass, 404);

        return $pass;
    }
}
