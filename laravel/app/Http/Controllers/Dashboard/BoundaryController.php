<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Boundary\ImportBoundaryGeoJsonRequest;
use App\Http\Requests\Boundary\PublishBoundaryRequest;
use App\Http\Requests\Boundary\SaveBoundaryDraftRequest;
use App\Http\Requests\Boundary\ValidateBoundaryRequest;
use App\Models\BoundaryConfig;
use App\Models\Community;
use App\Services\GeofenceService;
use App\Services\Properties\BoundaryGeoJson;
use App\Services\Properties\BoundaryPagePresenter;
use App\Services\Properties\BoundaryVersioning;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Backs src/components/dashboard/boundary-point-manager.tsx (1,439 lines) and
 * the set-geofence dialog.
 *
 * The original persisted the boundary to localStorage, which meant each admin
 * edited a private copy and a published boundary was never actually shared. It
 * is now a versioned server record with an audit trail, so publishing takes
 * effect for everyone and every change is attributable.
 */
class BoundaryController extends Controller
{
    public function __construct(
        private readonly GeofenceService $geofence,
        private readonly BoundaryVersioning $versions,
    ) {}

    public function edit(BoundaryPagePresenter $presenter): Response
    {
        return Inertia::render('Dashboard/Boundary', $presenter->props(Community::default()));
    }

    /** Stateless validation, so the editor can check a shape before saving. */
    public function validateBoundary(ValidateBoundaryRequest $request): JsonResponse
    {
        return response()->json($this->geofence->validateBoundary($this->polygon($request->validated('points'))));
    }

    /** Export the published (or else the draft) boundary as GeoJSON. */
    public function exportGeoJson(BoundaryGeoJson $geoJson): StreamedResponse|JsonResponse
    {
        $this->authorize('viewBoundary');
        $community = Community::default();
        $target = $community->publishedBoundary() ?? $community->draftBoundary();

        if (! $target) {
            return response()->json(['error' => 'No boundary configuration found to export.'], 404);
        }

        $document = $geoJson->export($community, $target, $this->geofence->polygonMetrics($target->coordinatePairs()));
        $filename = Str::slug($community->code ?: 'community')."-boundary-v{$target->version}.geojson";

        return response()->streamDownload(function () use ($document) {
            echo json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }, $filename, ['Content-Type' => 'application/geo+json']);
    }

    /** Read polygon points from an uploaded file or pasted GeoJSON, without saving. */
    public function importGeoJson(ImportBoundaryGeoJsonRequest $request, BoundaryGeoJson $geoJson): JsonResponse
    {
        $raw = match (true) {
            $request->hasFile('file') => file_get_contents($request->file('file')->getRealPath()),
            $request->filled('geojson') => is_string($request->input('geojson'))
                ? $request->input('geojson')
                : json_encode($request->input('geojson')),
            default => null,
        };

        try {
            $points = $raw ? $geoJson->parse($raw) : throw new InvalidArgumentException('No GeoJSON file or payload provided.');
        } catch (InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'points' => $points,
            'validation' => $this->geofence->validateBoundary(array_map(fn ($p) => [$p['lat'], $p['lng']], $points)),
            'message' => 'GeoJSON boundary imported successfully.',
        ]);
    }

    public function saveDraft(SaveBoundaryDraftRequest $request): RedirectResponse
    {
        $this->versions->saveDraft(Community::default(), $request->user(), $request->validated('points'), $request->validated('notes'));

        return back()->with('success', 'Boundary draft saved.');
    }

    public function publish(PublishBoundaryRequest $request): RedirectResponse
    {
        $points = $request->validated('points');
        $check = $this->geofence->validateBoundary($this->polygon($points));

        if (! $check['canPublish']) {
            return back()->withErrors(['points' => implode(' ', $check['errors'])]);
        }

        $this->versions->publish(Community::default(), $request->user(), $points, $check, $request->validated('notes'));

        return back()->with('success', 'Boundary published to the whole community.');
    }

    public function replace(Request $request, BoundaryConfig $boundaryConfig): RedirectResponse
    {
        $this->authorize('manage', $boundaryConfig);
        $this->versions->restore($this->inCommunity($boundaryConfig), $request->user(), $boundaryConfig);

        return back()->with('success', "Boundary successfully replaced with Version {$boundaryConfig->version}.");
    }

    public function destroy(Request $request, BoundaryConfig $boundaryConfig): RedirectResponse
    {
        $this->authorize('manage', $boundaryConfig);
        $community = $this->inCommunity($boundaryConfig);

        if ($boundaryConfig->status === 'PUBLISHED') {
            return back()->withErrors(['boundary' => 'The active published boundary cannot be deleted.']);
        }

        $this->versions->delete($community, $request->user(), $boundaryConfig);

        return back()->with('success', 'Boundary configuration deleted.');
    }

    /** The default community, when the version belongs to it; otherwise a 404. */
    private function inCommunity(BoundaryConfig $boundaryConfig): Community
    {
        $community = Community::default();

        if ($boundaryConfig->community_id !== $community->id) {
            abort(404, 'Boundary configuration does not belong to this community.');
        }

        return $community;
    }

    /**
     * @param  array<int, array{lat: mixed, lng: mixed}>  $points
     * @return array<int, array{0: float, 1: float}>
     */
    private function polygon(array $points): array
    {
        return array_map(fn (array $p) => [(float) $p['lat'], (float) $p['lng']], $points);
    }
}
