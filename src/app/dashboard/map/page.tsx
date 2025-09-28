
'use client';

import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import dynamic from 'next/dynamic';
import { useState, useEffect } from 'react';
import type { LatLngBoundsExpression, LatLngExpression } from 'leaflet';
import { latLngBounds } from 'leaflet';


// Default coordinates provided by the user
const defaultCoords: LatLngExpression[] = [
  [18.4781, -77.9278],
  [18.4783, -77.9239],
  [18.4752, -77.9236],
  [18.4750, -77.9276],
];

const primaryColor = '#7EC4CF';
const primaryColorFill = 'rgba(126, 196, 207, 0.2)';

const CommunityMap = dynamic(() => import('react-leaflet').then(leaflet => {
    const { MapContainer, TileLayer, Polygon, useMap } = leaflet;

    function MapBoundsUpdater({ bounds }: { bounds: LatLngBoundsExpression }) {
        const map = useMap();
        map.fitBounds(bounds);
        map.setMaxBounds(map.getBounds().pad(0.1)); // Add some padding
        return null;
    }

    return function MapComponent() {
        const bounds = latLngBounds(defaultCoords);

        return (
            <MapContainer
                center={bounds.getCenter()}
                zoom={16}
                style={{ height: '100%', width: '100%', borderRadius: 'var(--radius)' }}
                scrollWheelZoom={true}
                minZoom={15}
            >
                <TileLayer
                    attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                    url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                />
                <Polygon
                    pathOptions={{ color: primaryColor, fillColor: primaryColorFill, weight: 2 }}
                    positions={defaultCoords}
                />
                <MapBoundsUpdater bounds={bounds} />
            </MapContainer>
        );
    };
}), {
    ssr: false,
    loading: () => <p>Loading map...</p>,
});


export default function MapPage() {
    const [isClient, setIsClient] = useState(false);

    useEffect(() => {
        setIsClient(true);
    }, []);

    return (
        <div className="flex flex-col gap-8">
            <div>
                <h1 className="font-headline text-3xl font-bold">Community Map</h1>
                <p className="text-muted-foreground">An overview of the community layout.</p>
            </div>
            <Card>
                <CardHeader>
                    <CardTitle>Interactive Map</CardTitle>
                    <CardDescription>
                        The highlighted area represents the community boundaries.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="h-[60vh] w-full">
                        {isClient ? <CommunityMap /> : <p>Loading map...</p>}
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}
