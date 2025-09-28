
'use client';

import 'leaflet/dist/leaflet.css';
import L, { type LatLngExpression } from 'leaflet';
import { useEffect, useRef } from 'react';
import { useMap } from '@/context/map-context';

const primaryColor = '#7EC4CF';
const primaryColorFill = 'rgba(126, 196, 207, 0.2)';

export default function CommunityMap() {
    const { coordinates } = useMap();
    const mapRef = useRef<HTMLDivElement>(null);
    const mapInstanceRef = useRef<L.Map | null>(null);

    useEffect(() => {
        if (!mapRef.current) {
            return;
        }
        
        if (mapInstanceRef.current) {
            mapInstanceRef.current.remove();
            mapInstanceRef.current = null;
        }

        if (coordinates.length === 0) return;

        const bounds = L.latLngBounds(coordinates);
        
        mapInstanceRef.current = L.map(mapRef.current, {
            center: bounds.getCenter(),
            zoom: 16,
            minZoom: 15,
            scrollWheelZoom: true,
        });

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(mapInstanceRef.current);
        
        L.polygon(coordinates, { color: primaryColor, fillColor: primaryColorFill, weight: 2 }).addTo(mapInstanceRef.current);

        const map = mapInstanceRef.current;
        map.fitBounds(bounds);
        map.setMaxBounds(map.getBounds().pad(0.1));

        return () => {
            if (mapInstanceRef.current) {
                mapInstanceRef.current.remove();
                mapInstanceRef.current = null;
            }
        };

    }, [coordinates]);


    return (
        <div 
            ref={mapRef} 
            style={{ height: '100%', width: '100%', borderRadius: 'var(--radius)' }}
        />
    );
};
