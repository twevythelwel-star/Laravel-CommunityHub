<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Parking\StoreParkingPassRequest;
use App\Http\Requests\Parking\VerifyParkingPassRequest;
use App\Models\ParkingPass;
use App\Models\Vehicle;
use App\Services\ParkingPassService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ParkingPassController extends Controller
{
    public function __construct(
        protected ParkingPassService $parkingService
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        $passes = $this->parkingService->getPassesForUser($user);
        $vehicles = Vehicle::where('user_id', $user->id)->get(['id', 'make', 'model', 'color', 'license_plate', 'parking_location']);

        $categoryStats = [];
        foreach (ParkingPass::categories() as $catKey => $catData) {
            $categoryStats[$catKey] = [
                'meta' => $catData,
                'count' => $passes->where('category', $catKey)->count(),
            ];
        }

        return Inertia::render('Dashboard/Parking', [
            'passes' => $passes,
            'vehicles' => $vehicles,
            'categories' => ParkingPass::categories(),
            'categoryStats' => $categoryStats,
        ]);
    }

    public function store(StoreParkingPassRequest $request): RedirectResponse
    {
        $user = $request->user();
        $isStaff = $user->can('manageSecurity');

        $validated = $request->validated();

        $this->authorize('issue', [ParkingPass::class, $validated]);

        // A resident issues passes for their own property and vehicles; bays and
        // the accessible and loading-zone categories are assigned by staff.
        if (! $isStaff) {
            $validated['property'] = $user->propertyLabel();
            $validated['assigned_bay'] = null;
        }

        $this->parkingService->issueParkingPass($user, $validated);

        return back()->with('success', 'Parking QR credential issued successfully.');
    }

    public function verify(VerifyParkingPassRequest $request): JsonResponse
    {
        $token = $request->validated('token') ?? $request->input('token') ?? $request->query('token', '');
        $result = $this->parkingService->verifyParkingPass((string) $token);

        return response()->json($result);
    }
}
