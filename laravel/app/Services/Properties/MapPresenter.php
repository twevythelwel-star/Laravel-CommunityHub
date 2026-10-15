<?php

namespace App\Services\Properties;

use App\Models\BoundaryAuditLog;
use App\Models\BoundaryConfig;
use App\Models\Community;
use App\Models\Landmark;
use App\Models\User;
use App\Services\GeofenceService;

/**
 * The props of the community map (Dashboard/Map): the published boundary and
 * its metrics, the landmarks, the survey reference, and the boundary editor's
 * starting point (the newest draft, else what is live).
 *
 * Landmarks and the boundary both moved out of localStorage: one administrator
 * adding a landmark or redrawing the perimeter is now visible to every
 * resident, where before each browser held a private copy.
 */
class MapPresenter
{
    public function __construct(private readonly GeofenceService $geofence) {}

    /**
     * @return array<string, mixed>
     */
    public function props(User $viewer): array
    {
        $community = Community::default();
        $published = $community->publishedBoundary();
        $polygon = $published?->published_coordinates ?? [];

        return [
            'boundary' => [
                'coordinates' => $polygon,
                'version' => $published?->version,
                'publishedAt' => $published?->last_published_at?->toIso8601String(),
                'metrics' => $this->geofence->polygonMetrics($polygon),
            ],
            'landmarks' => $community->landmarks()->orderBy('name')->get()->map(fn (Landmark $l) => [
                'id' => $l->id,
                'name' => $l->name,
                'category' => $l->category,
                'description' => $l->description ?? '',
                'coordinates' => [$l->lat, $l->lng],
                'elevation' => $this->geofence->estimateElevation($l->lat, $l->lng),
                'iconType' => $l->iconType(),
                'color' => $l->pinColor(),
            ]),
            'community' => [
                'name' => $community->name,
                'code' => $community->code,
                'jurisdiction' => $community->jurisdiction ?? '',
                'datum' => $community->datum ?? 'WGS84',
                'referenceCoord' => [
                    'lat' => (float) ($community->reference_lat ?? 18.4766),
                    'lng' => (float) ($community->reference_lng ?? -77.9257),
                    'dms' => $community->reference_dms ?? '',
                    'description' => $community->reference_description ?? '',
                ],
                'cadastralZone' => $community->cadastral_zone ?? '',
            ],
            // Shape the boundary editor expects, previously loaded from
            // localStorage['community-boundary-config-v2'].
            'boundaryConfig' => $this->boundaryConfig($community->draftBoundary() ?? $published, $community->name),
            'can' => [
                'manageBoundary' => $viewer->can('manageBoundary'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function boundaryConfig(?BoundaryConfig $config, string $communityName): array
    {
        if ($config === null) {
            return [
                'version' => 0,
                'status' => 'DRAFT',
                'points' => [],
                'publishedCoordinates' => [],
                'lastPublishedAt' => null,
                'lastPublishedBy' => null,
                'auditHistory' => [],
            ];
        }

        return [
            'version' => $config->version,
            'status' => $config->status,
            'publishedCoordinates' => $config->published_coordinates ?? [],
            'lastPublishedAt' => $config->last_published_at?->toIso8601String(),
            'lastPublishedBy' => $config->last_published_by,
            'points' => $config->points->map(fn ($p) => [
                'id' => $p->point_index,
                'label' => $p->label,
                'lat' => (float) $p->lat,
                'lng' => (float) $p->lng,
                'isOptional' => $p->is_optional,
            ])->values(),
            'auditHistory' => $config->auditLogs()
                ->limit(50)
                ->get()
                ->map(fn (BoundaryAuditLog $l) => $l->toHistoryEntry($communityName))
                ->values(),
        ];
    }
}
