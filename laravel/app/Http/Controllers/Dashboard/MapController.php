<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Map\LocateMapRequest;
use App\Http\Requests\Map\StoreLandmarkRequest;
use App\Models\Community;
use App\Models\Landmark;
use App\Services\GeofenceService;
use App\Services\Properties\MapPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Backs src/app/dashboard/map/page.tsx, LeafletMapFixed.tsx,
 * add-landmark-dialog.tsx and boundary-point-manager.tsx.
 */
class MapController extends Controller
{
    public function __construct(private readonly GeofenceService $geofence) {}

    /** See MapPresenter for the boundary, landmarks and editor state. */
    public function index(Request $request, MapPresenter $presenter): Response
    {
        return Inertia::render('Dashboard/Map', $presenter->props($request->user()));
    }

    public function storeLandmark(StoreLandmarkRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        Community::default()->landmarks()->create([
            ...$validated,
            'created_by' => $request->user()->id,
        ]);

        $request->user()->recordActivity("Added map landmark {$validated['name']}");

        return back()->with('success', 'Landmark added.');
    }

    public function destroyLandmark(Request $request, Landmark $landmark): RedirectResponse
    {
        $this->authorize('manageLandmarks');

        $name = $landmark->name;
        $landmark->delete();

        $request->user()->recordActivity("Removed map landmark {$name}");

        return back()->with('success', "Removed \"{$name}\" from the community map.");
    }

    /**
     * Server-side geofence check. The browser reports a position; the server
     * decides whether it is inside the published boundary, so the answer cannot
     * be spoofed by editing client state.
     */
    public function locate(LocateMapRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $point = [(float) $validated['lat'], (float) $validated['lng']];
        $polygon = Community::default()->publishedBoundary()?->published_coordinates ?? [];

        return response()->json([
            'point' => $point,
            'dms' => [
                'lat' => $this->geofence->toDms($point[0], true),
                'lng' => $this->geofence->toDms($point[1], false),
            ],
            'elevation' => $this->geofence->estimateElevation($point[0], $point[1]),
            'perimeter' => $this->geofence->perimeterInfo($point, $polygon),
        ]);
    }
}
