'use client';

import dynamic from 'next/dynamic';
import { useMap } from "@/context/map-context";
import { useAuth } from "@/context/auth-context";
import { useIsClient } from "@/hooks/use-is-client";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { useToast } from '@/hooks/use-toast';
import { useSearchParams } from 'next/navigation';
import type L from 'leaflet';
import { useEffect, useState, useRef, useMemo, useCallback, Suspense } from 'react';
import { 
  Mountain, 
  Map as MapIcon, 
  Satellite, 
  Layers, 
  Search, 
  Navigation, 
  Copy, 
  Lock, 
  Unlock, 
  Maximize2, 
  Minimize2, 
  Compass, 
  Plus, 
  Minus, 
  ShieldCheck, 
  Building2, 
  Trees, 
  Radio, 
  Wrench,
  Crosshair,
  Info,
  Eye,
  EyeOff,
  CheckCircle2,
  AlertTriangle,
  Zap,
  Settings2,
  MapPin,
  Trash2,
  RotateCcw,
  Filter
} from 'lucide-react';
import type { MapType } from '@/components/dashboard/LeafletMapFixed';
import { 
  COMMUNITY_LANDMARKS, 
  DEFAULT_COMMUNITY_LANDMARKS,
  CommunityLandmark,
  LandmarkCategory,
  LANDMARK_PIN_CONFIGS,
  loadCommunityLandmarks,
  saveCommunityLandmarks,
  toDMS, 
  estimateElevation, 
  haversineDistance, 
  isPointInGeofence, 
  getGeofencePerimeterInfo,
  calculatePolygonMetrics
} from '@/lib/geofence-utils';
import { SetGeofenceDialog } from '@/components/dashboard/set-geofence-dialog';
import { AddLandmarkDialog } from '@/components/dashboard/add-landmark-dialog';
import BoundaryPointManager from '@/components/dashboard/boundary-point-manager';

// Client-only to avoid SSR/hydration issues
const LeafletMapFixed = dynamic(() => import("@/components/dashboard/LeafletMapFixed"), {
  ssr: false,
  loading: () => (
    <div className="h-[620px] w-full flex flex-col items-center justify-center bg-muted/20 gap-3">
      <Mountain className="h-8 w-8 text-primary animate-pulse" />
      <p className="text-sm text-muted-foreground font-medium">Loading Google Terrain Map...</p>
    </div>
  ),
});

function MapPageContent() {
  const isClient = useIsClient();
  const { coordinates } = useMap();
  const { user } = useAuth();
  const { toast } = useToast();

  const [mapInstance, setMapInstance] = useState<L.Map | null>(null);
  const [mapType, setMapType] = useState<MapType>('terrain');
  const [isLocked, setIsLocked] = useState<boolean>(true);
  const [isFullscreen, setIsFullscreen] = useState<boolean>(false);
  const [searchQuery, setSearchQuery] = useState<string>('');
  
  // Dynamic Community Landmark Pins (Green Security Gates, Red Community Centers, Blue Parks)
  const [landmarks, setLandmarks] = useState<CommunityLandmark[]>([]);
  const [isAddPinOpen, setIsAddPinOpen] = useState<boolean>(false);
  const [isClickToPlacePinActive, setIsClickToPlacePinActive] = useState<boolean>(false);
  const [pinFilterCategory, setPinFilterCategory] = useState<'all' | LandmarkCategory>('all');
  const [addPinCoords, setAddPinCoords] = useState<[number, number] | null>(null);

  // Load landmarks on mount
  useEffect(() => {
    setLandmarks(loadCommunityLandmarks());
  }, []);

  // Filtered landmarks for display
  const filteredLandmarks = useMemo(() => {
    if (pinFilterCategory === 'all') return landmarks;
    return landmarks.filter(l => l.category === pinFilterCategory);
  }, [landmarks, pinFilterCategory]);

  // Counts for badge tabs
  const categoryCounts = useMemo(() => {
    const counts = {
      all: landmarks.length,
      'Security Gate': 0,
      'Community Center': 0,
      'Park': 0,
    };
    landmarks.forEach(l => {
      if (counts[l.category] !== undefined) {
        counts[l.category]++;
      }
    });
    return counts;
  }, [landmarks]);

  // Handler to add landmark pin
  const handleAddLandmark = (newLandmark: CommunityLandmark) => {
    const updated = [...landmarks, newLandmark];
    setLandmarks(updated);
    saveCommunityLandmarks(updated);
    setIsClickToPlacePinActive(false);

    // Pan map to new pin
    if (mapInstance) {
      mapInstance.flyTo(newLandmark.coordinates, 18, { duration: 1.2 });
    }
  };

  // Handler to remove landmark pin
  const handleRemoveLandmark = useCallback((id: string, name: string) => {
    setLandmarks(prev => {
      const updated = prev.filter(l => l.id !== id);
      saveCommunityLandmarks(updated);
      return updated;
    });
    toast({
      title: 'Pin Removed',
      description: `Removed "${name}" from community map.`,
    });
  }, [toast]);

  // Reset to default landmarks
  const handleResetLandmarks = () => {
    setLandmarks(DEFAULT_COMMUNITY_LANDMARKS);
    saveCommunityLandmarks(DEFAULT_COMMUNITY_LANDMARKS);
    toast({
      title: 'Reset to Standard Pins',
      description: 'Restored baseline Security Gates, Community Center, and Parks.',
    });
  };

  // Geofence states
  const searchParams = useSearchParams();
  const tabParam = searchParams ? searchParams.get('tab') : null;
  const [showGeofenceMask, setShowGeofenceMask] = useState<boolean>(true);
  const [isGeofenceDialogOpen, setIsGeofenceDialogOpen] = useState<boolean>(false);
  const [activeView, setActiveView] = useState<'operational' | 'boundary'>(tabParam === 'boundary' ? 'boundary' : 'operational');

  useEffect(() => {
    if (tabParam === 'boundary') {
      setActiveView('boundary');
    } else if (tabParam === 'operational') {
      setActiveView('operational');
    }
  }, [tabParam]);

  // Inspected coordinate on map click
  const [inspectedCoords, setInspectedCoords] = useState<[number, number] | null>(null);
  const [activeCenter, setActiveCenter] = useState<[number, number]>([18.4766, -77.9257]);
  const [activeZoom, setActiveZoom] = useState<number>(16);

  const geoJsonLayerRef = useRef<L.GeoJSON | null>(null);
  const maskLayerRef = useRef<L.GeoJSON | null>(null);
  const markersLayerRef = useRef<L.LayerGroup | null>(null);
  const beaconsLayerRef = useRef<L.LayerGroup | null>(null);
  const inspectedMarkerRef = useRef<L.Marker | null>(null);
  const mapWrapperRef = useRef<HTMLDivElement | null>(null);

  const isSystemAdmin = user?.role === 'System Admin';

  // Polygon metrics (centroid, perimeter, area)
  const { center, areaAcres, areaHectares, perimeterMeters } = useMemo(() => {
    return calculatePolygonMetrics(coordinates);
  }, [coordinates]);

  // ── Render Inverted Geofence Mask Layer (Dims outside, highlights community) ──
  useEffect(() => {
    if (!mapInstance) return;

    import('leaflet').then((Leaflet) => {
      const LInstance = Leaflet.default;

      if (maskLayerRef.current) {
        maskLayerRef.current.remove();
        maskLayerRef.current = null;
      }

      if (!showGeofenceMask || coordinates.length < 3) return;

      const worldOuterRing = [
        [-180, -85],
        [180, -85],
        [180, 85],
        [-180, 85],
        [-180, -85],
      ];

      const communityHole = [
        ...coordinates.map(c => [c[1], c[0]]),
        [coordinates[0][1], coordinates[0][0]]
      ];

      const maskFeature: GeoJSON.Feature<GeoJSON.Polygon> = {
        type: "Feature",
        properties: {},
        geometry: {
          type: "Polygon",
          coordinates: [worldOuterRing, communityHole],
        },
      };

      const maskLayer = LInstance.geoJSON(maskFeature, {
        style: {
          fillColor: "#020817",
          fillOpacity: 0.36,
          stroke: false,
        },
        interactive: false,
      }).addTo(mapInstance);

      maskLayerRef.current = maskLayer;
      maskLayer.bringToBack();
    });
  }, [coordinates, mapInstance, showGeofenceMask]);

  // ── Render Geofence Perimeter Fence & Constraints ──
  useEffect(() => {
    if (!mapInstance) return;

    import('leaflet').then((Leaflet) => {
      const LInstance = Leaflet.default;

      if (geoJsonLayerRef.current) {
        geoJsonLayerRef.current.remove();
      }

      const geoJsonFeature: GeoJSON.Feature<GeoJSON.Polygon> = {
        type: "Feature",
        properties: {},
        geometry: {
          type: "Polygon",
          coordinates: [[...coordinates.map(c => [c[1], c[0]]), [coordinates[0][1], coordinates[0][0]]]],
        },
      };

      // Glowing Geofence Security Perimeter Fence Line
      const newLayer = LInstance.geoJSON(geoJsonFeature, {
        style: {
          color: "#10B981", // Emerald Geofence Barrier
          weight: 3.5,
          opacity: 0.95,
          dashArray: "8, 6",
          fillColor: "#10B981",
          fillOpacity: 0.08,
        }
      }).addTo(mapInstance);
      geoJsonLayerRef.current = newLayer;

      const bounds = newLayer.getBounds();

      if (isLocked) {
        mapInstance.fitBounds(bounds, { padding: [48, 48] });
        const paddedBounds = bounds.pad(0.08); // 8% exploration buffer
        mapInstance.setMaxBounds(paddedBounds);
        mapInstance.options.maxBoundsViscosity = 1.0; // Strict boundary enforcement
        const minZoomForArea = mapInstance.getBoundsZoom(bounds, true);
        mapInstance.setMinZoom(minZoomForArea);
      } else {
        mapInstance.setMaxBounds(undefined);
        mapInstance.setMinZoom(11);
      }
    });
  }, [coordinates, mapInstance, isLocked]);

  // ── Render Geofence Boundary Beacons (Radar Nodes on each corner vertex) ──
  useEffect(() => {
    if (!mapInstance) return;

    import('leaflet').then((Leaflet) => {
      const LInstance = Leaflet.default;

      if (beaconsLayerRef.current) {
        beaconsLayerRef.current.remove();
      }

      const group = LInstance.layerGroup().addTo(mapInstance);
      beaconsLayerRef.current = group;

      const nodePositions = ['NW Boundary', 'NE Boundary', 'SE Boundary', 'SW Boundary'];

      coordinates.forEach((coord, idx) => {
        const nodeName = `Geofence Node #${idx + 1} (${nodePositions[idx] || `Node ${idx + 1}`})`;
        const elev = estimateElevation(coord[0], coord[1]);

        const beaconHtml = `
          <div style="position: relative; display: flex; align-items: center; justify-content: center; width: 32px; height: 32px; cursor: pointer; transform: translate(-50%, -50%);">
            <div class="geofence-beacon-pulse" style="position: absolute; width: 26px; height: 26px; border-radius: 50%; background: rgba(16, 185, 129, 0.45); border: 1.5px solid #10B981;"></div>
            <div style="width: 12px; height: 12px; border-radius: 50%; background: #10B981; border: 2.5px solid #FFFFFF; box-shadow: 0 0 8px rgba(16,185,129,0.8); z-index: 2;"></div>
          </div>
        `;

        const beaconIcon = LInstance.divIcon({
          html: beaconHtml,
          className: 'geofence-beacon-icon',
          iconSize: [32, 32],
          iconAnchor: [16, 16],
          popupAnchor: [0, -16],
        });

        const popupContent = `
          <div style="min-width: 230px; padding: 12px 14px; font-family: inherit;">
            <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 4px;">
              <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #10B981;"></span>
              <span style="font-size: 11px; font-weight: 700; color: #10B981; text-transform: uppercase;">Perimeter Radar Node</span>
            </div>
            <h4 style="margin: 0 0 4px 0; font-size: 14px; font-weight: 700;">${nodeName}</h4>
            <p style="margin: 0 0 8px 0; font-size: 11px; color: #64748b; font-family: monospace;">
              ${coord[0].toFixed(5)}°N, ${Math.abs(coord[1]).toFixed(5)}°W
            </p>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 4px; padding: 6px 8px; background: rgba(100,116,139,0.08); border-radius: 6px; font-size: 11px;">
              <div><span style="color:#64748b;">Elevation</span><br/><strong>${elev}m MSL</strong></div>
              <div><span style="color:#64748b;">Status</span><br/><strong style="color:#10B981;">Armed & Active</strong></div>
            </div>
          </div>
        `;

        const marker = LInstance.marker(coord, { icon: beaconIcon }).addTo(group);
        marker.bindPopup(popupContent, { maxWidth: 280, closeButton: true });
      });
    });
  }, [coordinates, mapInstance]);

  // ── Render Google-Style Community Landmark Pins with Category Styling (Green, Red, Blue) ──
  useEffect(() => {
    if (!mapInstance) return;

    import('leaflet').then((Leaflet) => {
      const LInstance = Leaflet.default;

      if (markersLayerRef.current) {
        markersLayerRef.current.remove();
      }

      const group = LInstance.layerGroup().addTo(mapInstance);
      markersLayerRef.current = group;

      filteredLandmarks.forEach((landmark) => {
        // SVG glyph according to category
        let glyphSvg = '';
        let badgeIcon = '📍';

        if (landmark.category === 'Security Gate') {
          // Shield / Gate glyph
          glyphSvg = `<path d="M18 11.5L12.5 13.8V17.5C12.5 20.8 14.8 23.9 18 24.8C21.2 23.9 23.5 20.8 23.5 17.5V13.8L18 11.5Z" fill="${landmark.color}"/>`;
          badgeIcon = '🛡️';
        } else if (landmark.category === 'Community Center') {
          // Building / Center glyph
          glyphSvg = `<path d="M12.5 24V14.5L18 11L23.5 14.5V24H19.5V18.5H16.5V24H12.5ZM15 17H16.5V15H15V17ZM19.5 17H21V15H19.5V17Z" fill="${landmark.color}"/>`;
          badgeIcon = '🏛️';
        } else {
          // Tree / Park glyph
          glyphSvg = `<path d="M18 11L13.5 16H15.5L13 20H16.5V24H19.5V20H23L20.5 16H22.5L18 11Z" fill="${landmark.color}"/>`;
          badgeIcon = '🌳';
        }

        const pinHtml = `
          <div style="position: relative; display: flex; flex-direction: column; align-items: center; cursor: pointer; transform: translate(-50%, -100%);">
            <div style="position: absolute; bottom: 0; width: 22px; height: 9px; background: rgba(0,0,0,0.32); border-radius: 50%; filter: blur(2px);"></div>
            <svg width="36" height="46" viewBox="0 0 36 46" fill="none" xmlns="http://www.w3.org/2000/svg" style="filter: drop-shadow(0 4px 6px rgba(0,0,0,0.35)); transition: transform 0.2s;">
              <path d="M18 0C8.05888 0 0 8.05888 0 18C0 29.5 15.3 43.8 17.1 45.4C17.6 45.9 18.4 45.9 18.9 45.4C20.7 43.8 36 29.5 36 18C36 8.05888 27.9411 0 18 0Z" fill="${landmark.color}"/>
              <circle cx="18" cy="18" r="9.5" fill="#FFFFFF"/>
              ${glyphSvg}
            </svg>
            <div style="background: rgba(15,23,42,0.88); color: #f8fafc; font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 9999px; margin-top: -6px; border: 1.5px solid ${landmark.color}; white-space: nowrap; box-shadow: 0 2px 8px rgba(0,0,0,0.4); display: flex; align-items: center; gap: 4px;">
              <span>${badgeIcon}</span>
              <span>${landmark.name.length > 20 ? landmark.name.substring(0, 18) + '...' : landmark.name}</span>
            </div>
          </div>
        `;

        const icon = LInstance.divIcon({
          html: pinHtml,
          className: 'google-landmark-pin',
          iconSize: [36, 46],
          iconAnchor: [18, 46],
          popupAnchor: [0, -46],
        });

        const popupContent = `
          <div style="min-width: 250px; padding: 14px 16px; font-family: inherit;">
            <div style="display: flex; align-items: center; justify-content: space-between; gap: 6px; margin-bottom: 6px;">
              <div style="display: flex; align-items: center; gap: 6px;">
                <span style="display: inline-block; width: 10px; height: 10px; border-radius: 9999px; background-color: ${landmark.color};"></span>
                <span style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: ${landmark.color};">${landmark.category}</span>
              </div>
              <span style="font-size: 10px; padding: 2px 6px; border-radius: 4px; background: rgba(100,116,139,0.12); color: #64748b; font-family: monospace;">
                ${landmark.elevation}m MSL
              </span>
            </div>
            <h4 style="margin: 0 0 6px 0; font-size: 15px; font-weight: 700; color: inherit;">${landmark.name}</h4>
            <p style="margin: 0 0 10px 0; font-size: 12px; color: #64748b; line-height: 1.4;">${landmark.description}</p>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 6px; padding: 8px; background: rgba(100,116,139,0.08); border-radius: 8px; font-size: 11px; margin-bottom: 10px;">
              <div>
                <span style="color: #64748b; display: block;">Pin Color</span>
                <strong style="color: ${landmark.color}; font-size: 12px;">${landmark.category === 'Security Gate' ? 'Emerald Green' : landmark.category === 'Community Center' ? 'Ruby Red' : 'Sky Blue'}</strong>
              </div>
              <div>
                <span style="color: #64748b; display: block;">Geofence Access</span>
                <strong style="color: #10b981; font-size: 12px;">Authorized Zone</strong>
              </div>
            </div>
            <div style="font-size: 11px; color: #64748b; font-family: monospace; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center;">
              <span>${landmark.coordinates[0].toFixed(5)}°, ${landmark.coordinates[1].toFixed(5)}°</span>
            </div>
            <button id="btn-del-${landmark.id}" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 6px; padding: 6px 10px; background: rgba(239, 68, 68, 0.08); color: #EF4444; border: 1px solid rgba(239, 68, 68, 0.25); border-radius: 6px; font-size: 11px; font-weight: 600; cursor: pointer; transition: all 0.2s;">
              Remove This Landmark Pin
            </button>
          </div>
        `;

        const marker = LInstance.marker(landmark.coordinates, { icon }).addTo(group);
        marker.bindPopup(popupContent, { maxWidth: 310, closeButton: true });

        marker.on('popupopen', () => {
          const delBtn = document.getElementById(`btn-del-${landmark.id}`);
          if (delBtn) {
            delBtn.onclick = (e) => {
              e.stopPropagation();
              handleRemoveLandmark(landmark.id, landmark.name);
              mapInstance.closePopup();
            };
          }
        });
      });
    });
  }, [mapInstance, filteredLandmarks, handleRemoveLandmark]);

  // ── Handle Map Click with Real-Time Geofence Validation & Click-to-Place ──
  const handleMapClick = useCallback((coords: [number, number]) => {
    // If Click-to-Place mode is active, trigger Add Landmark Pin directly
    if (isClickToPlacePinActive) {
      setAddPinCoords(coords);
      setIsAddPinOpen(true);
      setIsClickToPlacePinActive(false);
      toast({
        title: "Location Selected",
        description: `Coordinates ${coords[0].toFixed(5)}°, ${coords[1].toFixed(5)}° selected for new landmark pin.`,
      });
      return;
    }

    setInspectedCoords(coords);

    if (!mapInstance) return;

    import('leaflet').then((Leaflet) => {
      const LInstance = Leaflet.default;

      if (inspectedMarkerRef.current) {
        inspectedMarkerRef.current.remove();
      }

      const elev = estimateElevation(coords[0], coords[1]);
      const geofenceInfo = getGeofencePerimeterInfo(coords, coordinates);
      const dmsLat = toDMS(coords[0], true);
      const dmsLng = toDMS(coords[1], false);

      const pinColor = geofenceInfo.isInside ? '#10B981' : '#F59E0B';

      const inspectPinHtml = `
        <div style="position: relative; display: flex; flex-direction: column; align-items: center; cursor: pointer; transform: translate(-50%, -100%);">
          <div class="google-pin-pulse" style="position: absolute; bottom: 0; width: 22px; height: 12px; background: ${geofenceInfo.isInside ? 'rgba(16, 185, 129, 0.4)' : 'rgba(245, 158, 11, 0.4)'}; border-radius: 50%; z-index: -1;"></div>
          <svg width="34" height="42" viewBox="0 0 34 42" fill="none" xmlns="http://www.w3.org/2000/svg" style="filter: drop-shadow(0 4px 8px rgba(0,0,0,0.45));">
            <path d="M17 0C7.61116 0 0 7.61116 0 17C0 27.5 14.5 40.5 16.2 42C16.6 42.4 17.4 42.4 17.8 42C19.5 40.5 34 27.5 34 17C34 7.61116 26.3888 0 17 0Z" fill="${pinColor}"/>
            <circle cx="17" cy="16" r="6.5" fill="#FFFFFF"/>
            <circle cx="17" cy="16" r="3.5" fill="${pinColor}"/>
          </svg>
        </div>
      `;

      const inspectIcon = LInstance.divIcon({
        html: inspectPinHtml,
        className: 'google-inspect-pin',
        iconSize: [34, 42],
        iconAnchor: [17, 42],
        popupAnchor: [0, -42],
      });

      const popupHtml = `
        <div style="min-width: 270px; padding: 14px 16px; font-family: inherit;">
          <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
            <span style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: ${pinColor}; letter-spacing: 0.05em;">
              ${geofenceInfo.isInside ? '🛡️ INSIDE GEOFENCE' : '⚠️ OUTSIDE GEOFENCE'}
            </span>
            <span style="font-size: 11px; color: #64748b;">${geofenceInfo.distanceMeters}m to perimeter</span>
          </div>
          <h4 style="margin: 0 0 4px 0; font-size: 16px; font-weight: 700;">${coords[0].toFixed(5)}, ${coords[1].toFixed(5)}</h4>
          <p style="margin: 0 0 8px 0; font-size: 12px; color: #64748b; font-family: monospace;">${dmsLat} ${dmsLng}</p>
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 6px; padding: 8px; background: rgba(100,116,139,0.08); border-radius: 8px; font-size: 11px; margin-bottom: 10px;">
            <div>
              <span style="color: #64748b; display: block;">Elevation</span>
              <strong style="color: inherit; font-size: 13px;">~${elev} m MSL</strong>
            </div>
            <div>
              <span style="color: #64748b; display: block;">Nearest Gate</span>
              <strong style="color: inherit; font-size: 12px;">${geofenceInfo.nearestGate.name.split(' ')[0]} (${geofenceInfo.nearestGate.distance}m)</strong>
            </div>
          </div>
          <div style="font-size: 11px; color: ${geofenceInfo.isInside ? '#10b981' : '#f59e0b'}; font-weight: 600; text-align: center;">
            ${geofenceInfo.isInside ? 'Authorized Community Cadastral Zone' : 'External Buffer Zone / Outside Monitored Bounds'}
          </div>
        </div>
      `;

      const marker = LInstance.marker(coords, { icon: inspectIcon }).addTo(mapInstance);
      marker.bindPopup(popupHtml, { maxWidth: 310, closeButton: true }).openPopup();
      inspectedMarkerRef.current = marker;
    });
  }, [mapInstance, coordinates, isClickToPlacePinActive, toast]);

  // ── Handle Coordinate or Landmark Search ──
  const handleSearchSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!searchQuery.trim() || !mapInstance) return;

    const coordMatch = searchQuery.match(/^(-?\d+(\.\d+)?)[,\s]+(-?\d+(\.\d+)?)$/);
    if (coordMatch) {
      const lat = parseFloat(coordMatch[1]);
      const lng = parseFloat(coordMatch[3]);
      if (lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180) {
        mapInstance.flyTo([lat, lng], 18, { duration: 1.2 });
        handleMapClick([lat, lng]);
        const inGeo = isPointInGeofence([lat, lng], coordinates);
        toast({
          title: inGeo ? "Centered (Within Geofence)" : "Centered (Outside Geofence)",
          description: `Navigated to ${lat.toFixed(5)}°, ${lng.toFixed(5)}°`,
        });
        return;
      }
    }

    const found = landmarks.find(
      (l) =>
        l.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
        l.category.toLowerCase().includes(searchQuery.toLowerCase())
    );

    if (found) {
      mapInstance.flyTo(found.coordinates, 18, { duration: 1.2 });
      handleMapClick(found.coordinates);
      toast({
        title: found.name,
        description: `Elevation: ${found.elevation}m • Inside Geofence`,
      });
    } else {
      toast({
        variant: "destructive",
        title: "Location Not Found",
        description: "Enter coordinates as '18.476, -77.925' or search for 'Gate', 'Clubhouse', 'Park'.",
      });
    }
  };

  const handleRecenter = () => {
    if (!mapInstance) return;
    mapInstance.flyTo(center, 16, { duration: 1 });
    toast({
      title: "Community Centered",
      description: `Targeted centroid: ${center[0].toFixed(5)}°, ${center[1].toFixed(5)}°`,
    });
  };

  const handleZoom = (delta: number) => {
    if (!mapInstance) return;
    mapInstance.setZoom(mapInstance.getZoom() + delta);
  };

  const copyCoordinates = (coords: [number, number]) => {
    const text = `${coords[0].toFixed(6)}, ${coords[1].toFixed(6)}`;
    navigator.clipboard.writeText(text);
    toast({
      title: "Coordinates Copied",
      description: `${text} copied to clipboard in decimal degrees.`,
    });
  };

  const toggleFullscreen = () => {
    if (!mapWrapperRef.current) return;
    if (!document.fullscreenElement) {
      mapWrapperRef.current.requestFullscreen().catch(() => {});
      setIsFullscreen(true);
    } else {
      document.exitFullscreen().catch(() => {});
      setIsFullscreen(false);
    }
  };

  const handleSimulateGeofence = () => {
    if (!mapInstance) return;
    mapInstance.flyTo(center, 17, { duration: 1 });
    handleMapClick(center);
    toast({
      title: "Geofence Verification",
      description: "Centroid test confirmed: 100% within authorized community perimeter fence.",
    });
  };

  const currentInspectGeofence = useMemo(() => {
    if (!inspectedCoords) return null;
    return getGeofencePerimeterInfo(inspectedCoords, coordinates);
  }, [inspectedCoords, coordinates]);

  return (
    <div className="flex flex-col gap-6 max-w-7xl mx-auto w-full pb-10 animate-fade-in">
      {/* System Admin Geofence Editor Modal */}
      <SetGeofenceDialog 
        open={isGeofenceDialogOpen} 
        onOpenChange={setIsGeofenceDialogOpen} 
      />

      {/* ── Page Header ── */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <div className="flex items-center gap-2 mb-1 flex-wrap">
            <span className="inline-flex items-center justify-center p-1.5 rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
              <ShieldCheck className="h-5 w-5" />
            </span>
            <h1 className="font-headline text-3xl font-bold tracking-tight">Community Geofenced Map</h1>
            <Badge variant="outline" className="font-mono text-xs border-emerald-500/30 text-emerald-600 bg-emerald-500/5 flex items-center gap-1">
              <span className="w-2 h-2 rounded-full bg-emerald-500 animate-ping" />
              GEOFENCE ACTIVE
            </Badge>

            {/* System Admin Boundary Manager Button */}
            {isSystemAdmin && (
              <Button
                size="sm"
                variant={activeView === 'boundary' ? 'default' : 'outline'}
                onClick={() => setActiveView(activeView === 'boundary' ? 'operational' : 'boundary')}
                className="text-xs h-7 gap-1.5 border-emerald-500/40 text-emerald-600 bg-emerald-500/10 hover:bg-emerald-500/20 font-semibold"
              >
                <Settings2 className="h-3.5 w-3.5" />
                {activeView === 'boundary' ? 'Back to Operational Map' : 'Boundary Point Manager'}
              </Button>
            )}
          </div>
          <p className="text-muted-foreground text-sm">
            Google Terrain visualization with active perimeter geofencing, cadastral boundary containment, and radar monitoring.
          </p>
        </div>

        {/* View Switcher Tabs & Geofence Telemetry Badge */}
        <div className="flex items-center gap-3 flex-wrap">
          <div className="flex items-center gap-1 bg-muted/60 p-1 rounded-xl border">
            <button
              onClick={() => setActiveView('operational')}
              className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all ${
                activeView === 'operational'
                  ? 'bg-card text-foreground shadow-sm'
                  : 'text-muted-foreground hover:text-foreground'
              }`}
            >
              <MapIcon className="h-3.5 w-3.5" />
              <span>Operational Map</span>
            </button>
            <button
              onClick={() => setActiveView('boundary')}
              className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all ${
                activeView === 'boundary'
                  ? 'bg-card text-foreground shadow-sm'
                  : 'text-muted-foreground hover:text-foreground'
              }`}
            >
              <ShieldCheck className="h-3.5 w-3.5 text-emerald-600" />
              <span>Boundary Point Manager</span>
              {isSystemAdmin ? (
                <Badge variant="outline" className="text-[9px] h-4 font-mono border-emerald-500/40 text-emerald-600 bg-emerald-500/10">
                  SYSADMIN
                </Badge>
              ) : (
                <Badge variant="outline" className="text-[9px] h-4 font-mono border-muted-foreground/30 text-muted-foreground">
                  RESTRICTED
                </Badge>
              )}
            </button>
          </div>

          <div className="flex items-center gap-3 bg-card border rounded-xl px-3.5 py-2 text-xs shadow-sm">
            <Radio className="h-4 w-4 text-emerald-500 shrink-0 animate-pulse" />
            <div className="flex flex-col font-mono leading-tight">
              <span className="font-semibold text-foreground flex items-center gap-1.5">
                <span>Perimeter: {perimeterMeters}m</span>
                <span className="text-muted-foreground">•</span>
                <span className="text-emerald-600 font-bold">{coordinates.length} Beacons</span>
              </span>
              <span className="text-muted-foreground text-[11px]">
                {toDMS(activeCenter[0], true)} {toDMS(activeCenter[1], false)}
              </span>
            </div>
          </div>
        </div>
      </div>

      {/* ── View Switcher Conditional Rendering ── */}
      {activeView === 'boundary' ? (
        <BoundaryPointManager />
      ) : (
        <>
          {/* ── Main Map Canvas Container ── */}
          <Card className="overflow-hidden border shadow-lg relative" ref={mapWrapperRef}>
        {/* Floating Google-Style Top Navigation Overlay */}
        <div className="absolute top-4 left-4 right-4 z-[400] flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3 pointer-events-none">
          {/* Search / Jump Input Box */}
          <form 
            onSubmit={handleSearchSubmit} 
            className="pointer-events-auto flex items-center bg-card/95 backdrop-blur-md border border-border shadow-md rounded-full px-3 py-1.5 w-full md:w-96 transition-all focus-within:ring-2 focus-within:ring-primary focus-within:shadow-lg"
          >
            <Search className="h-4 w-4 text-muted-foreground ml-1 mr-2 shrink-0" />
            <Input
              type="text"
              placeholder="Search coordinates or landmark..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="border-0 shadow-none focus-visible:ring-0 text-xs h-8 bg-transparent p-0"
            />
            {searchQuery && (
              <button
                type="button"
                onClick={() => setSearchQuery('')}
                className="text-xs text-muted-foreground hover:text-foreground px-2"
              >
                ✕
              </button>
            )}
            <Button type="submit" size="sm" variant="ghost" className="h-7 px-3 text-xs rounded-full font-medium">
              Go
            </Button>
          </form>

          {/* Map Layer Switcher & Pin Controls (Top-Right) */}
          <div className="pointer-events-auto flex items-center bg-card/95 backdrop-blur-md border border-border shadow-md rounded-full p-1 gap-1 self-end md:self-auto flex-wrap">
            <button
              onClick={() => setMapType('terrain')}
              className={`flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium transition-all ${
                mapType === 'terrain'
                  ? 'bg-primary text-primary-foreground shadow-sm'
                  : 'text-muted-foreground hover:text-foreground hover:bg-muted/50'
              }`}
            >
              <Mountain className="h-3.5 w-3.5" />
              <span>Terrain</span>
            </button>

            <button
              onClick={() => setMapType('roadmap')}
              className={`flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium transition-all ${
                mapType === 'roadmap'
                  ? 'bg-primary text-primary-foreground shadow-sm'
                  : 'text-muted-foreground hover:text-foreground hover:bg-muted/50'
              }`}
            >
              <MapIcon className="h-3.5 w-3.5" />
              <span>Streets</span>
            </button>

            <button
              onClick={() => setMapType('satellite')}
              className={`flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium transition-all ${
                mapType === 'satellite'
                  ? 'bg-primary text-primary-foreground shadow-sm'
                  : 'text-muted-foreground hover:text-foreground hover:bg-muted/50'
              }`}
            >
              <Satellite className="h-3.5 w-3.5" />
              <span>Satellite</span>
            </button>

            {/* Geofence Mask Toggle Button */}
            <button
              onClick={() => {
                setShowGeofenceMask(!showGeofenceMask);
                toast({
                  title: !showGeofenceMask ? "Geofence Mask Enabled" : "Geofence Mask Disabled",
                  description: !showGeofenceMask
                    ? "External territory dimmed; community perimeter highlighted."
                    : "Full surrounding terrain displayed.",
                });
              }}
              className={`flex items-center gap-1 px-2.5 py-1.5 rounded-full text-xs font-medium transition-all border-l pl-2 ${
                showGeofenceMask
                  ? 'text-emerald-600 bg-emerald-500/10 font-bold'
                  : 'text-muted-foreground hover:text-foreground'
              }`}
              title="Toggle Geofence External Mask"
            >
              {showGeofenceMask ? <Eye className="h-3.5 w-3.5" /> : <EyeOff className="h-3.5 w-3.5" />}
              <span className="hidden sm:inline">Perimeter Mask</span>
            </button>

            {/* Drop Pin / Click-to-Place Mode Toggle */}
            <button
              onClick={() => {
                const nextState = !isClickToPlacePinActive;
                setIsClickToPlacePinActive(nextState);
                if (nextState) {
                  toast({
                    title: "Click-to-Place Active",
                    description: "Click anywhere on the map to place a Green Security Gate, Red Community Center, or Blue Park pin.",
                  });
                }
              }}
              className={`flex items-center gap-1 px-2.5 py-1.5 rounded-full text-xs font-medium transition-all border-l pl-2 ${
                isClickToPlacePinActive
                  ? 'bg-amber-500 text-white font-bold shadow-sm animate-pulse'
                  : 'text-muted-foreground hover:text-foreground'
              }`}
              title="Click on map to drop pin"
            >
              <MapPin className="h-3.5 w-3.5" />
              <span className="hidden sm:inline">{isClickToPlacePinActive ? 'Click Map...' : 'Drop Pin'}</span>
            </button>

            {/* Quick Add Pin Dialog Trigger */}
            <button
              onClick={() => {
                setAddPinCoords(null);
                setIsAddPinOpen(true);
              }}
              className="flex items-center gap-1 px-3 py-1.5 rounded-full text-xs font-semibold bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm transition-all"
              title="Add Security Gate, Community Center, or Park Pin"
            >
              <Plus className="h-3.5 w-3.5" />
              <span>Add Pin</span>
            </button>
          </div>
        </div>

        {/* Floating Active Placement Mode Guidance Banner */}
        {isClickToPlacePinActive && (
          <div className="absolute top-20 left-1/2 -translate-x-1/2 z-[401] bg-amber-500 text-white px-4 py-2 rounded-full shadow-xl text-xs font-semibold flex items-center gap-2 animate-in fade-in slide-in-from-top-3 pointer-events-auto border-2 border-white/40">
            <MapPin className="h-4 w-4 animate-bounce" />
            <span>Click anywhere on the map to place a Landmark Pin (Green / Red / Blue)</span>
            <button 
              onClick={() => setIsClickToPlacePinActive(false)} 
              className="ml-2 bg-black/20 hover:bg-black/40 rounded-full w-5 h-5 flex items-center justify-center text-xs font-bold"
              title="Cancel Pin Placement"
            >
              ✕
            </button>
          </div>
        )}

        {/* Floating Google-Style Custom Controls (Bottom-Right) */}
        <div className="absolute bottom-6 right-4 z-[400] flex flex-col items-center gap-2">
          <div className="bg-card/95 backdrop-blur-md border border-border shadow-lg rounded-xl overflow-hidden flex flex-col">
            <Button
              variant="ghost"
              size="icon"
              onClick={() => handleZoom(1)}
              className="h-9 w-9 rounded-none border-b hover:bg-muted text-foreground"
              aria-label="Zoom In"
            >
              <Plus className="h-4 w-4" />
            </Button>
            <Button
              variant="ghost"
              size="icon"
              onClick={() => handleZoom(-1)}
              className="h-9 w-9 rounded-none hover:bg-muted text-foreground"
              aria-label="Zoom Out"
            >
              <Minus className="h-4 w-4" />
            </Button>
          </div>

          <Button
            variant="outline"
            size="icon"
            onClick={handleRecenter}
            className="h-9 w-9 rounded-xl bg-card/95 backdrop-blur-md shadow-lg hover:bg-muted text-foreground"
            title="Re-center on Community Centroid"
            aria-label="Re-center map"
          >
            <Crosshair className="h-4 w-4 text-primary" />
          </Button>

          <Button
            variant="outline"
            size="icon"
            onClick={() => {
              setIsLocked(!isLocked);
              toast({
                title: isLocked ? "Geofence Boundary Unlocked" : "Strict Geofence Lock Activated",
                description: isLocked
                  ? "Camera can now explore beyond the community perimeter."
                  : "Camera locked strictly inside the geofence perimeter.",
              });
            }}
            className={`h-9 w-9 rounded-xl bg-card/95 backdrop-blur-md shadow-lg transition-colors ${
              isLocked ? 'text-emerald-600 border-emerald-500/40 bg-emerald-500/5' : 'text-amber-500'
            }`}
            title={isLocked ? "Unlock pan/zoom constraint" : "Lock strictly inside community geofence"}
            aria-label="Toggle boundary lock"
          >
            {isLocked ? <Lock className="h-4 w-4" /> : <Unlock className="h-4 w-4" />}
          </Button>

          <Button
            variant="outline"
            size="icon"
            onClick={toggleFullscreen}
            className="h-9 w-9 rounded-xl bg-card/95 backdrop-blur-md shadow-lg hover:bg-muted text-foreground"
            title="Toggle Fullscreen"
            aria-label="Toggle fullscreen"
          >
            {isFullscreen ? <Minimize2 className="h-4 w-4" /> : <Maximize2 className="h-4 w-4" />}
          </Button>
        </div>

        {/* Floating Active Coordinate & Geofence Inspector Card (Bottom-Left) */}
        {inspectedCoords && currentInspectGeofence && (
          <div className="absolute bottom-6 left-4 z-[400] max-w-sm bg-card/95 backdrop-blur-md border border-border shadow-xl rounded-2xl p-4 transition-all animate-in fade-in slide-in-from-bottom-2">
            <div className="flex items-center justify-between gap-2 mb-2">
              <div className="flex items-center gap-1.5">
                {currentInspectGeofence.isInside ? (
                  <Badge variant="outline" className="border-emerald-500/40 text-emerald-600 bg-emerald-500/10 text-[10px] font-bold flex items-center gap-1">
                    <CheckCircle2 className="h-3 w-3" />
                    INSIDE GEOFENCE
                  </Badge>
                ) : (
                  <Badge variant="outline" className="border-amber-500/40 text-amber-600 bg-amber-500/10 text-[10px] font-bold flex items-center gap-1">
                    <AlertTriangle className="h-3 w-3" />
                    OUTSIDE GEOFENCE
                  </Badge>
                )}
              </div>
              <button
                onClick={() => setInspectedCoords(null)}
                className="text-xs text-muted-foreground hover:text-foreground h-5 w-5 rounded-full flex items-center justify-center hover:bg-muted"
              >
                ✕
              </button>
            </div>

            <div className="space-y-1 mb-3">
              <p className="font-mono text-sm font-bold text-foreground">
                {inspectedCoords[0].toFixed(6)}°, {inspectedCoords[1].toFixed(6)}°
              </p>
              <p className="font-mono text-xs text-muted-foreground">
                {toDMS(inspectedCoords[0], true)} {toDMS(inspectedCoords[1], false)}
              </p>
            </div>

            <div className="grid grid-cols-2 gap-2 text-xs bg-muted/40 p-2.5 rounded-xl border mb-3">
              <div>
                <span className="text-muted-foreground block text-[10px] uppercase">Terrain Elevation</span>
                <span className="font-semibold text-foreground">~{estimateElevation(inspectedCoords[0], inspectedCoords[1])} m MSL</span>
              </div>
              <div>
                <span className="text-muted-foreground block text-[10px] uppercase">Fence Distance</span>
                <span className={`font-semibold ${currentInspectGeofence.isInside ? 'text-emerald-600' : 'text-amber-600'}`}>
                  {currentInspectGeofence.distanceMeters}m {currentInspectGeofence.isInside ? 'inside' : 'beyond'}
                </span>
              </div>
              <div className="col-span-2 pt-1 border-t mt-1">
                <span className="text-muted-foreground block text-[10px] uppercase">Nearest Ingress Portal</span>
                <span className="font-medium text-foreground text-xs">
                  {currentInspectGeofence.nearestGate.name} ({currentInspectGeofence.nearestGate.distance}m away)
                </span>
              </div>
            </div>

            <div className="flex items-center gap-2">
              <Button
                size="sm"
                variant="outline"
                className="flex-1 h-8 text-xs font-medium gap-1.5"
                onClick={() => copyCoordinates(inspectedCoords)}
              >
                <Copy className="h-3.5 w-3.5" />
                Copy
              </Button>
              <Button
                size="sm"
                className="flex-1 h-8 text-xs font-medium gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white"
                onClick={() => {
                  setAddPinCoords(inspectedCoords);
                  setIsAddPinOpen(true);
                }}
              >
                <Plus className="h-3.5 w-3.5" />
                Drop Pin Here
              </Button>
            </div>
          </div>
        )}

        {/* Map Canvas */}
        <CardContent className="p-0 overflow-hidden">
          {isClient && (
            <LeafletMapFixed
              center={center}
              zoom={16}
              mapType={mapType}
              onReady={setMapInstance}
              onMapClick={handleMapClick}
              onMove={(c, z) => {
                setActiveCenter(c);
                setActiveZoom(z);
              }}
              style={{ height: isFullscreen ? "100vh" : 640 }}
            />
          )}
        </CardContent>
      </Card>

      {/* ── Landmark & Geofence Analytics Panels ── */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        {/* Landmark Quick Navigation Cards */}
        <div className="md:col-span-2 space-y-3">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <h2 className="text-lg font-bold text-foreground flex items-center gap-2">
              <Building2 className="h-4 w-4 text-primary" />
              Community Pins & Infrastructure
            </h2>
            <span className="text-xs text-muted-foreground">Click card to pan map • Manage pins</span>
          </div>

          {/* Category Filter Tabs Bar */}
          <div className="flex items-center gap-1.5 overflow-x-auto pb-1 flex-wrap">
            <Button
              size="sm"
              variant={pinFilterCategory === 'all' ? 'default' : 'outline'}
              onClick={() => setPinFilterCategory('all')}
              className="h-7 text-xs rounded-full px-3 font-medium"
            >
              All Pins ({categoryCounts.all})
            </Button>
            <Button
              size="sm"
              variant={pinFilterCategory === 'Security Gate' ? 'default' : 'outline'}
              onClick={() => setPinFilterCategory('Security Gate')}
              className={`h-7 text-xs rounded-full px-3 font-medium gap-1.5 ${
                pinFilterCategory === 'Security Gate' ? 'bg-emerald-600 hover:bg-emerald-700 text-white font-semibold' : 'text-emerald-700 dark:text-emerald-400 border-emerald-500/30'
              }`}
            >
              <span className="w-2 h-2 rounded-full bg-emerald-500 shrink-0" />
              Security Gates ({categoryCounts['Security Gate']})
            </Button>
            <Button
              size="sm"
              variant={pinFilterCategory === 'Community Center' ? 'default' : 'outline'}
              onClick={() => setPinFilterCategory('Community Center')}
              className={`h-7 text-xs rounded-full px-3 font-medium gap-1.5 ${
                pinFilterCategory === 'Community Center' ? 'bg-rose-600 hover:bg-rose-700 text-white font-semibold' : 'text-rose-700 dark:text-rose-400 border-rose-500/30'
              }`}
            >
              <span className="w-2 h-2 rounded-full bg-rose-500 shrink-0" />
              Community Centers ({categoryCounts['Community Center']})
            </Button>
            <Button
              size="sm"
              variant={pinFilterCategory === 'Park' ? 'default' : 'outline'}
              onClick={() => setPinFilterCategory('Park')}
              className={`h-7 text-xs rounded-full px-3 font-medium gap-1.5 ${
                pinFilterCategory === 'Park' ? 'bg-blue-600 hover:bg-blue-700 text-white font-semibold' : 'text-blue-700 dark:text-blue-400 border-blue-500/30'
              }`}
            >
              <span className="w-2 h-2 rounded-full bg-blue-500 shrink-0" />
              Parks ({categoryCounts['Park']})
            </Button>

            <div className="ml-auto flex items-center gap-1.5 pl-2">
              <Button
                size="sm"
                variant="ghost"
                onClick={handleResetLandmarks}
                className="h-7 text-xs text-muted-foreground hover:text-foreground gap-1 px-2.5"
                title="Reset to standard pins"
              >
                <RotateCcw className="h-3 w-3" />
                <span>Reset</span>
              </Button>
              <Button
                size="sm"
                onClick={() => {
                  setAddPinCoords(null);
                  setIsAddPinOpen(true);
                }}
                className="h-7 text-xs bg-emerald-600 hover:bg-emerald-700 text-white gap-1 px-3 rounded-lg"
              >
                <Plus className="h-3.5 w-3.5" />
                <span>Add Pin</span>
              </Button>
            </div>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            {filteredLandmarks.map((lm) => {
              const isSecurity = lm.category === 'Security Gate';
              const isCommunityCenter = lm.category === 'Community Center';
              const badgeStyle = isSecurity
                ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500/30'
                : isCommunityCenter
                ? 'bg-rose-500/10 text-rose-700 dark:text-rose-400 border-rose-500/30'
                : 'bg-blue-500/10 text-blue-700 dark:text-blue-400 border-blue-500/30';

              return (
                <div
                  key={lm.id}
                  onClick={() => {
                    if (mapInstance) {
                      mapInstance.flyTo(lm.coordinates, 18, { duration: 1.2 });
                      handleMapClick(lm.coordinates);
                    }
                  }}
                  className="p-3.5 rounded-xl border bg-card hover:bg-muted/40 transition-all cursor-pointer shadow-sm hover:shadow group flex flex-col justify-between relative"
                >
                  <div>
                    <div className="flex items-start justify-between gap-2 mb-2">
                      <div className="flex items-center gap-2">
                        <span 
                          className="w-3.5 h-3.5 rounded-full shrink-0 shadow-sm border border-white/60 flex items-center justify-center text-[8px]"
                          style={{ backgroundColor: lm.color }}
                        />
                        <h3 className="font-semibold text-sm text-foreground group-hover:text-primary transition-colors">
                          {lm.name}
                        </h3>
                      </div>
                      <Badge variant="outline" className={`text-[10px] shrink-0 font-medium ${badgeStyle}`}>
                        {lm.category}
                      </Badge>
                    </div>

                    <p className="text-xs text-muted-foreground line-clamp-2 mb-3">
                      {lm.description}
                    </p>
                  </div>

                  <div className="flex items-center justify-between pt-2 border-t text-[11px] font-mono text-muted-foreground">
                    <span className="flex items-center gap-1.5">
                      <span className="font-medium text-foreground">{lm.elevation}m MSL</span>
                      <span>•</span>
                      <span>{lm.coordinates[0].toFixed(4)}°, {lm.coordinates[1].toFixed(4)}°</span>
                    </span>

                    <div className="flex items-center gap-2" onClick={(e) => e.stopPropagation()}>
                      <button
                        onClick={() => {
                          if (mapInstance) {
                            mapInstance.flyTo(lm.coordinates, 18, { duration: 1.2 });
                            handleMapClick(lm.coordinates);
                          }
                        }}
                        className="text-primary font-medium flex items-center gap-1 hover:underline text-xs"
                      >
                        Jump <Navigation className="h-3 w-3" />
                      </button>

                      <button
                        onClick={() => handleRemoveLandmark(lm.id, lm.name)}
                        className="text-muted-foreground hover:text-destructive p-1 rounded hover:bg-destructive/10 transition-colors"
                        title="Delete pin"
                      >
                        <Trash2 className="h-3.5 w-3.5" />
                      </button>
                    </div>
                  </div>
                </div>
              );
            })}

            {/* Quick Add Pin Card */}
            <div
              onClick={() => {
                setAddPinCoords(null);
                setIsAddPinOpen(true);
              }}
              className="p-3.5 rounded-xl border border-dashed border-emerald-500/40 bg-emerald-500/5 hover:bg-emerald-500/10 transition-all cursor-pointer flex flex-col items-center justify-center text-center gap-2 group min-h-[130px]"
            >
              <div className="w-8 h-8 rounded-full bg-emerald-500/15 flex items-center justify-center text-emerald-600 group-hover:scale-110 transition-transform">
                <Plus className="h-4 w-4" />
              </div>
              <div>
                <h3 className="font-semibold text-xs text-foreground">Add Custom Pin</h3>
                <p className="text-[11px] text-muted-foreground">🟢 Security Gate • 🔴 Community Center • 🔵 Park</p>
              </div>
            </div>
          </div>
        </div>

        {/* Topographic & Geofence Specification Card */}
        <div className="space-y-3">
          <div className="flex items-center justify-between">
            <h2 className="text-lg font-bold text-foreground flex items-center gap-2">
              <ShieldCheck className="h-4 w-4 text-emerald-500" />
              Geofence Parameters
            </h2>
            <div className="flex items-center gap-1.5">
              {isSystemAdmin && (
                <Button 
                  size="sm" 
                  variant="outline" 
                  onClick={() => setIsGeofenceDialogOpen(true)}
                  className="text-xs h-7 gap-1 border-emerald-500/40 text-emerald-600 bg-emerald-500/10 font-medium"
                >
                  <Settings2 className="h-3 w-3" />
                  Edit Geofence
                </Button>
              )}
              <Button 
                size="sm" 
                variant="outline" 
                onClick={handleSimulateGeofence}
                className="text-xs h-7 gap-1"
              >
                <Zap className="h-3.5 w-3.5 text-emerald-500" />
                Verify
              </Button>
            </div>
          </div>

          <Card className="border shadow-sm">
            <CardHeader className="p-4 pb-2">
              <CardTitle className="text-sm font-semibold flex items-center justify-between">
                <span>Perimeter Surveillance</span>
                <span className="text-xs font-mono text-emerald-600 bg-emerald-500/10 px-2 py-0.5 rounded-full">
                  ARMED
                </span>
              </CardTitle>
              <CardDescription className="text-xs">
                Active geofencing with {coordinates.length} radar corner nodes and 2 ingress control gates.
              </CardDescription>
            </CardHeader>
            <CardContent className="p-4 pt-2 space-y-3">
              <div className="flex justify-between items-center py-1.5 border-b text-xs">
                <span className="text-muted-foreground">Geofenced Area</span>
                <span className="font-semibold text-foreground font-mono">{areaAcres} Acres ({areaHectares} Ha)</span>
              </div>
              <div className="flex justify-between items-center py-1.5 border-b text-xs">
                <span className="text-muted-foreground">Perimeter Fence Length</span>
                <span className="font-semibold text-foreground font-mono">{perimeterMeters} meters</span>
              </div>
              <div className="flex justify-between items-center py-1.5 border-b text-xs">
                <span className="text-muted-foreground">Boundary Radar Beacons</span>
                <span className="font-semibold text-emerald-600 font-mono">{coordinates.length} Nodes Online</span>
              </div>
              <div className="flex justify-between items-center py-1.5 border-b text-xs">
                <span className="text-muted-foreground">Controlled Ingress Gates</span>
                <span className="font-semibold text-foreground font-mono">2 Gates (Main & North)</span>
              </div>
              <div className="flex justify-between items-center py-1.5 text-xs">
                <span className="text-muted-foreground">Centroid Benchmark</span>
                <button
                  onClick={() => copyCoordinates(center)}
                  className="font-mono text-primary hover:underline flex items-center gap-1"
                  title="Copy Centroid Coordinates"
                >
                  {center[0].toFixed(4)}°, {center[1].toFixed(4)}°
                  <Copy className="h-3 w-3" />
                </button>
              </div>
            </CardContent>
          </Card>

          {/* Geofence Rules Box */}
          <div className="p-3.5 rounded-xl bg-muted/40 border text-xs space-y-1.5 text-muted-foreground">
            <div className="flex items-center gap-1.5 font-semibold text-foreground text-xs">
              <Info className="h-4 w-4 text-emerald-500 shrink-0" />
              <span>Geofencing Active Rules</span>
            </div>
            <p>
              • <strong>Click anywhere</strong> on the map to test whether a coordinate is <em>Inside</em> or <em>Outside</em> the geofence perimeter.
            </p>
            <p>
              • <strong>Perimeter Mask</strong> automatically dims exterior terrain to clearly demarcate the residential community boundaries.
            </p>
            <p>
              • <strong>System Admin</strong> can click <em>Set Geofence Coordinates</em> to modify or load boundary presets in real time.
            </p>
          </div>
        </div>
      </div>
      </>
      )}

      {/* Dynamic Landmark Pin Dialog */}
      <AddLandmarkDialog
        open={isAddPinOpen}
        onOpenChange={setIsAddPinOpen}
        onAddLandmark={handleAddLandmark}
        initialCoordinates={addPinCoords}
      />

      {/* System Admin Geofence Coordination Modal */}
      {isSystemAdmin && (
        <SetGeofenceDialog
          open={isGeofenceDialogOpen}
          onOpenChange={setIsGeofenceDialogOpen}
        />
      )}
    </div>
  );
}

export default function MapPage() {
  return (
    <Suspense fallback={<div className="p-8 text-center text-xs text-muted-foreground">Loading Community Map...</div>}>
      <MapPageContent />
    </Suspense>
  );
}
