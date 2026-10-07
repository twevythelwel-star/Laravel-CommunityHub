<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\GateId;
use App\Http\Controllers\Controller;
use App\Models\GatePass;
use App\Models\Household;
use App\Services\DigitalAccessWalletService;
use App\Services\GatePassEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class DigitalAccessWalletController extends Controller
{
    public function __construct(
        private readonly DigitalAccessWalletService $walletService,
        private readonly GatePassEngine $engine,
    ) {}

    /**
     * Display the Digital Access Wallet page.
     */
    public function index(Request $request): InertiaResponse
    {
        $user = $request->user();
        $credentials = $this->walletService->getWalletCredentials($user);
        $revokedHistory = $this->walletService->getRevokedCredentials($user);

        $household = Household::where('primary_homeowner_id', $user->id)
            ->with('members')
            ->first();

        $gates = array_map(fn (GateId $g) => [
            'id' => $g->value,
            'name' => $g->label(),
        ], GateId::cases());

        return Inertia::render('Dashboard/Wallet', [
            'credentials' => $credentials,
            'revokedHistory' => $revokedHistory,
            'household' => $household ? [
                'id' => $household->id,
                'name' => $household->name,
                'property_number' => $household->property_number,
                'members_count' => $household->members->count(),
            ] : null,
            'gates' => $gates,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role->value,
                'canScan' => $user->can('scanPasses'),
            ],
        ]);
    }

    /**
     * Fresh JSON endpoint returning live tokens and countdowns for active wallet cards.
     */
    public function credentials(Request $request): JsonResponse
    {
        $credentials = $this->walletService->getWalletCredentials($request->user());
        $revokedHistory = $this->walletService->getRevokedCredentials($request->user());

        return response()->json([
            'credentials' => $credentials,
            'revoked_history' => $revokedHistory,
            'refreshed_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Report credential lost/stolen:
     * Immediately revokes the current credential (invalidating all QR tokens and screenshots)
     * and automatically generates a fresh replacement credential.
     */
    public function reportLost(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pass_id' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $pass = GatePass::where('pass_id', $validated['pass_id'])->firstOrFail();
        $this->authorizePassAccess($request->user(), $pass);

        $result = $this->walletService->reportLostAndReplace(
            $pass,
            $request->user(),
            $validated['reason'] ?? 'Reported Lost/Stolen by Cardholder'
        );

        return response()->json($result);
    }

    /**
     * Download an official Apple Wallet (.pkpass) bundle.
     */
    public function downloadApplePass(Request $request, string $passId): Response
    {
        $pass = GatePass::where('pass_id', $passId)->firstOrFail();

        // Ensure user is authorized for this pass
        $this->authorizePassAccess($request->user(), $pass);

        $pkpass = $this->walletService->generateApplePkpass($pass);

        return response($pkpass, 200, [
            'Content-Type' => 'application/vnd.apple.pkpass',
            'Content-Disposition' => "attachment; filename=\"pass-{$pass->pass_id}.pkpass\"",
            'Content-Length' => strlen($pkpass),
            'Pragma' => 'no-cache',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    /**
     * Return Google Wallet "Save to Google Wallet" pass object & JWT payload.
     */
    public function googlePassPayload(Request $request, string $passId): JsonResponse
    {
        $pass = GatePass::where('pass_id', $passId)->firstOrFail();
        $this->authorizePassAccess($request->user(), $pass);

        $payload = $this->walletService->getGoogleWalletPayload($pass);

        return response()->json($payload);
    }

    /**
     * Return Samsung Wallet digital key & pass card payload.
     */
    public function samsungPassPayload(Request $request, string $passId): JsonResponse
    {
        $pass = GatePass::where('pass_id', $passId)->firstOrFail();
        $this->authorizePassAccess($request->user(), $pass);

        $payload = $this->walletService->getSamsungWalletPayload($pass);

        return response()->json($payload);
    }

    /**
     * Interactive Gate NFC Tap Simulation:
     * Resident or security guard taps contactless phone against the virtual gate antenna.
     */
    public function simulateNfcTap(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'payload' => ['required', 'string'],
            'gate' => ['nullable', 'string'],
        ]);

        $gate = GateId::tryFrom($validated['gate'] ?? '') ?? GateId::Gate01;
        $user = $request->user();

        $result = $this->walletService->simulateNfcTap($validated['payload'], $gate, $user);

        return response()->json($result);
    }

    /**
     * Ensure the requesting user owns the pass, is head of household for the pass, or is administrative.
     */
    private function authorizePassAccess($user, GatePass $pass): void
    {
        if ($user->role->isAdministrative() || $pass->user_id === $user->id) {
            return;
        }

        // Check if pass belongs to user's household
        $household = Household::where('primary_homeowner_id', $user->id)
            ->whereHas('members', fn ($q) => $q->where('gate_pass_id', $pass->id))
            ->exists();

        if (! $household) {
            abort(403, 'Unauthorized access to this digital credential.');
        }
    }
}
