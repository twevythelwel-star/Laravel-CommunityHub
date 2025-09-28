// app/components/AreaLockMap.tsx
"use client";

import { useEffect, useMemo, useRef, useState } from "react";
import { MapContainer, TileLayer, GeoJSON, useMap } from "react-leaflet";
import L, { LatLngBoundsExpression, GeoJSON as LGeoJSON, Map as LeafletMap } from "leaflet";
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

function HighlightController({
  feature,
  style,
  fitPadding = 48,
  boundsBufferDeg = 0.01,
}: AreaLockMapProps) {
  const map = useMap();
  const layerRef = useRef<LGeoJSON | null>(null);

  const areaBounds = useMemo(() => {
    const gj = L.geoJSON(feature);
    const b = gj.getBounds();
    gj.remove();
    return b;
  }, [feature]);

  useEffect(() => {
    if (layerRef.current) {
      layerRef.current.removeFrom(map);
    }
    const layer = L.geoJSON(feature, {
      style: {
        color: "hsl(var(--primary))",
        weight: 3,
        opacity: 0.9,
        fillColor: "hsl(var(--primary))",
        fillOpacity: 0.2,
        ...style,
      },
    }).addTo(map);
    layerRef.current = layer;

    map.fitBounds(areaBounds, { padding: [fitPadding, fitPadding], animate: true });

    const bb = areaBounds.pad(0);
    const southWest = bb.getSouthWest();
    const northEast = bb.getNorthEast();
    const maxBounds: LatLngBoundsExpression = [
      [southWest.lat - boundsBufferDeg, southWest.lng - boundsBufferDeg],
      [northEast.lat + boundsBufferDeg, northEast.lng + boundsBufferDeg],
    ];
    map.setMaxBounds(maxBounds);
    map.options.maxBoundsViscosity = 0.9;

    const minZoomForArea = map.getBoundsZoom(areaBounds, true);
    map.setMinZoom(minZoomForArea);

    const handleResize = () => {
      const z = map.getBoundsZoom(areaBounds, true);
      map.setMinZoom(z);
    };
    map.on("resize", handleResize);

    return () => {
      map.off("resize", handleResize);
      if (layerRef.current) {
        layerRef.current.removeFrom(map);
        layerRef.current = null;
      }
    };
  }, [map, feature, style, areaBounds, fitPadding, boundsBufferDeg]);

  return null;
}

function SafeMapContainer(props: React.ComponentProps<typeof MapContainer> & { children: React.ReactNode }) {
  const [mounted, setMounted] = useState(false);
  const mapRef = useRef<LeafletMap | null>(null);

  useEffect(() => setMounted(true), []);

  useEffect(() => {
    return () => {
      if (mapRef.current) {
        const container = mapRef.current.getContainer() as any;
        mapRef.current.remove();
        if (container && container._leaflet_id) container._leaflet_id = undefined;
        mapRef.current = null;
      }
    };
  }, []);

  if (!mounted) return null;

  return (
    <MapContainer
      {...props}
      whenCreated={(map) => {
        if (!mapRef.current) mapRef.current = map;
      }}
    >
      {props.children}
    </MapContainer>
  );
}


export default function AreaLockMap(props: AreaLockMapProps) {
  return (
    <div className="h-[500px] w-full rounded-lg overflow-hidden">
      <SafeMapContainer
        center={[18, -77.5]}
        zoom={10}
        style={{ height: "100%", width: "100%" }}
        scrollWheelZoom
        zoomControl
        preferCanvas={false}
      >
        <TileLayer 
            url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png" 
            attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        />
        <HighlightController {...props} />
      </SafeMapContainer>
    </div>
  );
}
