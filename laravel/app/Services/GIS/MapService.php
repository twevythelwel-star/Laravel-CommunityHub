<?php

namespace App\Services\GIS;

/**
 * MapService - Map rendering and tile provider abstraction
 *
 * PHP counterpart to the TypeScript MapModule, providing:
 * - Tile provider configuration management
 * - Map type conversions
 * - Map configuration utilities
 * - Layer management
 */
class MapService
{
    private static array $tileProviders = [];

    /**
     * Initialize default tile providers
     */
    public static function initialize(): void
    {
        self::$tileProviders = [
            'terrain' => [
                'url' => 'https://{s}.google.com/vt/lyrs=p&x={x}&y={y}&z={z}',
                'options' => [
                    'maxZoom' => 20,
                    'subdomains' => ['mt0', 'mt1', 'mt2', 'mt3'],
                ],
                'attribution' => 'Map data © Google Terrain',
                'maxZoom' => 20,
            ],
            'roadmap' => [
                'url' => 'https://{s}.google.com/vt/lyrs=m&x={x}&y={y}&z={z}',
                'options' => [
                    'maxZoom' => 20,
                    'subdomains' => ['mt0', 'mt1', 'mt2', 'mt3'],
                ],
                'attribution' => 'Map data © Google Maps',
                'maxZoom' => 20,
            ],
            'satellite' => [
                'url' => 'https://{s}.google.com/vt/lyrs=y&x={x}&y={y}&z={z}',
                'options' => [
                    'maxZoom' => 20,
                    'subdomains' => ['mt0', 'mt1', 'mt2', 'mt3'],
                ],
                'attribution' => 'Imagery © Google Satellite',
                'maxZoom' => 20,
            ],
            'hybrid' => [
                'url' => 'https://{s}.google.com/vt/lyrs=y&x={x}&y={y}&z={z}',
                'options' => [
                    'maxZoom' => 20,
                    'subdomains' => ['mt0', 'mt1', 'mt2', 'mt3'],
                ],
                'attribution' => 'Imagery © Google Hybrid',
                'maxZoom' => 20,
            ],
            'topo' => [
                'url' => 'https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png',
                'options' => [
                    'maxZoom' => 17,
                ],
                'attribution' => 'Map data: © OpenStreetMap contributors, SRTM | Map style: © OpenTopoMap',
                'maxZoom' => 17,
            ],
            'dark' => [
                'url' => 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png',
                'options' => [
                    'maxZoom' => 19,
                ],
                'attribution' => '© OpenStreetMap contributors © CARTO',
                'maxZoom' => 19,
            ],
            'light' => [
                'url' => 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png',
                'options' => [
                    'maxZoom' => 19,
                ],
                'attribution' => '© OpenStreetMap contributors © CARTO',
                'maxZoom' => 19,
            ],
        ];
    }

    /**
     * Get tile provider configuration for a map type
     *
     * @param  string  $mapType  - Type of map
     * @return array Tile provider configuration
     */
    public static function getTileProvider(string $mapType): array
    {
        self::initialize();

        return self::$tileProviders[$mapType] ?? self::$tileProviders['roadmap'];
    }

    /**
     * Register a custom tile provider
     *
     * @param  string  $mapType  - Type identifier
     * @param  array  $config  - Tile provider configuration
     */
    public static function registerTileProvider(string $mapType, array $config): void
    {
        self::$tileProviders[$mapType] = $config;
    }

    /**
     * Get available map types
     *
     * @return array Array of available map types
     */
    public static function getAvailableMapTypes(): array
    {
        self::initialize();

        return array_keys(self::$tileProviders);
    }

    /**
     * Convert coordinate array to GeoJSON Polygon format
     *
     * @param  array  $coordinates  - Array of [lat, lng] coordinates
     * @return array GeoJSON Polygon
     */
    public static function coordinatesToGeoJSONPolygon(array $coordinates): array
    {
        $geoJsonCoords = [];
        foreach ($coordinates as $coord) {
            $geoJsonCoords[] = [$coord[1], $coord[0]];
        }
        // Close the polygon
        $geoJsonCoords[] = [$coordinates[0][1], $coordinates[0][0]];

        return [
            'type' => 'Polygon',
            'coordinates' => [$geoJsonCoords],
        ];
    }

    /**
     * Convert GeoJSON Polygon to coordinate array
     *
     * @param  array  $geoJson  - GeoJSON Polygon
     * @return array Array of [lat, lng] coordinates
     */
    public static function geoJsonPolygonToCoordinates(array $geoJson): array
    {
        if (($geoJson['type'] ?? '') !== 'Polygon') {
            throw new \InvalidArgumentException('Invalid GeoJSON: must be a Polygon');
        }

        $coordinates = [];
        foreach ($geoJson['coordinates'][0] as $coord) {
            $coordinates[] = [$coord[1], $coord[0]];
        }

        // Remove the closing point if present
        if (
            count($coordinates) > 3 &&
            $coordinates[0][0] === $coordinates[count($coordinates) - 1][0] &&
            $coordinates[0][1] === $coordinates[count($coordinates) - 1][1]
        ) {
            array_pop($coordinates);
        }

        return $coordinates;
    }

    /**
     * Create a complete GeoJSON feature from coordinates
     *
     * @param  array  $coordinates  - Polygon vertices
     * @param  array  $properties  - Optional properties
     * @return array GeoJSON Feature
     */
    public static function createGeoJSONFeature(array $coordinates, array $properties = []): array
    {
        return [
            'type' => 'Feature',
            'properties' => $properties,
            'geometry' => self::coordinatesToGeoJSONPolygon($coordinates),
        ];
    }

    /**
     * Validate map configuration
     *
     * @param  array  $config  - Map configuration
     * @return array Validation result
     */
    public static function validateMapConfig(array $config): array
    {
        $errors = [];
        $warnings = [];

        // Validate center coordinates
        if (! isset($config['center']) || ! is_array($config['center']) || count($config['center']) !== 2) {
            $errors[] = 'Map center must be an array of [latitude, longitude]';
        } elseif (isset($config['center'])) {
            $locationService = new LocationService;
            if (! $locationService->isValidCoordinate($config['center'])) {
                $errors[] = 'Map center coordinates are invalid';
            }
        }

        // Validate zoom level
        if (isset($config['zoom'])) {
            if (! is_numeric($config['zoom']) || $config['zoom'] < 0 || $config['zoom'] > 20) {
                $errors[] = 'Zoom level must be between 0 and 20';
            }
        }

        // Validate map type
        if (isset($config['mapType']) && ! in_array($config['mapType'], self::getAvailableMapTypes())) {
            $warnings[] = "Map type '{$config['mapType']}' is not a standard type, will use default";
        }

        return [
            'isValid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Get default map configuration
     *
     * @param  string  $mapType  - Optional map type
     * @return array Default map configuration
     */
    public static function getDefaultConfig(string $mapType = 'terrain'): array
    {
        return [
            'center' => [18.4766, -77.9257],
            'zoom' => 16,
            'mapType' => $mapType,
            'minZoom' => 4,
            'maxZoom' => 20,
            'controls' => ['zoom', 'scale'],
        ];
    }
}
