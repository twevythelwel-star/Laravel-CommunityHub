
'use client';

import dynamic from 'next/dynamic';
import { useMap } from "@/context/map-context";
import { useIsClient } from "@/hooks/use-is-client";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import L from 'leaflet';
import { useEffect, useState } from 'react';

// Client-only to avoid SSR/hydration issues
const LeafletMapFixed = dynamic(() => import("@/components/dashboard/LeafletMapFixed"), {
  ssr: false,
  loading: () => <div className="h-[500px] w-full flex items-center justify-center"><p>Loading map...</p></div>,
});

export default function MapPage() {
    const isClient = useIsClient();
    const { coordinates } = useMap();
    const [mapInstance, setMapInstance] = useState<L.Map | null>(null);
    const [geoJsonLayer, setGeoJsonLayer] = useState<L.GeoJSON | null>(null);

    // Calculate bounds from coordinates. GeoJSON is [lng, lat], Leaflet is [lat, lng]
    const bounds = L.latLngBounds(coordinates);

    // Create GeoJSON feature from coordinates
    const geoJsonFeature: GeoJSON.Feature<GeoJSON.Polygon> = {
        type: "Feature",
        properties: {},
        geometry: {
            type: "Polygon",
            // GeoJSON polygon needs to be closed and in [lng, lat] format
            coordinates: [[...coordinates.map(c => [c[1], c[0]]), [coordinates[0][1], coordinates[0][0]]]],
        },
    };

    useEffect(() => {
        if (mapInstance) {
             // Remove old layer if it exists
            if (geoJsonLayer) {
                geoJsonLayer.remove();
            }

            // Add new layer
            const newLayer = L.geoJSON(geoJsonFeature, {
                style: {
                    color: "hsl(var(--primary))",
                    weight: 3,
                    opacity: 0.9,
                    fillColor: "hsl(var(--primary))",
                    fillOpacity: 0.2,
                }
            }).addTo(mapInstance);
            setGeoJsonLayer(newLayer);

            // Fit map to new bounds
            const bounds = newLayer.getBounds();
            mapInstance.fitBounds(bounds, { padding: [48, 48] });
             
            // Set panning bounds with a small buffer
            const paddedBounds = bounds.pad(0.05); // 5% padding
            mapInstance.setMaxBounds(paddedBounds);
            mapInstance.options.maxBoundsViscosity = 0.9;
            
            // Set min zoom
            const minZoomForArea = mapInstance.getBoundsZoom(bounds, true);
            mapInstance.setMinZoom(minZoomForArea);
        }
    }, [coordinates, mapInstance]); // Re-run when coordinates or map instance changes


    return (
        <div className="flex flex-col gap-8">
            <div>
                <h1 className="font-headline text-3xl font-bold">Community Map</h1>
                <p className="text-muted-foreground">An interactive, locked overview of the community layout.</p>
            </div>
            <Card>
                <CardHeader>
                    <CardTitle>Interactive Map</CardTitle>
                    <CardDescription>
                        The highlighted area represents the community boundaries. Panning and zooming are restricted to this area.
                    </CardDescription>
                </CardHeader>
                <CardContent className="overflow-hidden rounded-lg">
                    {isClient && (
                       <LeafletMapFixed
                            center={bounds.getCenter()}
                            zoom={16}
                            onReady={setMapInstance}
                        />
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
