<?php

namespace App\Services\GIS;

/**
 * LocationService - Core GIS functionality for geospatial operations
 *
 * PHP counterpart to the TypeScript LocationModule, providing:
 * - Distance calculations (Haversine formula)
 * - Coordinate conversions (DMS, elevation estimation)
 * - Point-in-polygon tests
 * - Polygon metrics (area, perimeter, centroid)
 * - Geocoding utilities
 */
class LocationService
{
    private const EARTH_RADIUS_M = 6371000;

    private const M_PER_DEG_LNG = 111320;

    private const M_PER_DEG_LAT = 110574;

    /**
     * Convert decimal degrees to DMS (Degrees/Minutes/Seconds)
     *
     * @param  float  $deg  - Decimal degree value
     * @param  bool  $isLat  - Whether this is a latitude (true) or longitude (false)
     * @return string DMS formatted string (e.g., "18°28'30.0"N")
     */
    public function toDMS(float $deg, bool $isLat): string
    {
        $absolute = abs($deg);
        $degrees = (int) floor($absolute);
        $minutesNotTruncated = ($absolute - $degrees) * 60;
        $minutes = (int) floor($minutesNotTruncated);
        $seconds = number_format(($minutesNotTruncated - $minutes) * 60, 1);

        $direction = $isLat
            ? ($deg >= 0 ? 'N' : 'S')
            : ($deg >= 0 ? 'E' : 'W');

        return sprintf('%d°%d\'%s"%s', $degrees, $minutes, $seconds, $direction);
    }

    /**
     * Convert DMS to decimal degrees
     *
     * @param  string  $dms  - DMS string (e.g., "18°28'30.0"N")
     * @return float Decimal degree value
     */
    public function fromDMS(string $dms): float
    {
        if (! preg_match('/(\d+)°(\d+)\'([\d.]+)"([NSEW])/i', $dms, $matches)) {
            throw new \InvalidArgumentException("Invalid DMS format: {$dms}");
        }

        $degrees = (float) $matches[1];
        $minutes = (float) $matches[2];
        $seconds = (float) $matches[3];
        $direction = strtoupper($matches[4]);

        $decimal = $degrees + $minutes / 60 + $seconds / 3600;

        if (in_array($direction, ['S', 'W'])) {
            $decimal = -$decimal;
        }

        return $decimal;
    }

    /**
     * Estimate elevation based on location (simplified interpolation)
     * In production, this would use a proper elevation API
     *
     * @param  float  $lat  - Latitude
     * @param  float  $lng  - Longitude
     * @return int Estimated elevation in meters
     */
    public function estimateElevation(float $lat, float $lng): int
    {
        $baseLat = 18.4750;
        $baseLng = -77.9270;
        $dLat = ($lat - $baseLat) * 111000;
        $dLng = ($lng - $baseLng) * 105000;
        $elevation = (int) round(38 + $dLat * 0.08 + $dLng * 0.04);

        return max(15, min(180, $elevation));
    }

    /**
     * Calculate great-circle distance between two coordinates using Haversine formula
     *
     * @param  array  $from  - Starting coordinate [lat, lng]
     * @param  array  $to  - Ending coordinate [lat, lng]
     * @param  string  $unit  - Distance unit (default: meters)
     * @return float Distance in specified unit
     */
    public function haversineDistance(array $from, array $to, string $unit = 'meters'): float
    {
        $dLat = deg2rad($to[0] - $from[0]);
        $dLng = deg2rad($to[1] - $from[1]);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($from[0])) * cos(deg2rad($to[0])) * sin($dLng / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $distanceMeters = self::EARTH_RADIUS_M * $c;

        return $this->convertDistance($distanceMeters, 'meters', $unit);
    }

    /**
     * Convert distance between units
     *
     * @param  float  $value  - Distance value
     * @param  string  $fromUnit  - Source unit
     * @param  string  $toUnit  - Target unit
     * @return float Converted distance
     */
    public function convertDistance(float $value, string $fromUnit, string $toUnit): float
    {
        // Convert to meters first
        $meters = $value;
        switch ($fromUnit) {
            case 'kilometers':
                $meters = $value * 1000;
                break;
            case 'miles':
                $meters = $value * 1609.344;
                break;
            case 'nautical-miles':
                $meters = $value * 1852;
                break;
        }

        // Convert from meters to target unit
        switch ($toUnit) {
            case 'meters':
                return $meters;
            case 'kilometers':
                return $meters / 1000;
            case 'miles':
                return $meters / 1609.344;
            case 'nautical-miles':
                return $meters / 1852;
        }

        return $meters;
    }

    /**
     * Convert area between units
     *
     * @param  float  $value  - Area value
     * @param  string  $fromUnit  - Source unit
     * @param  string  $toUnit  - Target unit
     * @return float Converted area
     */
    public function convertArea(float $value, string $fromUnit, string $toUnit): float
    {
        // Convert to square meters first
        $sqMeters = $value;
        switch ($fromUnit) {
            case 'sq-kilometers':
                $sqMeters = $value * 1000000;
                break;
            case 'acres':
                $sqMeters = $value * 4046.86;
                break;
            case 'hectares':
                $sqMeters = $value * 10000;
                break;
            case 'sq-miles':
                $sqMeters = $value * 2589988.11;
                break;
        }

        // Convert from square meters to target unit
        switch ($toUnit) {
            case 'sq-meters':
                return $sqMeters;
            case 'sq-kilometers':
                return $sqMeters / 1000000;
            case 'acres':
                return $sqMeters / 4046.86;
            case 'hectares':
                return $sqMeters / 10000;
            case 'sq-miles':
                return $sqMeters / 2589988.11;
        }

        return $sqMeters;
    }

    /**
     * Ray-casting point-in-polygon test
     *
     * @param  array  $point  - Point to test [lat, lng]
     * @param  array  $polygon  - Polygon vertices [lat, lng][]
     * @return bool True if point is inside polygon
     */
    public function isPointInPolygon(array $point, array $polygon): bool
    {
        $count = count($polygon);
        if ($count < 3) {
            return false;
        }

        [$lat, $lng] = $point;
        $inside = false;

        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            [$latI, $lngI] = $polygon[$i];
            [$latJ, $lngJ] = $polygon[$j];

            if (($lngI > $lng) !== ($lngJ > $lng)) {
                $denominator = $lngJ - $lngI;

                // Guard against division by zero (horizontal edge)
                if ($denominator == 0.0) {
                    continue;
                }

                if ($lat < (($latJ - $latI) * ($lng - $lngI)) / $denominator + $latI) {
                    $inside = ! $inside;
                }
            }
        }

        return $inside;
    }

    /**
     * Calculate polygon metrics (centroid, area, perimeter)
     *
     * @param  array  $polygon  - Polygon vertices [lat, lng][]
     * @return array Polygon metrics object
     */
    public function calculatePolygonMetrics(array $polygon): array
    {
        if ($polygon === []) {
            return $this->getEmptyMetrics();
        }

        $count = count($polygon);
        $sumLat = array_sum(array_column($polygon, 0));
        $sumLng = array_sum(array_column($polygon, 1));
        $center = [$sumLat / $count, $sumLng / $count];

        if ($count < 3) {
            return $this->getEmptyMetrics($center);
        }

        $area = 0.0;
        $perimeter = 0;
        $latScale = cos(deg2rad($center[0]));

        for ($i = 0; $i < $count; $i++) {
            $p1 = $polygon[$i];
            $p2 = $polygon[($i + 1) % $count];

            $perimeter += $this->haversineDistance($p1, $p2);

            $x1 = ($p1[1] - $center[1]) * self::M_PER_DEG_LNG * $latScale;
            $y1 = ($p1[0] - $center[0]) * self::M_PER_DEG_LAT;
            $x2 = ($p2[1] - $center[1]) * self::M_PER_DEG_LNG * $latScale;
            $y2 = ($p2[0] - $center[0]) * self::M_PER_DEG_LAT;

            $area += ($x1 * $y2 - $x2 * $y1);
        }

        $sqMeters = abs($area) / 2;

        return [
            'center' => $center,
            'area' => [
                'sqMeters' => $sqMeters,
                'sqKilometers' => $sqMeters / 1000000,
                'acres' => number_format($sqMeters * 0.000247105, 2, '.', ''),
                'hectares' => number_format($sqMeters * 0.0001, 2, '.', ''),
                'sqMiles' => number_format($sqMeters / 2589988.11, 2, '.', ''),
            ],
            'perimeter' => [
                'meters' => (int) round($perimeter),
                'kilometers' => $perimeter / 1000,
                'miles' => number_format($perimeter / 1609.344, 2, '.', ''),
            ],
        ];
    }

    /**
     * Get empty metrics object
     *
     * @param  array  $center  - Optional center coordinate
     * @return array Empty metrics object
     */
    private function getEmptyMetrics(array $center = [0, 0]): array
    {
        return [
            'center' => $center,
            'area' => [
                'sqMeters' => 0,
                'sqKilometers' => 0,
                'acres' => '0.00',
                'hectares' => '0.00',
                'sqMiles' => '0.00',
            ],
            'perimeter' => [
                'meters' => 0,
                'kilometers' => 0,
                'miles' => '0.00',
            ],
        ];
    }

    /**
     * Calculate bounding box for a set of coordinates
     *
     * @param  array  $coordinates  - Array of coordinates
     * @return array Bounding box
     */
    public function calculateBoundingBox(array $coordinates): array
    {
        if (count($coordinates) === 0) {
            return ['north' => 0, 'south' => 0, 'east' => 0, 'west' => 0];
        }

        $lats = array_column($coordinates, 0);
        $lngs = array_column($coordinates, 1);

        return [
            'north' => max($lats),
            'south' => min($lats),
            'east' => max($lngs),
            'west' => min($lngs),
        ];
    }

    /**
     * Check if a coordinate is within a bounding box
     *
     * @param  array  $point  - Point to test
     * @param  array  $bbox  - Bounding box
     * @return bool True if point is within bounding box
     */
    public function isPointInBoundingBox(array $point, array $bbox): bool
    {
        return
            $point[0] >= $bbox['south'] &&
            $point[0] <= $bbox['north'] &&
            $point[1] >= $bbox['west'] &&
            $point[1] <= $bbox['east'];
    }

    /**
     * Calculate midpoint between two coordinates
     *
     * @param  array  $from  - Starting coordinate
     * @param  array  $to  - Ending coordinate
     * @return array Midpoint coordinate
     */
    public function midpoint(array $from, array $to): array
    {
        return [
            ($from[0] + $to[0]) / 2,
            ($from[1] + $to[1]) / 2,
        ];
    }

    /**
     * Calculate bearing between two coordinates
     *
     * @param  array  $from  - Starting coordinate
     * @param  array  $to  - Ending coordinate
     * @return float Bearing in degrees (0-360)
     */
    public function calculateBearing(array $from, array $to): float
    {
        $dLng = deg2rad($to[1] - $from[1]);
        $fromLat = deg2rad($from[0]);
        $toLat = deg2rad($to[0]);

        $x = sin($dLng) * cos($toLat);
        $y = cos($fromLat) * sin($toLat) - sin($fromLat) * cos($toLat) * cos($dLng);

        $bearing = rad2deg(atan2($x, $y));

        return ($bearing + 360) % 360;
    }

    /**
     * Validate coordinate ranges
     *
     * @param  array  $coordinate  - Coordinate to validate
     * @return bool True if coordinate is valid
     */
    public function isValidCoordinate(array $coordinate): bool
    {
        [$lat, $lng] = $coordinate;

        return $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180;
    }

    /**
     * Format coordinate for display
     *
     * @param  array  $coordinate  - Coordinate to format
     * @param  string  $format  - Format type
     * @return string Formatted coordinate string
     */
    public function formatCoordinate(array $coordinate, string $format = 'decimal'): string
    {
        [$lat, $lng] = $coordinate;

        switch ($format) {
            case 'dms':
                return $this->toDMS($lat, true).', '.$this->toDMS($lng, false);
            case 'dm':
                $latAbs = abs($lat);
                $latDeg = floor($latAbs);
                $latMin = (($latAbs - $latDeg) * 60);
                $latDir = $lat >= 0 ? 'N' : 'S';

                $lngAbs = abs($lng);
                $lngDeg = floor($lngAbs);
                $lngMin = (($lngAbs - $lngDeg) * 60);
                $lngDir = $lng >= 0 ? 'E' : 'W';

                return sprintf(
                    '%d°%.1f\'%s, %d°%.1f\'%s',
                    $latDeg,
                    $latMin,
                    $latDir,
                    $lngDeg,
                    $lngMin,
                    $lngDir
                );
            default:
                return sprintf('%.6f, %.6f', $lat, $lng);
        }
    }
}
