<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\BoundaryAuditLog;
use App\Models\Community;
use App\Services\GeofenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

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
    public function __construct(private readonly GeofenceService $geofence) {}

    public function edit(Request $request): Response
    {
        $community = Community::default();
        $draft = $community->draftBoundary() ?? $community->publishedBoundary();
        $published = $community->publishedBoundary();

        return Inertia::render('Dashboard/Boundary', [
            'community' => [
                'name' => $community->name,
                'code' => $community->code,
                'jurisdiction' => $community->jurisdiction,
                'datum' => $community->datum,
                'referenceCoord' => [
                    'lat' => $community->reference_lat,
                    'lng' => $community->reference_lng,
                    'dms' => $community->reference_dms,
                    'description' => $community->reference_description,
                ],
                'cadastralZone' => $community->cadastral_zone,
            ],
            'draft' => $draft ? [
                'id' => $draft->id,
                'version' => $draft->version,
                'status' => $draft->status,
                'points' => $draft->points->map(fn ($p) => [
                    'id' => $p->point_index,
                    'label' => $p->label,
                    'lat' => $p->lat,
                    'lng' => $p->lng,
                    'isOptional' => $p->is_optional,
                ]),
            ] : null,
            'publishedCoordinates' => $published?->published_coordinates ?? [],
            'validation' => $draft
                ? $this->geofence->validateBoundary($draft->coordinatePairs())
                : null,
            'auditHistory' => $draft
                ? $draft->auditLogs()->limit(50)->get()->map(fn (BoundaryAuditLog $l) => [
                    'id' => $l->id,
                    'community' => $l->community,
                    'action' => $l->action,
                    'changedBy' => $l->changed_by,
                    'role' => $l->role,
                    'previousVersion' => $l->previous_version,
                    'newVersion' => $l->new_version,
                    'pointsCount' => $l->points_count,
                    'timestamp' => $l->occurred_at->toIso8601String(),
                    'published' => $l->published,
                    'notes' => $l->notes,
                    'areaAcres' => $l->area_acres,
                    'perimeterMeters' => $l->perimeter_meters,
                ])
                : [],
        ]);
    }

    /** Stateless validation, so the editor can check a shape before saving. */
    public function validateBoundary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'points' => ['required', 'array', 'min:1', 'max:8'],
            'points.*.lat' => ['required', 'numeric', 'between:-90,90'],
            'points.*.lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $polygon = array_map(
            fn (array $p) => [(float) $p['lat'], (float) $p['lng']],
            $validated['points'],
        );

        return response()->json($this->geofence->validateBoundary($polygon));
    }

    public function saveDraft(Request $request): RedirectResponse
    {
        $validated = $this->validatePoints($request);

        $community = Community::default();
        $user = $request->user();

        DB::transaction(function () use ($validated, $community, $user) {
            $latestVersion = $community->boundaryConfigs()->max('version') ?? 0;

            $draft = $community->boundaryConfigs()
                ->where('status', 'DRAFT')
                ->orderByDesc('version')
                ->first();

            $previousVersion = $draft->version ?? $latestVersion;

            if (! $draft) {
                $draft = $community->boundaryConfigs()->create([
                    'version' => $latestVersion + 1,
                    'status' => 'DRAFT',
                ]);
            }

            $draft->points()->delete();

            foreach ($validated['points'] as $index => $point) {
                $pointIndex = $index + 1;

                $draft->points()->create([
                    'point_index' => $pointIndex,
                    'label' => $point['label'] ?? "Point {$pointIndex}",
                    'lat' => $point['lat'],
                    'lng' => $point['lng'],
                    // Points 1-4 are mandatory, 5-8 optional.
                    'is_optional' => $pointIndex > 4,
                ]);
            }

            $metrics = $this->geofence->polygonMetrics(
                array_map(fn ($p) => [(float) $p['lat'], (float) $p['lng']], $validated['points'])
            );

            $draft->auditLogs()->create([
                'community' => $community->name,
                'action' => 'Draft Saved',
                'changed_by' => $user->display_name,
                'role' => $user->role->value,
                'previous_version' => $previousVersion,
                'new_version' => $draft->version,
                'points_count' => count($validated['points']),
                'published' => false,
                'notes' => $validated['notes'] ?? null,
                'area_acres' => $metrics['areaAcres'],
                'perimeter_meters' => $metrics['perimeterMeters'],
                'occurred_at' => now(),
            ]);
        });

        return back()->with('success', 'Boundary draft saved.');
    }

    public function publish(Request $request): RedirectResponse
    {
        $validated = $this->validatePoints($request);

        $polygon = array_map(
            fn (array $p) => [(float) $p['lat'], (float) $p['lng']],
            $validated['points'],
        );

        $check = $this->geofence->validateBoundary($polygon);

        if (! $check['canPublish']) {
            return back()->withErrors(['points' => implode(' ', $check['errors'])]);
        }

        $community = Community::default();
        $user = $request->user();

        DB::transaction(function () use ($community, $user, $polygon, $validated, $check) {
            $previous = $community->publishedBoundary();

            // Supersede the previous published version rather than mutating it,
            // so the history stays intact.
            $previous?->update(['status' => 'SUPERSEDED']);

            $nextVersion = ($community->boundaryConfigs()->max('version') ?? 0) + 1;

            $config = $community->boundaryConfigs()->create([
                'version' => $nextVersion,
                'status' => 'PUBLISHED',
                'published_coordinates' => $polygon,
                'last_published_at' => now(),
                'last_published_by' => $user->display_name,
            ]);

            foreach ($validated['points'] as $index => $point) {
                $pointIndex = $index + 1;

                $config->points()->create([
                    'point_index' => $pointIndex,
                    'label' => $point['label'] ?? "Point {$pointIndex}",
                    'lat' => $point['lat'],
                    'lng' => $point['lng'],
                    'is_optional' => $pointIndex > 4,
                ]);
            }

            $config->auditLogs()->create([
                'community' => $community->name,
                'action' => 'Boundary Published',
                'changed_by' => $user->display_name,
                'role' => $user->role->value,
                'previous_version' => $previous->version ?? 0,
                'new_version' => $nextVersion,
                'points_count' => count($validated['points']),
                'published' => true,
                'notes' => $validated['notes'] ?? null,
                'area_acres' => $check['areaAcres'],
                'perimeter_meters' => $check['perimeterMeters'],
                'occurred_at' => now(),
            ]);

            // Discard any stale drafts now that a newer boundary is live.
            $community->boundaryConfigs()
                ->where('status', 'DRAFT')
                ->where('version', '<', $nextVersion)
                ->delete();
        });

        return back()->with('success', 'Boundary published to the whole community.');
    }

    private function validatePoints(Request $request): array
    {
        return $request->validate([
            'points' => ['required', 'array', 'min:4', 'max:8'],
            'points.*.label' => ['nullable', 'string', 'max:60'],
            'points.*.lat' => ['required', 'numeric', 'between:-90,90'],
            'points.*.lng' => ['required', 'numeric', 'between:-180,180'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
    }
}
