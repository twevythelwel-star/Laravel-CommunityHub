
// app/components/AreaLockMap.tsx
"use client";

import { useEffect, useMemo, useRef } from "react";
import { MapContainer, TileLayer, GeoJSON, useMap } from "react-leaflet";
import L, { LatLngBoundsExpression, GeoJSON as LGeoJSON } from "leaflet";
import "leaflet/dist/leaflet.css";

type AreaLockMapProps = {
  /** Any valid GeoJSON Polygon/MultiPolygon feature (WGS84) */
  feature: GeoJSON.Feature<GeoJSON.Polygon | GeoJSON.MultiPolygon>;
  /** Optional: visual style for highlight */
  style?: L.PathOptions;
  /** Optional padding in pixels when fitting */
  fitPadding?: number;
  /** Optional: extra buffer (degrees) around bounds you still allow panning into */
  boundsBufferDeg?: number;
};

function useHighlightAndLockArea({
  feature,
  style,
  fitPadding = 48,
  boundsBufferDeg = 0.01,
}: AreaLockMapProps) {
  const map = useMap();
  const layerRef = useRef<LGeoJSON | null>(null);

  // Compute the feature's bounds once
  const areaBounds = useMemo(() => {
    const gj = L.geoJSON(feature);
    const b = gj.getBounds();
    gj.remove(); // temp instance
    return b;
  }, [feature]);

  useEffect(() => {
    // 1) Add/Update highlight layer
    if (layerRef.current) {
      layerRef.current.removeFrom(map);
      layerRef.current = null;
    }
    const layer = L.geoJSON(feature, {
      style: {
        color: "hsl(var(--primary))",      // stroke
        weight: 3,
        opacity: 0.9,
        fillColor: "hsl(var(--primary))",  // fill
        fillOpacity: 0.2,
        ...style,
      },
    }).addTo(map);
    layer.bringToFront();
    layerRef.current = layer;

    // 2) Fit to area with padding
    map.fitBounds(areaBounds, { padding: [fitPadding, fitPadding], animate: true });

    // 3) Set panning bounds with a small buffer around the area
    const bb = areaBounds.pad(0); // Leaflet LatLngBounds
    const southWest = bb.getSouthWest();
    const northEast = bb.getNorthEast();
    const maxBounds: LatLngBoundsExpression = [
      [southWest.lat - boundsBufferDeg, southWest.lng - boundsBufferDeg],
      [northEast.lat + boundsBufferDeg, northEast.lng + boundsBufferDeg],
    ];
    map.setMaxBounds(maxBounds);
    // Make the "rubber band" sticky near the edge
    map.options.maxBoundsViscosity = 0.9;

    // 4) Ensure you can't zoom out so far that the area becomes a tiny dot
    // Compute a reasonable minZoom that contains the area + padding.
    const minZoomForArea = map.getBoundsZoom(areaBounds, true /* inside */);
    const currentMaxZoom = map.getMaxZoom() || 21;
    map.setMinZoom(minZoomForArea);
    map.setMaxZoom(currentMaxZoom);

    // Keep the minZoom in sync if container size changes
    const handleResize = () => {
      const z = map.getBoundsZoom(areaBounds, true);
      map.setMinZoom(z);
    };
    map.on("resize", handleResize);

    return () => {
      map.off("resize", handleResize);
      map.setMaxBounds(null);            // release lock
      map.setMinZoom(0);
      if (layerRef.current) {
        layerRef.current.removeFrom(map);
        layerRef.current = null;
      }
    };
  }, [map, feature, style, areaBounds, fitPadding, boundsBufferDeg]);
}

function HighlightController(props: AreaLockMapProps) {
  useHighlightAndLockArea(props);
  return null;
}

export default function AreaLockMap(props: AreaLockMapProps) {
  return (
    <div className="h-[500px] w-full rounded-lg overflow-hidden">
      <MapContainer
        // Center/zoom are placeholders; hook will fitBounds
        center={[18, -77.5]}
        zoom={10}
        style={{ height: "100%", width: "100%" }}
        // Sensible UX settings
        scrollWheelZoom
        zoomControl
        preferCanvas={false}
        // Smooth edge resistance when hitting maxBounds
        // (actual viscosity set in effect; this keeps TS happy)
        // @ts-ignore
        maxBoundsViscosity={0.9}
      >
        <TileLayer 
            url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png" 
            attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        />
        <HighlightController {...props} />
      </MapContainer>
    </div>
  );
}
