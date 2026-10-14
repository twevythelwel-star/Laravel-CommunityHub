<?php

namespace App\Services\Properties;

use App\Models\BoundaryAuditLog;
use App\Models\BoundaryConfig;
use App\Models\Community;
use App\Models\User;
use App\Services\GeofenceService;
use Illuminate\Support\Facades\DB;

/**
 * The life of an estate boundary: drafts, published versions, restores and
 * deletions. A published boundary is never edited in place: publishing or
 * restoring creates the next version and supersedes the previous one, and
 * every step writes an audit row, so the history stays complete.
 */
class BoundaryVersioning
{
    public function __construct(private readonly GeofenceService $geofence) {}

    /**
     * Save the points as the community's draft, creating the next version's
     * draft if there is none.
     *
     * @param  array<int, array{lat: float|string, lng: float|string, label?: string|null}>  $points
     */
    public function saveDraft(Community $community, User $user, array $points, ?string $notes = null): BoundaryConfig
    {
        return DB::transaction(function () use ($community, $user, $points, $notes) {
            $latestVersion = $community->boundaryConfigs()->max('version') ?? 0;

            $draft = $community->boundaryConfigs()
                ->where('status', 'DRAFT')
                ->orderByDesc('version')
                ->first();

            $previousVersion = $draft->version ?? $latestVersion;

            $draft ??= $community->boundaryConfigs()->create([
                'version' => $latestVersion + 1,
                'status' => 'DRAFT',
            ]);

            $draft->points()->delete();
            $this->writePoints($draft, $points);

            $this->audit($community, $user, $draft, 'Draft Saved', [
                'previous_version' => $previousVersion,
                'new_version' => $draft->version,
                'points_count' => count($points),
                'published' => false,
                'notes' => $notes,
                ...$this->metrics($this->polygon($points)),
            ]);

            return $draft;
        });
    }

    /**
     * Publish the points as the next version. The caller has already checked
     * the shape can be published; $check is that result, reused for metrics.
     *
     * @param  array<int, array{lat: float|string, lng: float|string, label?: string|null}>  $points
     * @param  array{areaAcres: mixed, perimeterMeters: mixed}  $check
     */
    public function publish(Community $community, User $user, array $points, array $check, ?string $notes = null): BoundaryConfig
    {
        return DB::transaction(function () use ($community, $user, $points, $check, $notes) {
            $previous = $community->publishedBoundary();
            $config = $this->createPublished($community, $user, $this->polygon($points));
            $this->writePoints($config, $points);

            $this->audit($community, $user, $config, 'Boundary Published', [
                'previous_version' => $previous->version ?? 0,
                'new_version' => $config->version,
                'points_count' => count($points),
                'published' => true,
                'notes' => $notes,
                'area_acres' => $check['areaAcres'],
                'perimeter_meters' => $check['perimeterMeters'],
            ]);

            return $config;
        });
    }

    /** Publish a copy of an earlier version as the next version. */
    public function restore(Community $community, User $user, BoundaryConfig $snapshot): BoundaryConfig
    {
        return DB::transaction(function () use ($community, $user, $snapshot) {
            $previous = $community->publishedBoundary();
            $config = $this->createPublished($community, $user, $snapshot->coordinatePairs());

            foreach ($snapshot->points as $point) {
                $config->points()->create($point->only(['point_index', 'label', 'lat', 'lng', 'is_optional']));
            }

            $this->audit($community, $user, $config, 'Boundary Replaced / Restored', [
                'previous_version' => $previous?->version ?? $snapshot->version,
                'new_version' => $config->version,
                'points_count' => $snapshot->points()->count(),
                'published' => true,
                'notes' => "Active boundary replaced/restored with snapshot from version {$snapshot->version}",
                ...$this->metrics($snapshot->coordinatePairs()),
            ]);

            return $config;
        });
    }

    /** Delete a draft or superseded version. The audit row outlives it. */
    public function delete(Community $community, User $user, BoundaryConfig $config): void
    {
        DB::transaction(function () use ($community, $user, $config) {
            $this->audit($community, $user, null, "Boundary {$config->status} v{$config->version} Deleted", [
                'previous_version' => $config->version,
                'new_version' => $config->version,
                'points_count' => $config->points()->count(),
                'published' => false,
                'notes' => "Deleted boundary configuration v{$config->version} ({$config->status})",
                'area_acres' => null,
                'perimeter_meters' => null,
            ]);

            $config->points()->delete();
            $config->delete();
        });
    }

    /**
     * The next version, published: supersedes the current published version
     * (kept for history) and discards drafts older than it.
     *
     * @param  array<int, array{0: float, 1: float}>  $polygon
     */
    private function createPublished(Community $community, User $user, array $polygon): BoundaryConfig
    {
        $community->publishedBoundary()?->update(['status' => 'SUPERSEDED']);

        $version = ($community->boundaryConfigs()->max('version') ?? 0) + 1;

        $config = $community->boundaryConfigs()->create([
            'version' => $version,
            'status' => 'PUBLISHED',
            'published_coordinates' => $polygon,
            'last_published_at' => now(),
            'last_published_by' => $user->display_name,
        ]);

        $community->boundaryConfigs()
            ->where('status', 'DRAFT')
            ->where('version', '<', $version)
            ->delete();

        return $config;
    }

    /**
     * Points 1-4 are mandatory, 5-8 optional.
     *
     * @param  array<int, array{lat: float|string, lng: float|string, label?: string|null}>  $points
     */
    private function writePoints(BoundaryConfig $config, array $points): void
    {
        foreach (array_values($points) as $index => $point) {
            $pointIndex = $index + 1;

            $config->points()->create([
                'point_index' => $pointIndex,
                'label' => $point['label'] ?? "Point {$pointIndex}",
                'lat' => $point['lat'],
                'lng' => $point['lng'],
                'is_optional' => $pointIndex > 4,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function audit(Community $community, User $user, ?BoundaryConfig $config, string $action, array $details): void
    {
        BoundaryAuditLog::create([
            'boundary_config_id' => $config?->id,
            'community' => $community->name,
            'action' => $action,
            'changed_by' => $user->display_name,
            'role' => $user->role->value,
            'occurred_at' => now(),
            ...$details,
        ]);
    }

    /**
     * @param  array<int, array{lat: float|string, lng: float|string}>  $points
     * @return array<int, array{0: float, 1: float}>
     */
    private function polygon(array $points): array
    {
        return array_map(fn (array $p) => [(float) $p['lat'], (float) $p['lng']], array_values($points));
    }

    /**
     * @param  array<int, array{0: float, 1: float}>  $polygon
     * @return array{area_acres: mixed, perimeter_meters: mixed}
     */
    private function metrics(array $polygon): array
    {
        $metrics = $this->geofence->polygonMetrics($polygon);

        return ['area_acres' => $metrics['areaAcres'], 'perimeter_meters' => $metrics['perimeterMeters']];
    }
}
