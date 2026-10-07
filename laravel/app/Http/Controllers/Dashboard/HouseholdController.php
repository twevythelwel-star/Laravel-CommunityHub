<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\User;
use App\Services\GatePassEngine;
use App\Services\HouseholdManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class HouseholdController extends Controller
{
    public function __construct(
        private readonly HouseholdManagementService $householdService,
        private readonly GatePassEngine $engine,
    ) {}

    /**
     * Display the Family & Household Management dashboard.
     */
    public function index(Request $request): InertiaResponse
    {
        $user = $request->user();
        $this->ensureResident($user);

        // Get or initialize homeowner's household
        $household = $this->householdService->getOrCreateHousehold($user);

        return Inertia::render('Dashboard/Household', [
            'household' => [
                'id' => $household->id,
                'name' => $household->name,
                'property_number' => $household->property_number,
                'address' => $household->address,
                'notes' => $household->notes,
                'members' => $household->members->map(function (HouseholdMember $m) {
                    $token = null;
                    if ($m->gatePass && $m->gatePass->isActive()) {
                        try {
                            $token = $this->engine->issueToken($m->gatePass)['token'];
                        } catch (\Throwable $e) {
                            $token = $m->gatePass->pass_id;
                        }
                    }

                    return [
                        'id' => $m->id,
                        'name' => $m->name,
                        'email' => $m->email,
                        'phone' => $m->phone,
                        'role_in_household' => $m->role_in_household,
                        'relationship_label' => $m->relationship_label,
                        'pass_category' => $m->pass_category,
                        'credential_label' => $m->credentialTypeLabel(),
                        'role_badge_class' => $m->roleBadgeColor(),
                        'permissions' => $m->permissions ?? [],
                        'access_schedule' => $m->access_schedule,
                        'status' => $m->status,
                        'valid_until' => $m->valid_until?->toFormattedDateString(),
                        'gate_pass' => $m->gatePass ? [
                            'id' => $m->gatePass->id,
                            'pass_id' => $m->gatePass->pass_id,
                            'category' => $m->gatePass->category->value,
                            'status' => $m->gatePass->status->value,
                            'holder_name' => $m->gatePass->holder_name,
                            'token' => $token,
                        ] : null,
                    ];
                }),
            ],
            'availablePermissions' => HouseholdManagementService::AVAILABLE_PERMISSIONS,
            'isOwner' => true,
        ]);
    }

    /**
     * Add a new member to the household.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $this->ensureResident($user);
        $household = $this->householdService->getOrCreateHousehold($user);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50'],
            // Not 'homeowner': that member is the account holder, created with the household.
            'role_in_household' => ['required', 'string', 'in:spouse,child,long_term_occupant,caregiver,other'],
            'relationship_label' => ['nullable', 'string', 'max:100'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(array_keys(HouseholdManagementService::AVAILABLE_PERMISSIONS))],
            'access_schedule' => ['nullable', 'array'],
            'valid_until' => ['nullable', 'date'],
        ]);

        $member = $this->householdService->addMember($household, $validated);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => "Household member {$member->name} added successfully.",
                'member' => $member,
            ], 201);
        }

        return back()->with('success', "Household member {$member->name} added with digital gate credential.");
    }

    /**
     * Update permissions or details for a household member.
     */
    public function update(Request $request, HouseholdMember $member): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $this->ensureResident($user);
        $this->ensureCanManageMember($user, $member, 'Unauthorized to modify this household member.');

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(array_keys(HouseholdManagementService::AVAILABLE_PERMISSIONS))],
            'access_schedule' => ['nullable', 'array'],
            'status' => ['nullable', 'string', 'in:active,suspended'],
            'valid_until' => ['nullable', 'date'],
        ]);

        $updated = $this->householdService->updateMember($member, $validated);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Household member updated successfully.',
                'member' => $updated,
            ]);
        }

        return back()->with('success', "Updated permissions for {$updated->name}.");
    }

    /**
     * Remove a household member and revoke their gate pass.
     */
    public function destroy(Request $request, HouseholdMember $member): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $this->ensureResident($user);
        $this->ensureCanManageMember($user, $member, 'Unauthorized to delete this household member.');

        $name = $member->name;
        $this->householdService->removeMember($member);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => "Member {$name} removed and digital credential revoked.",
            ]);
        }

        return back()->with('success', "Member {$name} removed and digital credential revoked.");
    }

    /**
     * Households are residents' (and administrators'): each member gets a gate
     * pass, and any role could create a household and fill it.
     */
    private function ensureResident(User $user): void
    {
        abort_unless($user->role->isResident() || $user->role->isAdministrative(), 403, 'Households belong to residents.');
    }

    /**
     * A member of the user's own household, or any member for administrators.
     * Looked up, not created: checking used to give the caller a household
     * and a homeowner gate pass of their own first.
     */
    private function ensureCanManageMember(User $user, HouseholdMember $member, string $message): void
    {
        if ($user->role->isAdministrative()) {
            return;
        }

        $ownHouseholdId = Household::where('primary_homeowner_id', $user->id)->value('id');

        abort_unless($ownHouseholdId !== null && (int) $member->household_id === (int) $ownHouseholdId, 403, $message);
    }
}
