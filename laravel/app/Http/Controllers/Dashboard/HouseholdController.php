<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Household\StoreHouseholdMemberRequest;
use App\Http\Requests\Household\UpdateHouseholdMemberRequest;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Services\HouseholdManagementService;
use App\Services\People\HouseholdPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class HouseholdController extends Controller
{
    public function __construct(
        private readonly HouseholdManagementService $householdService,
    ) {}

    /**
     * Display the Family & Household Management dashboard.
     */
    public function index(Request $request, HouseholdPresenter $presenter): InertiaResponse
    {
        $this->authorize('create', HouseholdMember::class);

        return Inertia::render('Dashboard/Household', $presenter->props($request->user()));
    }

    /**
     * Add a new member to the household.
     */
    public function store(StoreHouseholdMemberRequest $request): RedirectResponse|JsonResponse
    {
        $household = $this->householdService->getOrCreateHousehold($request->user());
        $member = $this->householdService->addMember($household, $request->validated());

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
    public function update(UpdateHouseholdMemberRequest $request, HouseholdMember $member): RedirectResponse|JsonResponse
    {
        $updated = $this->householdService->updateMember($member, $request->validated());

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
        $this->authorize('delete', $member);

        $name = $member->name;
        $this->householdService->removeMember($member);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => "Member {$name} removed and digital credential revoked.",
            ]);
        }

        return back()->with('success', "Member {$name} removed and digital credential revoked.");
    }
}
