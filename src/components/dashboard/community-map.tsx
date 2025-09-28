
'use client';

import { MapContainer, TileLayer, Polygon, useMap } from 'react-leaflet';
import { latLngBounds, type LatLngBoundsExpression, type LatLngExpression } from 'leaflet';
import 'leaflet/dist/leaflet.css';

const defaultCoords: LatLngExpression[] = [
  [18.4781, -77.9278],
  [18.4783, -77.9239],
  [18.4752, -77.9236],
  [18.4750, -77.9276],
];

const primaryColor = '#7EC4CF';
const primaryColorFill = 'rgba(126, 196, 207, 0.2)';

function MapBoundsUpdater({ bounds }: { bounds: LatLngBoundsExpression }) {
    const map = useMap();
    map.fitBounds(bounds);
    map.setMaxBounds(map.getBounds().pad(0.1)); // Add some padding
    return null;
}

export default function CommunityMap() {
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
