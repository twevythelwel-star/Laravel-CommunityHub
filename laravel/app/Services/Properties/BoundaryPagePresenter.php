<?php

namespace App\Services\Properties;

use App\Models\BoundaryAuditLog;
use App\Models\BoundaryConfig;
use App\Models\Community;
use App\Services\GeofenceService;
use Illuminate\Support\Collection;

/**
 * The props of the boundary editor (Dashboard/Boundary): the community's
 * survey reference, the draft being edited, the live published polygon, the
 * draft's audit trail and every version that can be restored.
 */
class BoundaryPagePresenter
{
    public function __construct(private readonly GeofenceService $geofence) {}

    /**
     * @return array<string, mixed>
     */
    public function props(Community $community): array
    {
        $published = $community->publishedBoundary();
        $draft = $community->draftBoundary() ?? $published;

        return [
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
                'points' => $this->points($draft),
            ] : null,
            'publishedCoordinates' => $published?->published_coordinates ?? [],
            'validation' => $draft ? $this->geofence->validateBoundary($draft->coordinatePairs()) : null,
            'auditHistory' => $draft ? $draft->auditLogs()->limit(50)->get()->map(fn (BoundaryAuditLog $l) => [
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
            ]) : [],
            'previousVersions' => $community->boundaryConfigs()
                ->with('points')
                ->orderByDesc('version')
                ->get()
                ->map(fn (BoundaryConfig $b) => [
                    'id' => $b->id,
                    'version' => $b->version,
                    'status' => $b->status,
                    'pointsCount' => $b->points->count(),
                    'publishedAt' => $b->last_published_at?->toIso8601String(),
                    'publishedBy' => $b->last_published_by,
                    'points' => $this->points($b),
                ]),
        ];
    }

    /**
     * @return Collection<int, array{id: int, label: string, lat: mixed, lng: mixed, isOptional: bool}>
     */
    private function points(BoundaryConfig $boundary): Collection
    {
        return $boundary->points->map(fn ($p) => [
            'id' => $p->point_index,
            'label' => $p->label,
            'lat' => $p->lat,
            'lng' => $p->lng,
            'isOptional' => $p->is_optional,
        ]);
    }
}
