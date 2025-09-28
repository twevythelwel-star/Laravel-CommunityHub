
"use client";

import { useEffect, useRef } from "react";
import L, { Map as LeafletMap } from "leaflet";
import "leaflet/dist/leaflet.css";

type Props = {
  center?: [number, number];
  zoom?: number;
  className?: string;
  style?: React.CSSProperties;
  /** optional callback if you need the map instance for layers/markers */
  onReady?: (map: LeafletMap) => void;
};

export default function LeafletMapFixed({
  center = [18, -77.5],
  zoom = 12,
  className,
  style,
  onReady,
}: Props) {
  const containerRef = useRef<HTMLDivElement | null>(null);
  const mapRef = useRef<LeafletMap | null>(null);

  // Initialize the map once; make it StrictMode/HMR safe with guard + cleanup.
  useEffect(() => {
    const el = containerRef.current;
    if (!el) return;

    // If a previous map exists (HMR, route flip, etc.), remove it.
    if (mapRef.current) {
      mapRef.current.remove();
      mapRef.current = null;
    }

    // Very rare: DOM node kept by HMR with a stale Leaflet flag.
    const anyEl = el as any;
    if (anyEl._leaflet_id) {
      try {
        // This flag only prevents re-init; clearing it is safe because we
        // remove() on real instances above. This avoids the “already initialized” throw.
        anyEl._leaflet_id = undefined;
      } catch {}
    }

    // Create map
    const map = L.map(el, { zoomControl: true });
    mapRef.current = map;

    map.setView(center, zoom);

    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
      attribution: "© OpenStreetMap contributors",
    }).addTo(map);

    onReady?.(map);

    // Clean up completely so the container can be reused without errors.
    return () => {
      if (mapRef.current) {
        mapRef.current.remove();
        mapRef.current = null;
      }
      // Belt-and-suspenders: clear DOM flag if Leaflet left it behind.
      const c = el as any;
      if (c && c._leaflet_id) c._leaflet_id = undefined;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []); // initialize once

  // If center/zoom props change later, adjust without re-initializing.
  useEffect(() => {
    if (mapRef.current) mapRef.current.setView(center, zoom);
  }, [center, zoom]);

  return (
    <div
      ref={containerRef}
      className={className}
      style={{ height: 500, width: "100%", ...style }}
    />
  );
}

