<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\GateId;
use App\Enums\PassCategory;
use App\Enums\ValidationStatus;
use App\Http\Controllers\Controller;
use App\Models\AccessLogEntry;
use App\Models\GatePass;
use App\Models\Staff;
use App\Services\GatePassEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
    public function __construct(private readonly GatePassEngine $engine) {}

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
                'status' => $pass->status,
            ],

            'visual' => [
                'variant' => $this->engine->assignedColorVariant($category, $pass->pass_id, $pass->rotation_seq),
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

    /** @return array<int, array<string, mixed>> */
    private function directory(): array
    {
        return GatePass::with('user:id,role,display_name')
            ->orderBy('category')
            ->orderBy('holder_name')
            ->get()
            ->map(function (GatePass $pass) {
                $variant = $this->engine->assignedColorVariant(
                    $pass->category,
                    $pass->pass_id,
                    $pass->rotation_seq,
                );

                return [
                    'id' => $pass->id,
                    'passId' => $pass->pass_id,
                    'category' => $pass->category->value,
                    'userName' => $pass->holder_name,
                    'role' => $pass->user?->role->value ?? $pass->category->value,
                    'property' => $pass->property,
                    'gate' => $pass->designated_gate->value,
                    'status' => $pass->isRevoked() ? 'REVOKED' : 'ACTIVE',
                    'colorVariant' => $variant,
                ];
            })
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
     * Validates a scanned token and writes the access-log entry atomically, so
     * an entry can never be recorded without the validation that justified it.
     */
    public function scan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:4096'],
            'gate' => ['nullable', 'string', 'in:GATE-01,GATE-02,GATE-ANY'],
        ]);

        $gate = GateId::tryFrom($validated['gate'] ?? '') ?? GateId::Gate01;

        $report = $this->engine->validate($validated['token'], $gate);

        $entry = DB::transaction(function () use ($report, $gate, $request) {
            $pass = GatePass::where('pass_id', $report['passId'])->first();

            return AccessLogEntry::create([
                'user_id' => $pass?->user_id,
                'user_name' => $report['userName'],
                'user_role' => $pass?->user?->role->value ?? $report['category'],
                'method' => 'Digital Pass',
                'gate' => config('gatepass.gates.'.$gate->value, $gate->value),
                'pass_id' => $report['passId'],
                'result' => $report['status'],
                'deny_reason' => $report['status'] === ValidationStatus::Deny->value
                    ? $report['primaryReason']
                    : null,
                'validation_report' => $report,
                'scanned_by' => $request->user()->id,
                'occurred_at' => now(),
            ]);
        });

        // Denials are worth a dedicated security trail, not just a table row.
        if ($report['status'] === ValidationStatus::Deny->value) {
            Log::channel('security')->warning('Gate pass denied', [
                'pass_id' => $report['passId'],
                'gate' => $gate->value,
                'reason' => $report['primaryReason'],
                'scanned_by' => $request->user()->uid,
            ]);
        }

        return response()->json([
            'report' => $report,
            'accessLogId' => $entry->id,
        ]);
    }

    public function revoke(Request $request, GatePass $gatePass): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $gatePass->revoke($request->user(), $validated['reason']);

        Log::channel('security')->notice('Gate pass revoked', [
            'pass_id' => $gatePass->pass_id,
            'revoked_by' => $request->user()->uid,
            'reason' => $validated['reason'],
        ]);

        return back()->with('success', "Pass {$gatePass->pass_id} has been revoked.");
    }
}
