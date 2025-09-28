
'use client';

import 'leaflet/dist/leaflet.css';
import L, { type LatLngExpression } from 'leaflet';
import { useEffect, useRef } from 'react';

const defaultCoords: LatLngExpression[] = [
  [18.4781, -77.9278],
  [18.4783, -77.9239],
  [18.4752, -77.9236],
  [18.4750, -77.9276],
];

const primaryColor = '#7EC4CF';
const primaryColorFill = 'rgba(126, 196, 207, 0.2)';

export default function CommunityMap() {
    const mapRef = useRef<HTMLDivElement>(null);
    const mapInstanceRef = useRef<L.Map | null>(null);

    useEffect(() => {
        // Don't do anything if the map ref is not available
        if (!mapRef.current) {
            return;
        }
        
        // If the map is already initialized, don't re-initialize it.
        // This is the key to preventing the error during HMR or React StrictMode.
        if (mapInstanceRef.current) {
            return;
        }

        const bounds = L.latLngBounds(defaultCoords);
        
        mapInstanceRef.current = L.map(mapRef.current, {
            center: bounds.getCenter(),
            zoom: 16,
            minZoom: 15,
            scrollWheelZoom: true,
        });

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(mapInstanceRef.current);
        
        L.polygon(defaultCoords, { color: primaryColor, fillColor: primaryColorFill, weight: 2 }).addTo(mapInstanceRef.current);

        const map = mapInstanceRef.current;
        map.fitBounds(bounds);
        map.setMaxBounds(map.getBounds().pad(0.1));

        // Cleanup function to run when the component unmounts
        return () => {
            if (mapInstanceRef.current) {
                mapInstanceRef.current.remove();
                mapInstanceRef.current = null;
            }
        };

    }, []);


    return (
        <div 
            ref={mapRef} 
            style={{ height: '100%', width: '100%', borderRadius: 'var(--radius)' }}
        />
    );
};
