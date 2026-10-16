<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vehicles\StoreVehicleRequest;
use App\Http\Requests\Vehicles\UpdateVehicleRequest;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\Vehicle;
use App\Services\VehicleManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VehicleController extends Controller
{
    public function __construct(
        protected VehicleManagementService $vehicleService
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        $myVehicles = $this->vehicleService->getVehiclesForUser($user);
        $isSecurityOrAdmin = $user->can('viewAny', Vehicle::class);
        $allEstateVehicles = $isSecurityOrAdmin ? $this->vehicleService->getAllVehicles() : [];

        $household = Household::where('primary_homeowner_id', $user->id)->first();
        $householdMembers = $household
            ? HouseholdMember::where('household_id', $household->id)->get(['id', 'name', 'role_in_household'])
            : [];

        $stats = [
            'total' => $myVehicles->count(),
            'anprEnabled' => $myVehicles->where('anpr_enabled', true)->count(),
            'electricVehicles' => $myVehicles->where('is_ev', true)->count(),
            'temporary' => $myVehicles->where('is_temporary', true)->count(),
            'estateTotal' => is_countable($allEstateVehicles) ? count($allEstateVehicles) : 0,
        ];

        return Inertia::render('Dashboard/Vehicles', [
            'vehicles' => $myVehicles,
            'estateVehicles' => $allEstateVehicles,
            'householdMembers' => $householdMembers,
            'stats' => $stats,
            'isSecurityOrAdmin' => $isSecurityOrAdmin,
        ]);
    }

    public function store(StoreVehicleRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $this->authorize('assignToMember', [Vehicle::class, $validated['household_member_id'] ?? null]);

        $this->vehicleService->registerVehicle($request->user(), $validated);

        return back()->with('success', 'Vehicle registered successfully. ANPR plate authorization active.');
    }

    public function update(UpdateVehicleRequest $request, Vehicle $vehicle): RedirectResponse
    {
        $validated = $request->validated();

        $user = $request->user();
        $this->authorize('manage', $vehicle);
        $this->authorize('assignToMember', [Vehicle::class, $validated['household_member_id'] ?? null]);

        // Suspension and revocation are security's call: an owner could set a
        // suspended plate back to active, or switch its ANPR back on.
        if (! $user->can('manageSecurity')) {
            unset($validated['status']);
            if (in_array($vehicle->status, ['suspended', 'revoked'], true)) {
                unset($validated['anpr_enabled']);
            }
        }

        $vehicle->update($validated);

        return back()->with('success', 'Vehicle details updated successfully.');
    }

    public function destroy(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $this->authorize('manage', $vehicle);

        $vehicle->delete();

        return back()->with('success', 'Vehicle removed from community registry.');
    }

    public function anprLookup(Request $request): JsonResponse
    {
        $plate = $request->query('plate', '');
        $result = $this->vehicleService->lookupPlate($plate);

        // The result names the owner, their property and where the car parks
        // (VehiclePolicy::view). Someone else's plate is answered exactly
        // like an unregistered one.
        $visible = ($result['found'] ?? false)
            && ($vehicle = Vehicle::find($result['id']))
            && $request->user()->can('view', $vehicle);

        if (! $visible) {
            return response()->json([
                'found' => false,
                'authorized' => false,
                'message' => "No registered vehicle matching plate '{$plate}'.",
                'vehicle' => null,
            ], 404);
        }

        return response()->json([
            'found' => true,
            'authorized' => $result['authorized'] ?? true,
            'owner' => $result['owner'] ?? ['name' => $result['ownerName'] ?? 'Resident'],
            'message' => 'Vehicle recognized by ANPR registry.',
            'vehicle' => $result,
        ]);
    }
}
