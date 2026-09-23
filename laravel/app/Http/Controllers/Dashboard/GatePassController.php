<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\GateId;
use App\Enums\PassCategory;
use App\Enums\PassStatus;
use App\Exceptions\InvalidPassTransition;
use App\Exceptions\ScanNotConfirmable;
use App\Http\Controllers\Controller;
use App\Models\GatePass;
use App\Models\Staff;
use App\Models\User;
use App\Services\GatePassEngine;
use App\Services\GateScanner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Backs src/app/dashboard/gate-pass/page.tsx and the scanner dialog.
 *
 * The token is minted server-side and polled by the client, so the signing key
 * never reaches the browser. The React pass components keep rendering the same
 * shape and colour data, now supplied as props.
 */
class GatePassController extends Controller
{
    public function __construct(
        private readonly GatePassEngine $engine,
        private readonly GateScanner $scanner,
    ) {}

    /**
     * States an operator may move a pass to by hand. Check-in and check-out
     * are not here: they only happen through a confirmed scan.
     */
    private const MANUAL_TRANSITIONS = [
        PassStatus::Approved, PassStatus::Rejected, PassStatus::Cancelled,
        PassStatus::Suspended, PassStatus::Active, PassStatus::Revoked,
    ];

    public function index(Request $request): Response
    {
        $user = $request->user();
        $pass = $this->engine->issuePassFor($user);

        $category = $pass->category;
        $canManage = $user->can('manageSecurity');

        return Inertia::render('Dashboard/GatePass', [
            'pass' => [
                // Needed by the PDF link, whose route binds {gatePass} by primary key.
                'id' => $pass->id,
                'passId' => $pass->pass_id,
                'category' => $category->value,
                'holderName' => $pass->holder_name,
                'property' => $pass->property,
                'accessZone' => $pass->access_zone,
                'gate' => $pass->designated_gate->value,
                'rotationSeq' => $pass->rotation_seq,
                'status' => $pass->status->value,
            ],

            'visual' => [
                'variant' => $this->engine->variantFor($pass),
                'shape' => $category->shape()->value,
            ],

            'policy' => $this->camelPolicy($this->engine->policyFor($category)),

            /*
             | Visual config for all seven categories. The client used to hold its
             | own copy in CATEGORY_CONFIGS; sending it keeps config/gatepass.php
             | the single source of truth and stops the two drifting apart.
             */
            'categoryConfigs' => $this->categoryConfigs(),

            /*
             | The identity directory replaces DEMO_PASS_DIRECTORY, a hardcoded
             | fixture in the old engine module. Only staff who may manage
             | security see other people's passes.
             */
            'directory' => $canManage ? $this->directory() : [],

            'staff' => $canManage
                ? Staff::with('addedBy:id,role')->orderBy('name')->get()->map(fn (Staff $s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'job' => $s->job,
                    'idType' => $s->id_type,
                    'idExpiry' => $s->id_expiry->toIso8601String(),
                    'property' => $s->property,
                    'status' => $s->effectiveStatus(),
                    'photoUrl' => $s->photo_url,
                    // Household staff carry the house-hex badge, community staff the diamond.
                    'category' => $s->addedBy?->role?->isResident()
                        ? PassCategory::HomeownerStaff->value
                        : PassCategory::Staff->value,
                ])
                : [],

            'windowSeconds' => (int) config('gatepass.window_seconds'),
            'gates' => config('gatepass.gates'),

            'can' => [
                'scan' => $user->can('scanPasses'),
                'manageSecurity' => $canManage,
                'manageUsers' => $user->can('manageUsers'),
            ],
        ]);
    }

    /** @return array<string, array<string, mixed>> */
    private function categoryConfigs(): array
    {
        $configs = [];

        foreach (PassCategory::cases() as $case) {
            $raw = $this->engine->categoryConfig($case);

            // camelCase for the React components, which expect the original
            // CategoryVisualConfig field names.
            $configs[$case->value] = [
                'displayName' => $raw['display_name'],
                'shape' => $raw['shape'],
                'shapeLabel' => $raw['shape_label'],
                'themeColor' => $raw['theme_color'],
                'contrastBg' => $raw['contrast_bg'],
                'accentColor' => $raw['accent_color'],
                'badgeBorder' => $raw['badge_border'],
                'gradient' => $raw['gradient'],
                'iconName' => $raw['icon_name'],
                'description' => $raw['description'],
            ];
        }

        return $configs;
    }

    private function directory(): array
    {
        return GatePass::with('user:id,role,display_name')
            ->orderBy('category')
            ->orderBy('holder_name')
            ->get()
            ->map(fn (GatePass $pass) => [
                'id' => $pass->id,
                'passId' => $pass->pass_id,
                'category' => $pass->category->value,
                'userName' => $pass->holder_name,
                'role' => $pass->user?->role->value ?? $pass->category->value,
                'property' => $pass->property,
                'gate' => $pass->designated_gate->value,
                'status' => $pass->status->value,
                'validFrom' => $pass->valid_from?->toIso8601String(),
                'validUntil' => $pass->valid_until?->toIso8601String(),
                'colorVariant' => $this->engine->variantFor($pass),
            ])
            ->all();
    }

    /** Converts a snake_case policy array into the camelCase shape React expects. */
    private function camelPolicy(array $policy): array
    {
        $hours = $policy['operational_hours'];

        return [
            'title' => $policy['title'],
            'description' => $policy['description'],
            'authorizedZones' => $policy['authorized_zones'],
            'allowedGates' => $policy['allowed_gates'],
            'operationalHours' => [
                'is24Hours' => $hours['is_24_hours'] ?? false,
                'startHour' => $hours['start_hour'] ?? null,
                'endHour' => $hours['end_hour'] ?? null,
                'daysOfWeek' => $hours['days_of_week'] ?? null,
            ],
            'privileges' => [
                'canManageGuests' => $policy['privileges']['can_manage_guests'],
                'canAssociateVehicles' => $policy['privileges']['can_associate_vehicles'],
                'hasEmergencyOverride' => $policy['privileges']['has_emergency_override'],
                'hasGateOperationOverride' => $policy['privileges']['has_gate_operation_override'],
                'restrictedFromHomeownerFunctions' => $policy['privileges']['restricted_from_homeowner_functions'],
            ],
        ];
    }

    /**
     * Returns the current rolling token. The front end polls this once per
     * validity window instead of computing the token locally.
     */
    public function token(Request $request): JsonResponse
    {
        $pass = $this->engine->issuePassFor($request->user());

        // A revoked, suspended or expired pass gets no code: a code the gate
        // will refuse is worse than an honest message on the pass page.
        if (! $pass->isActive()) {
            return response()->json([
                'message' => "Pass {$pass->pass_id} is {$pass->status->label()}. Contact estate security.",
                'status' => $pass->status->value,
            ], 423);
        }

        $issued = $this->engine->issueToken($pass);

        return response()->json([
            'token' => $issued['token'],
            'validFrom' => $issued['valid_from']->toIso8601String(),
            'validUntil' => $issued['valid_until']->toIso8601String(),
            'secondsRemaining' => max(0, $issued['valid_until']->getTimestamp() - now()->getTimestamp()),
        ]);
    }

    public function rotate(Request $request): JsonResponse
    {
        $pass = $this->engine->issuePassFor($request->user());

        $rotation = $this->engine->rotateVisualIdentity($pass);

        $request->user()->recordActivity('Rotated gate pass visual identity');

        return response()->json([
            'rotationSeq' => $rotation['next_seq'],
            'variant' => $rotation['variant'],
            'rotatedAt' => $rotation['rotated_at'],
        ]);
    }

    /**
     * Validates a scanned code and returns the server's decision: CHECK_IN,
     * CHECK_OUT or REJECT. A CHECK_IN or CHECK_OUT is not applied yet; the
     * response carries a scanId for confirmScan().
     */
    public function scan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:4096'],
            'gate' => ['nullable', 'string', Rule::in(['GATE-01', 'GATE-02'])],
        ]);

        $gate = GateId::tryFrom($validated['gate'] ?? '') ?? GateId::Gate01;
        $result = $this->scanner->scan($validated['token'], $gate, $request->user());

        return response()->json($result);
    }

    /**
     * Confirms (or refuses) a scanned decision. Takes only the scan ID: the
     * action to apply is the one the server decided when it validated the
     * code, so the browser cannot choose it.
     */
    public function confirmScan(Request $request, string $scan): JsonResponse
    {
        $validated = $request->validate([
            'accept' => ['required', 'boolean'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $result = $this->scanner->confirm($scan, $request->user(), $validated['accept'], $validated['reason'] ?? null);
        } catch (ScanNotConfirmable $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json(['success' => true, ...$result]);
    }

    public function revoke(Request $request, GatePass $gatePass): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $gatePass->revoke($request->user(), $validated['reason']);
        } catch (InvalidPassTransition $e) {
            return back()->withErrors(['reason' => $e->getMessage()]);
        }

        Log::channel('security')->notice('Gate pass revoked', [
            'pass_id' => $gatePass->pass_id,
            'revoked_by' => $request->user()->uid,
            'reason' => $validated['reason'],
        ]);

        return back()->with('success', "Pass {$gatePass->pass_id} has been revoked.");
    }

    /** Issues a new pass to an account whose last one was revoked or expired. */
    public function reissue(Request $request, User $user): RedirectResponse
    {
        try {
            $pass = $this->engine->reissuePassFor($user, $request->user());
        } catch (\RuntimeException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        Log::channel('security')->notice('Gate pass reissued', [
            'pass_id' => $pass->pass_id,
            'holder' => $user->uid,
            'by' => $request->user()->uid,
        ]);

        return back()->with('success', "Pass {$pass->pass_id} issued to {$user->display_name}.");
    }

    /**
     * Moves a pass through its lifecycle by hand: approve or reject a request,
     * suspend or reinstate, cancel, revoke.
     *
     * Security and administrators may make any of these moves. A resident may
     * cancel the pass of a guest they registered, and nothing else.
     */
    public function transition(Request $request, GatePass $gatePass): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(array_map(fn (PassStatus $s) => $s->value, self::MANUAL_TRANSITIONS))],
            'reason' => [
                Rule::requiredIf(fn () => in_array($request->input('status'), ['REJECTED', 'REVOKED', 'SUSPENDED'], true)),
                'nullable', 'string', 'max:500',
            ],
        ]);

        $user = $request->user();
        $target = PassStatus::from($validated['status']);

        $isHostCancelling = $target === PassStatus::Cancelled
            && $gatePass->visitor?->homeowner_id === $user->id;

        abort_unless($user->can('manageSecurity') || $isHostCancelling, 403);

        try {
            $target === PassStatus::Approved
                ? $this->engine->approve($gatePass, $user, $validated['reason'] ?? null)
                : $gatePass->transitionTo($target, $user, $validated['reason'] ?? null);
        } catch (InvalidPassTransition $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        // A contractor's pass is only sent once it is approved.
        $visitor = $gatePass->visitor;
        if ($target === PassStatus::Approved && $visitor && ($channels = $visitor->passNotificationChannels()) !== []) {
            $visitor->sendPassNotification($channels);
        }

        Log::channel('security')->notice('Gate pass status changed', [
            'pass_id' => $gatePass->pass_id,
            'status' => $gatePass->status->value,
            'by' => $user->uid,
            'reason' => $validated['reason'] ?? null,
        ]);

        return back()->with('success', "Pass {$gatePass->pass_id} is now {$gatePass->status->label()}.");
    }
}
