
'use client';

import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { useMap } from "@/context/map-context";
import dynamic from 'next/dynamic';
import { useIsClient } from "@/hooks/use-is-client";
import type { GeoJSON } from "leaflet";

const AreaLockMap = dynamic(() => import('@/components/dashboard/AreaLockMap'), { 
    ssr: false,
    loading: () => <div className="h-[500px] w-full flex items-center justify-center"><p>Loading map...</p></div>,
});


export default function MapPage() {
    const isClient = useIsClient();
    const { coordinates } = useMap();

    // The AreaLockMap expects a GeoJSON feature. We need to convert our coordinates.
    // GeoJSON polygons need to be "closed" - the first and last points must be the same.
    const polygonCoordinates = [...coordinates, coordinates[0]];

    const geoJsonFeature: GeoJSON.Feature<GeoJSON.Polygon> = {
        type: "Feature",
        properties: {},
        geometry: {
            type: "Polygon",
            coordinates: [polygonCoordinates.map(c => [c[1], c[0]])], // GeoJSON is [lng, lat]
        },
    };

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
                <CardContent>
                    {isClient && (
                       <AreaLockMap feature={geoJsonFeature} />
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
