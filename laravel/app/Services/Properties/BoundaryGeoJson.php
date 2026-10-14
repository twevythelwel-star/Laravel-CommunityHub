<?php

namespace App\Services\Properties;

use App\Models\BoundaryConfig;
use App\Models\Community;
use InvalidArgumentException;

/**
 * Converts an estate boundary to and from GeoJSON. GeoJSON orders each
 * coordinate [lng, lat] and closes a polygon ring by repeating its first
 * point; the editor works in [lat, lng] with an open ring of at most 8.
 */
class BoundaryGeoJson
{
    private const MAX_POINTS = 8;

    /**
     * The boundary as a one-feature FeatureCollection.
     *
     * @param  array{areaAcres: mixed, perimeterMeters: mixed}  $metrics
     * @return array<string, mixed>
     */
    public function export(Community $community, BoundaryConfig $boundary, array $metrics): array
    {
        $ring = $boundary->points->map(fn ($p) => [(float) $p->lng, (float) $p->lat])->values()->all();

        if (count($ring) >= 3) {
            $ring[] = $ring[0];
        }

        return [
            'type' => 'FeatureCollection',
            'features' => [[
                'type' => 'Feature',
                'geometry' => ['type' => 'Polygon', 'coordinates' => [$ring]],
                'properties' => [
                    'community' => $community->name,
                    'code' => $community->code,
                    'version' => $boundary->version,
                    'status' => $boundary->status,
                    'pointCount' => $boundary->points->count(),
                    'areaAcres' => $metrics['areaAcres'],
                    'perimeterMeters' => $metrics['perimeterMeters'],
                    'exportedAt' => now()->toIso8601String(),
                ],
            ]],
        ];
    }

    /**
     * The editor points of the first polygon in a GeoJSON Polygon, Feature or
     * FeatureCollection, sampled down to 8.
     *
     * @return array<int, array{id: int, label: string, lat: float, lng: float, isOptional: bool}>
     *
     * @throws InvalidArgumentException with a message fit to show the user
     */
    public function parse(string $raw): array
    {
        $data = json_decode($raw, true);
        if (! is_array($data)) {
            throw new InvalidArgumentException('Invalid JSON structure.');
        }

        $ring = $this->outerRing($data);
        if (! is_array($ring) || count($ring) < 4) {
            throw new InvalidArgumentException('GeoJSON does not contain a valid polygon ring with at least 4 coordinates.');
        }

        $points = [];
        foreach ($this->sample($this->openRing($ring)) as $index => $coord) {
            if (! is_array($coord) || count($coord) < 2) {
                continue;
            }
            $pointIndex = $index + 1;
            $points[] = [
                'id' => $pointIndex,
                'label' => "Imported P{$pointIndex}",
                'lat' => (float) $coord[1],
                'lng' => (float) $coord[0],
                'isOptional' => $pointIndex > 4,
            ];
        }

        if (count($points) < 4) {
            throw new InvalidArgumentException('Could not extract at least 4 valid coordinates.');
        }

        return $points;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, mixed>|null
     */
    private function outerRing(array $data): ?array
    {
        $geometry = match ($data['type'] ?? null) {
            'FeatureCollection' => $data['features'][0]['geometry'] ?? null,
            'Feature' => $data['geometry'] ?? null,
            'Polygon' => $data,
            default => null,
        };

        return ($geometry['type'] ?? null) === 'Polygon' ? ($geometry['coordinates'][0] ?? null) : null;
    }

    /**
     * Drop the closing point GeoJSON repeats.
     *
     * @param  array<int, mixed>  $ring
     * @return array<int, mixed>
     */
    private function openRing(array $ring): array
    {
        $first = $ring[0];
        $last = end($ring);

        if (count($ring) > 3 && is_array($first) && is_array($last) && $first[0] == $last[0] && $first[1] == $last[1]) {
            array_pop($ring);
        }

        return $ring;
    }

    /**
     * Evenly spaced points, at most MAX_POINTS of them.
     *
     * @param  array<int, mixed>  $ring
     * @return array<int, mixed>
     */
    private function sample(array $ring): array
    {
        if (count($ring) <= self::MAX_POINTS) {
            return array_values($ring);
        }

        $step = count($ring) / self::MAX_POINTS;

        return array_map(fn (int $i) => $ring[(int) floor($i * $step)], range(0, self::MAX_POINTS - 1));
    }
}
