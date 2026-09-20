'use client';

import dynamic from 'next/dynamic';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { Badge } from "@/components/ui/badge";
import { 
  ArrowUpRight, 
  CalendarCheck, 
  Users, 
  DollarSign, 
  Phone, 
  MessageSquare, 
  Activity, 
  MoreHorizontal,
  Mountain,
  Settings2,
  ShieldCheck,
  MapPin,
  Radio,
  Compass,
  Crosshair,
  Layers,
  Map as MapIcon,
  CheckCircle2
} from "lucide-react";
import { Button } from "@/components/ui/button";
import Link from "next/link";
import Image from "next/image";
import { useAuth } from "@/context/auth-context";
import { ClientFormattedDate } from "@/components/client-formatted-date";
import { useEffect, useState, useRef, useMemo } from "react";
import type { Fundraiser, Donation } from "@/types";
import { FundraiserProgressCard } from "@/components/dashboard/fundraiser-progress-card";
import type L from 'leaflet';
import { useMap } from "@/context/map-context";
import { 
  COMMUNITY_LANDMARKS, 
  calculatePolygonMetrics, 
  toDMS,
  estimateElevation 
} from "@/lib/geofence-utils";
import { SetGeofenceDialog } from "@/components/dashboard/set-geofence-dialog";

// Client-only dynamic import for Leaflet map to prevent SSR hydration mismatches
const LeafletMapFixed = dynamic(() => import("@/components/dashboard/LeafletMapFixed"), {
  ssr: false,
  loading: () => (
    <div className="h-full min-h-[300px] w-full flex flex-col items-center justify-center bg-muted/20 gap-2 rounded-xl">
      <Mountain className="h-8 w-8 text-primary animate-pulse" />
      <p className="text-xs text-muted-foreground font-medium">Loading Google Terrain Map...</p>
    </div>
  ),
});

const mockTransactions = [
    { id: '1', homeowner: 'Marcus Vance (Lot 42)', date: '2025-07-20', amount: 5000.00, status: 'Paid' },
    { id: '2', homeowner: 'Olivia Davis (Lot 12)', date: '2025-07-19', amount: 5000.00, status: 'Paid' },
    { id: '3', homeowner: 'Carlos Gomez (Lot 21)', date: '2025-07-01', amount: 5000.00, status: 'Overdue' },
    { id: '4', homeowner: 'Sophia Taylor (Unit 15B)', date: '2025-07-18', amount: 5000.00, status: 'Paid' },
];

const mockPromotions = [
    {
        id: 'promo_1',
        title: 'Free Delivery Friday!',
        description: "From 'Local Eats' tonight only.",
        imageUrl: 'https://images.unsplash.com/photo-1513104890138-7c749659a591?w=100&q=80',
        aiHint: 'food delivery',
    },
    {
        id: 'promo_2',
        title: '50% off Gym Membership',
        description: "Join 'Community Fit' this month.",
        imageUrl: 'https://images.unsplash.com/photo-1534438327276-14e5300c3a48?w=100&q=80',
        aiHint: 'fitness gym',
    },
    {
        id: 'promo_3',
        title: 'Weekend Car Wash Special',
        description: "Get a full-service wash for $15.",
        imageUrl: 'https://images.unsplash.com/photo-1520340356584-f9917d1eea6f?w=100&q=80',
        aiHint: 'car wash',
    },
];

const mockFundraisers: Fundraiser[] = [
    {
        id: 'fr_1',
        title: 'New Playground Equipment',
        description: 'Help us build a new, modern playground for the community children with the latest safety features.',
        goal: 1500000,
        goalCurrency: 'JMD',
        startDate: new Date('2024-07-01T00:00:00Z'),
        endDate: new Date('2024-09-30T23:59:59Z'),
        status: 'Active',
    },
    {
        id: 'fr_4',
        title: 'Annual Community BBQ',
        description: 'Support our annual community get-together! Funds will go towards food, drinks, and entertainment for all residents.',
        goal: 250000,
        goalCurrency: 'JMD',
        startDate: new Date('2024-08-01T00:00:00Z'),
        endDate: new Date('2024-08-31T23:59:59Z'),
        status: 'Active',
    },
    {
        id: 'fr_2',
        title: 'Community Garden Expansion',
        description: 'We want to add 10 new plots to the community garden and install a new irrigation system.',
        goal: 400000,
        goalCurrency: 'JMD',
        startDate: new Date('2024-10-01T00:00:00Z'),
        endDate: new Date('2024-11-30T23:59:59Z'),
        status: 'Upcoming',
    },
];

const mockDonations: Donation[] = [
    { id: 'd_1', fundraiserId: 'fr_1', amount: 50, currency: 'USD', donorName: 'Marcus V.', isAnonymous: false, timestamp: new Date() },
    { id: 'd_2', fundraiserId: 'fr_1', amount: 100, currency: 'USD', donorName: 'Olivia D.', isAnonymous: false, timestamp: new Date() },
    { id: 'd_3', fundraiserId: 'fr_1', amount: 5000, currency: 'JMD', isAnonymous: true, timestamp: new Date() },
    { id: 'd_4', fundraiserId: 'fr_1', amount: 250, currency: 'USD', donorName: 'Carlos G.', isAnonymous: false, timestamp: new Date() },
    { id: 'd_5', fundraiserId: 'fr_1', amount: 75, currency: 'EUR', isAnonymous: true, timestamp: new Date() },
    { id: 'd_7', fundraiserId: 'fr_4', amount: 10000, currency: 'JMD', isAnonymous: true, timestamp: new Date() },
    { id: 'd_8', fundraiserId: 'fr_4', amount: 20, currency: 'USD', donorName: 'Aisha K.', isAnonymous: false, timestamp: new Date() },
];

export default function Dashboard() {
  const { user } = useAuth();
  const { coordinates } = useMap();
  const [currentMonth, setCurrentMonth] = useState('');
  const [isClient, setIsClient] = useState(false);
  const [activeLocation, setActiveLocation] = useState<string | null>('clubhouse');
  const [isGateOpen, setIsGateOpen] = useState(false);
  const [mapInstance, setMapInstance] = useState<L.Map | null>(null);
  const [isGeofenceDialogOpen, setIsGeofenceDialogOpen] = useState(false);

  const geoJsonLayerRef = useRef<L.GeoJSON | null>(null);
  const beaconsLayerRef = useRef<L.LayerGroup | null>(null);
  const markersLayerRef = useRef<L.LayerGroup | null>(null);

  const isSystemAdmin = user && user.role === 'System Admin';

  const { center, areaAcres, perimeterMeters } = useMemo(() => {
    return calculatePolygonMetrics(coordinates);
  }, [coordinates]);

  useEffect(() => {
    setIsClient(true);
    setCurrentMonth(new Date().toLocaleString('default', { month: 'long' }));
  }, []);

  // Synchronize geofence polygon on Leaflet map instance
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

      const newLayer = LInstance.geoJSON(geoJsonFeature, {
        style: {
          color: "#10B981",
          weight: 3,
          opacity: 0.95,
          dashArray: "6, 6",
          fillColor: "#10B981",
          fillOpacity: 0.08,
        }
      }).addTo(mapInstance);
      geoJsonLayerRef.current = newLayer;

      const bounds = newLayer.getBounds();
      mapInstance.fitBounds(bounds, { padding: [24, 24] });
    });
  }, [coordinates, mapInstance]);

  // Synchronize radar beacons on each boundary vertex
  useEffect(() => {
    if (!mapInstance) return;

    import('leaflet').then((Leaflet) => {
      const LInstance = Leaflet.default;
      if (beaconsLayerRef.current) {
        beaconsLayerRef.current.remove();
      }

      const group = LInstance.layerGroup().addTo(mapInstance);
      beaconsLayerRef.current = group;

      coordinates.forEach((coord, idx) => {
        const beaconHtml = `
          <div style="position: relative; display: flex; align-items: center; justify-content: center; width: 22px; height: 22px; cursor: pointer; transform: translate(-50%, -50%);">
            <div style="position: absolute; width: 18px; height: 18px; border-radius: 50%; background: rgba(16, 185, 129, 0.4); border: 1px solid #10B981;"></div>
            <div style="width: 8px; height: 8px; border-radius: 50%; background: #10B981; border: 2px solid #FFFFFF; box-shadow: 0 0 6px rgba(16,185,129,0.8); z-index: 2;"></div>
          </div>
        `;

        const beaconIcon = LInstance.divIcon({
          html: beaconHtml,
          className: 'geofence-beacon-icon',
          iconSize: [22, 22],
          iconAnchor: [11, 11],
        });

        LInstance.marker(coord, { icon: beaconIcon })
          .bindPopup(`<div style="padding: 4px; font-size: 11px;"><strong>Geofence Node #${idx + 1}</strong><br/><span style="font-family: monospace; color: #64748b;">${coord[0].toFixed(5)}°N, ${Math.abs(coord[1]).toFixed(5)}°W</span></div>`)
          .addTo(group);
      });
    });
  }, [coordinates, mapInstance]);

  // Synchronize Google-style landmark pins
  useEffect(() => {
    if (!mapInstance) return;

    import('leaflet').then((Leaflet) => {
      const LInstance = Leaflet.default;
      if (markersLayerRef.current) {
        markersLayerRef.current.remove();
      }

      const group = LInstance.layerGroup().addTo(mapInstance);
      markersLayerRef.current = group;

      COMMUNITY_LANDMARKS.forEach((landmark) => {
        const pinHtml = `
          <div style="position: relative; display: flex; flex-direction: column; align-items: center; cursor: pointer; transform: translate(-50%, -100%);">
            <svg width="28" height="34" viewBox="0 0 34 42" fill="none" xmlns="http://www.w3.org/2000/svg" style="filter: drop-shadow(0 3px 5px rgba(0,0,0,0.4));">
              <path d="M17 0C7.61116 0 0 7.61116 0 17C0 27.5 14.5 40.5 16.2 42C16.6 42.4 17.4 42.4 17.8 42C19.5 40.5 34 27.5 34 17C34 7.61116 26.3888 0 17 0Z" fill="${landmark.color}"/>
              <circle cx="17" cy="16" r="6" fill="#FFFFFF"/>
              <circle cx="17" cy="16" r="3.5" fill="${landmark.color}"/>
            </svg>
            <div style="background: rgba(15,23,42,0.85); color: #f8fafc; font-size: 10px; font-weight: 600; padding: 1px 6px; border-radius: 9999px; margin-top: -4px; border: 1px solid rgba(255,255,255,0.25); white-space: nowrap;">
              ${landmark.name.split(' ')[0]}
            </div>
          </div>
        `;

        const icon = LInstance.divIcon({
          html: pinHtml,
          className: 'google-landmark-pin',
          iconSize: [28, 34],
          iconAnchor: [14, 34],
          popupAnchor: [0, -34],
        });

        const marker = LInstance.marker(landmark.coordinates, { icon }).addTo(group);
        marker.bindPopup(`
          <div style="min-width: 190px; padding: 4px 6px; font-family: inherit;">
            <div style="font-size: 9px; font-weight: 700; color: ${landmark.color}; text-transform: uppercase;">${landmark.category}</div>
            <strong style="font-size: 12px; display: block; margin: 2px 0;">${landmark.name}</strong>
            <p style="font-size: 11px; color: #64748b; margin: 0 0 6px 0;">${landmark.description}</p>
            <div style="font-size: 10px; font-family: monospace; color: #64748b;">${landmark.coordinates[0].toFixed(5)}°, ${landmark.coordinates[1].toFixed(5)}°</div>
          </div>
        `);
      });
    });
  }, [mapInstance]);

  const handleSelectLandmark = (landmark: typeof COMMUNITY_LANDMARKS[0]) => {
    setActiveLocation(landmark.id);
    if (mapInstance) {
      mapInstance.flyTo(landmark.coordinates, 17, { duration: 1 });
    }
  };

  if (!isClient || !user) {
    return null;
  }

  const canViewActiveResidents = user && ['System Admin', 'Admin', 'Security'].includes(user.role);
  const canViewUpcomingVisitors = user && ['System Admin', 'Homeowner', 'Temporary Homeowner', 'Security'].includes(user.role);
  const canViewRecentVisitors = user && ['System Admin', 'Admin', 'Homeowner', 'Security'].includes(user.role);
  const canViewBilling = user && ['System Admin', 'Admin'].includes(user.role);
  const canViewFundraiser = user && ['System Admin', 'Admin', 'Homeowner', 'Temporary Homeowner'].includes(user.role);

  const totalCollected = mockTransactions.filter(t => t.status === 'Paid').reduce((acc, t) => acc + t.amount, 0);
  const outstandingDues = mockTransactions.filter(t => t.status !== 'Paid').reduce((acc, t) => acc + t.amount, 0);
  const activeFundraisers = mockFundraisers.filter(f => f.status === 'Active');

  return (
    <div className="flex flex-1 flex-col gap-8 pb-12 select-none">
      
      {/* ─── PRIMARY LAYOUT GRID ─── */}
      <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-5 xl:grid-cols-5">
        
        {/* Neighborhood Geofence Map (Left - Spans 3 columns) */}
        <Card className="lg:col-span-3 bg-card border-border text-card-foreground flex flex-col justify-between overflow-hidden shadow-sm relative">
          <div className="p-5 pb-3 flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-border bg-card/60 z-10">
            <div>
              <div className="flex items-center gap-2">
                <CardTitle className="text-lg font-bold tracking-tight text-foreground flex items-center gap-2">
                  <Mountain className="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                  Neighborhood Geofence Map
                </CardTitle>
                <Badge variant="outline" className="border-emerald-500/40 text-emerald-600 bg-emerald-500/10 text-[10px] font-bold">
                  Google Terrain
                </Badge>
              </div>
              <CardDescription className="text-xs text-muted-foreground flex items-center gap-2 mt-0.5">
                <span>Perimeter: {perimeterMeters}m</span>
                <span>•</span>
                <span>{areaAcres} Acres</span>
                <span>•</span>
                <span>{coordinates.length} Beacons</span>
              </CardDescription>
            </div>

            <div className="flex items-center gap-2 flex-wrap">
              {isSystemAdmin && (
                <Button 
                  size="sm" 
                  variant="outline" 
                  onClick={() => setIsGeofenceDialogOpen(true)}
                  className="h-8 text-xs font-semibold rounded-lg gap-1.5 border-primary/40 text-primary hover:bg-primary/10 transition-colors shadow-sm"
                >
                  <Settings2 className="h-3.5 w-3.5" />
                  <span>Set Geofence</span>
                </Button>
              )}

              <Link href="/dashboard/map">
                <Button size="sm" variant="ghost" className="h-8 text-xs font-semibold gap-1 text-muted-foreground hover:text-foreground">
                  <span>Full Map</span>
                  <ArrowUpRight className="h-3.5 w-3.5" />
                </Button>
              </Link>
            </div>
          </div>

          <CardContent className="p-4 flex flex-1 flex-col md:flex-row gap-4 relative justify-between items-stretch">
            {/* Map sidebar selector list */}
            <div className="flex flex-col justify-center gap-2 w-full md:w-44 shrink-0 z-10">
              <span className="text-[11px] font-bold uppercase tracking-wider text-muted-foreground px-1">
                Community Points
              </span>
              {COMMUNITY_LANDMARKS.map((landmark) => {
                const isActive = activeLocation === landmark.id;
                return (
                  <button
                    key={landmark.id}
                    type="button"
                    onClick={() => handleSelectLandmark(landmark)}
                    className={`p-2 rounded-lg border text-left flex items-center gap-2 transition-all duration-200 text-xs w-full ${
                      isActive 
                        ? 'bg-primary/15 border-primary text-primary font-semibold shadow-xs' 
                        : 'bg-muted/40 border-border text-muted-foreground hover:text-foreground hover:bg-muted/70'
                    }`}
                  >
                    <span 
                      className="w-2.5 h-2.5 rounded-full shrink-0" 
                      style={{ backgroundColor: landmark.color }}
                    />
                    <div className="truncate flex-1">
                      <div className="truncate font-medium text-foreground">{landmark.name}</div>
                      <div className="text-[10px] text-muted-foreground truncate">{landmark.category}</div>
                    </div>
                  </button>
                );
              })}
            </div>

            {/* Google Terrain Geofenced Map Canvas */}
            <div className="flex-1 min-h-[300px] h-[300px] md:h-auto rounded-xl border border-border overflow-hidden relative shadow-inner">
              <LeafletMapFixed
                center={center}
                zoom={16}
                mapType="terrain"
                onReady={setMapInstance}
                style={{ height: "100%", width: "100%", minHeight: "280px" }}
              />
            </div>
          </CardContent>

          {/* Bottom Action Area */}
          <div className="p-4 pt-3 border-t border-border flex items-center justify-between bg-muted/20">
            <span className="text-xs text-muted-foreground flex items-center gap-2 font-mono">
              <span className="h-2 w-2 rounded-full bg-emerald-500 inline-block animate-pulse"></span>
              <span>All nodes online</span>
              <span className="hidden sm:inline text-muted-foreground/60">•</span>
              <span className="hidden sm:inline text-[11px]">{toDMS(center[0], true)} {toDMS(center[1], false)}</span>
            </span>
            <Button 
              size="sm" 
              onClick={() => setIsGateOpen(!isGateOpen)}
              className={`text-xs font-semibold h-8 rounded-lg gap-1.5 transition-all duration-300 ${
                isGateOpen 
                  ? 'bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm' 
                  : 'bg-muted border border-border text-foreground hover:bg-muted/80'
              }`}
            >
              <Activity className="h-3.5 w-3.5" />
              <span>{isGateOpen ? 'Gate Opened' : 'Security Gate Locked'}</span>
            </Button>
          </div>
        </Card>

        {/* Community Announcements (Right - Spans 2 columns) */}
        <Card className="lg:col-span-2 bg-card border-border text-card-foreground flex flex-col justify-between overflow-hidden shadow-sm">
          <div className="p-6 pb-0 flex items-center justify-between">
            <CardTitle className="text-lg font-bold tracking-tight text-foreground">Community Announcements</CardTitle>
            <Button size="icon" variant="ghost" className="h-8 w-8 text-muted-foreground hover:text-foreground">
              <MoreHorizontal className="h-4 w-4" />
            </Button>
          </div>

          <CardContent className="p-6 flex-1 flex flex-col gap-4 overflow-y-auto max-h-[360px]">
            {/* BBQ Announcement Item */}
            <div className="bg-muted/30 border border-border rounded-xl overflow-hidden flex flex-col hover:border-border/80 transition-colors">
              <div className="h-24 bg-muted relative overflow-hidden shrink-0">
                <Image 
                  src="https://images.unsplash.com/photo-1555939594-58d7cb561ad1?w=400&q=80" 
                  alt="Annual BBQ Event"
                  fill
                  className="object-cover opacity-90"
                />
                <div className="absolute top-3 left-3 bg-background/95 border border-border rounded px-2 py-1 flex flex-col items-center shrink-0 min-w-[42px] leading-tight shadow-md text-foreground">
                  <span className="text-[10px] text-muted-foreground font-bold uppercase tracking-wider">Nov</span>
                  <span className="text-sm font-extrabold text-emerald-600 dark:text-emerald-400">15</span>
                </div>
              </div>
              <div className="p-3.5 flex flex-col gap-1">
                <h4 className="text-sm font-bold text-foreground leading-tight">Annual BBQ Event</h4>
                <p className="text-xs text-muted-foreground line-clamp-1">Join us for the community cookout at the Clubhouse.</p>
                <div className="flex items-center gap-2 mt-2">
                  <Badge variant="outline" className="text-[10px] py-0 h-4">Clubhouse</Badge>
                  <span className="text-[10px] text-muted-foreground">04:00 PM</span>
                </div>
              </div>
            </div>

            {/* Security Notice */}
            <div className="bg-muted/30 border border-border rounded-xl p-4 flex gap-3.5 hover:border-border/80 transition-colors">
              <div className="h-10 w-10 rounded-lg bg-primary/10 border border-primary/20 flex items-center justify-center shrink-0 text-primary font-bold text-lg">
                📢
              </div>
              <div className="flex-1 flex flex-col gap-1">
                <h4 className="text-sm font-bold text-foreground">Security Update</h4>
                <p className="text-xs text-muted-foreground line-clamp-2">New automated gate scanners are now online at the main entrance.</p>
                <span className="text-[10px] text-muted-foreground mt-1">2 hours ago</span>
              </div>
            </div>
          </CardContent>
        </Card>

      </div>

      {/* ─── VISITOR PASSES & RESIDENTS ROW ─── */}
      <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-5 xl:grid-cols-5">
        
        {/* Visitor Gate Passes (Spans 3 Columns) */}
        <Card className="lg:col-span-3 bg-card border-border text-card-foreground flex flex-col justify-between overflow-hidden shadow-sm">
          <div className="p-6 pb-0 flex items-center justify-between">
            <div>
              <CardTitle className="text-lg font-bold tracking-tight text-foreground">Visitor Gate Passes</CardTitle>
              <CardDescription className="text-xs text-muted-foreground">Active entry clearances authorized today</CardDescription>
            </div>
            <Button size="icon" variant="ghost" className="h-8 w-8 text-muted-foreground hover:text-foreground">
              <MoreHorizontal className="h-4 w-4" />
            </Button>
          </div>

          <CardContent className="p-6 flex flex-col md:flex-row gap-4 overflow-x-auto">
            {/* Pass 1 */}
            <div className="flex-1 min-w-[200px] bg-muted/30 border border-border rounded-xl p-4 flex flex-col gap-4 hover:border-primary/40 transition-all duration-200 relative group">
              <div className="flex items-center gap-3">
                <div className="h-10 w-10 rounded-full bg-primary/10 relative overflow-hidden border border-primary/20 flex items-center justify-center font-bold text-primary text-xs">
                  LJ
                </div>
                <div className="flex-1 flex flex-col leading-tight">
                  <span className="text-xs font-bold text-foreground group-hover:text-primary transition-colors">Liam Johnson</span>
                  <span className="text-[10px] text-muted-foreground">Lot 42 Guest</span>
                </div>
                <span className="h-2 w-2 rounded-full bg-emerald-500 shrink-0"></span>
              </div>
              <div className="flex flex-col gap-1.5 text-[10px] text-muted-foreground font-semibold border-t border-b border-border py-3">
                <div className="flex justify-between"><span>Arrival</span><span className="text-foreground">09:45 AM</span></div>
                <div className="flex justify-between"><span>Duration</span><span className="text-foreground">3 hrs</span></div>
                <div className="flex justify-between"><span>Vehicle</span><span className="text-foreground">Tesla Model S</span></div>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-[9px] font-bold text-emerald-700 dark:text-emerald-400 bg-emerald-500/15 border border-emerald-500/30 rounded-full px-2 py-0.5">● Approved</span>
              </div>
            </div>

            {/* Pass 2 */}
            <div className="flex-1 min-w-[200px] bg-muted/30 border border-border rounded-xl p-4 flex flex-col gap-4 hover:border-primary/40 transition-all duration-200 relative group">
              <div className="flex items-center gap-3">
                <div className="h-10 w-10 rounded-full bg-primary/10 relative overflow-hidden border border-primary/20 flex items-center justify-center font-bold text-primary text-xs">
                  NW
                </div>
                <div className="flex-1 flex flex-col leading-tight">
                  <span className="text-xs font-bold text-foreground group-hover:text-primary transition-colors">Noah Williams</span>
                  <span className="text-[10px] text-muted-foreground">Lot 12 Guest</span>
                </div>
                <span className="h-2 w-2 rounded-full bg-emerald-500 shrink-0"></span>
              </div>
              <div className="flex flex-col gap-1.5 text-[10px] text-muted-foreground font-semibold border-t border-b border-border py-3">
                <div className="flex justify-between"><span>Arrival</span><span className="text-foreground">10:30 AM</span></div>
                <div className="flex justify-between"><span>Duration</span><span className="text-foreground">2 hrs</span></div>
                <div className="flex justify-between"><span>Vehicle</span><span className="text-foreground">XYZ 1234</span></div>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-[9px] font-bold text-emerald-700 dark:text-emerald-400 bg-emerald-500/15 border border-emerald-500/30 rounded-full px-2 py-0.5">● Approved</span>
              </div>
            </div>

            {/* Pass 3 */}
            <div className="flex-1 min-w-[200px] bg-muted/30 border border-border rounded-xl p-4 flex flex-col gap-4 hover:border-primary/40 transition-all duration-200 relative group">
              <div className="flex items-center gap-3">
                <div className="h-10 w-10 rounded-full bg-primary/10 relative overflow-hidden border border-primary/20 flex items-center justify-center font-bold text-primary text-xs">
                  EW
                </div>
                <div className="flex-1 flex flex-col leading-tight">
                  <span className="text-xs font-bold text-foreground group-hover:text-primary transition-colors">Emma Watson</span>
                  <span className="text-[10px] text-muted-foreground">Lot 25 Guest</span>
                </div>
                <span className="h-2 w-2 rounded-full bg-emerald-500 shrink-0"></span>
              </div>
              <div className="flex flex-col gap-1.5 text-[10px] text-muted-foreground font-semibold border-t border-b border-border py-3">
                <div className="flex justify-between"><span>Arrival</span><span className="text-foreground">01:15 PM</span></div>
                <div className="flex justify-between"><span>Duration</span><span className="text-foreground">4 hrs</span></div>
                <div className="flex justify-between"><span>Vehicle</span><span className="text-foreground">Toyota RAV4</span></div>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-[9px] font-bold text-emerald-700 dark:text-emerald-400 bg-emerald-500/15 border border-emerald-500/30 rounded-full px-2 py-0.5">● Approved</span>
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Resident Directory (Spans 2 Columns) */}
        <Card className="lg:col-span-2 bg-card border-border text-card-foreground flex flex-col justify-between overflow-hidden shadow-sm">
          <div className="p-6 pb-0 flex items-center justify-between">
            <CardTitle className="text-lg font-bold tracking-tight text-foreground">Resident Spotlight</CardTitle>
            <Button size="icon" variant="ghost" className="h-8 w-8 text-muted-foreground hover:text-foreground">
              <MoreHorizontal className="h-4 w-4" />
            </Button>
          </div>

          <CardContent className="p-6 flex-1 grid grid-cols-2 gap-3 overflow-y-auto max-h-[300px]">
            {/* Resident 1 */}
            <div className="bg-muted/30 border border-border rounded-xl p-3 flex flex-col gap-2 hover:border-border/80 transition-colors">
              <div className="flex items-center gap-2">
                <div className="h-8 w-8 rounded-full bg-primary/10 border border-primary/20 flex items-center justify-center font-bold text-primary text-xs">
                  MV
                </div>
                <div className="flex-1 min-w-0">
                  <h5 className="text-xs font-bold text-foreground truncate">Marcus Vance</h5>
                  <p className="text-[9px] text-muted-foreground font-semibold uppercase">Lot 42 · Res</p>
                </div>
              </div>
              <div className="flex gap-1.5 mt-1 border-t border-border pt-2 justify-end">
                <Button size="icon" variant="ghost" className="h-6 w-6 rounded-md bg-muted hover:bg-primary hover:text-primary-foreground text-muted-foreground transition-all duration-200">
                  <Phone className="h-3 w-3" />
                </Button>
                <Button size="icon" variant="ghost" className="h-6 w-6 rounded-md bg-muted hover:bg-primary hover:text-primary-foreground text-muted-foreground transition-all duration-200">
                  <MessageSquare className="h-3 w-3" />
                </Button>
              </div>
            </div>

            {/* Resident 2 */}
            <div className="bg-muted/30 border border-border rounded-xl p-3 flex flex-col gap-2 hover:border-border/80 transition-colors">
              <div className="flex items-center gap-2">
                <div className="h-8 w-8 rounded-full bg-primary/10 border border-primary/20 flex items-center justify-center font-bold text-primary text-xs">
                  OD
                </div>
                <div className="flex-1 min-w-0">
                  <h5 className="text-xs font-bold text-foreground truncate">Olivia Davis</h5>
                  <p className="text-[9px] text-emerald-600 dark:text-emerald-400 font-bold uppercase">Lot 12 · Board</p>
                </div>
              </div>
              <div className="flex gap-1.5 mt-1 border-t border-border pt-2 justify-end">
                <Button size="icon" variant="ghost" className="h-6 w-6 rounded-md bg-muted hover:bg-primary hover:text-primary-foreground text-muted-foreground transition-all duration-200">
                  <Phone className="h-3 w-3" />
                </Button>
                <Button size="icon" variant="ghost" className="h-6 w-6 rounded-md bg-muted hover:bg-primary hover:text-primary-foreground text-muted-foreground transition-all duration-200">
                  <MessageSquare className="h-3 w-3" />
                </Button>
              </div>
            </div>

            {/* Resident 3 */}
            <div className="bg-muted/30 border border-border rounded-xl p-3 flex flex-col gap-2 hover:border-border/80 transition-colors">
              <div className="flex items-center gap-2">
                <div className="h-8 w-8 rounded-full bg-primary/10 border border-primary/20 flex items-center justify-center font-bold text-primary text-xs">
                  CG
                </div>
                <div className="flex-1 min-w-0">
                  <h5 className="text-xs font-bold text-foreground truncate">Carlos Gomez</h5>
                  <p className="text-[9px] text-muted-foreground font-semibold uppercase">Lot 21 · Res</p>
                </div>
              </div>
              <div className="flex gap-1.5 mt-1 border-t border-border pt-2 justify-end">
                <Button size="icon" variant="ghost" className="h-6 w-6 rounded-md bg-muted hover:bg-primary hover:text-primary-foreground text-muted-foreground transition-all duration-200">
                  <Phone className="h-3 w-3" />
                </Button>
                <Button size="icon" variant="ghost" className="h-6 w-6 rounded-md bg-muted hover:bg-primary hover:text-primary-foreground text-muted-foreground transition-all duration-200">
                  <MessageSquare className="h-3 w-3" />
                </Button>
              </div>
            </div>

            {/* Resident 4 */}
            <div className="bg-muted/30 border border-border rounded-xl p-3 flex flex-col gap-2 hover:border-border/80 transition-colors">
              <div className="flex items-center gap-2">
                <div className="h-8 w-8 rounded-full bg-primary/10 border border-primary/20 flex items-center justify-center font-bold text-primary text-xs">
                  ST
                </div>
                <div className="flex-1 min-w-0">
                  <h5 className="text-xs font-bold text-foreground truncate">Sophia Taylor</h5>
                  <p className="text-[9px] text-muted-foreground font-semibold uppercase">Unit 15B · Res</p>
                </div>
              </div>
              <div className="flex gap-1.5 mt-1 border-t border-border pt-2 justify-end">
                <Button size="icon" variant="ghost" className="h-6 w-6 rounded-md bg-muted hover:bg-primary hover:text-primary-foreground text-muted-foreground transition-all duration-200">
                  <Phone className="h-3 w-3" />
                </Button>
                <Button size="icon" variant="ghost" className="h-6 w-6 rounded-md bg-muted hover:bg-primary hover:text-primary-foreground text-muted-foreground transition-all duration-200">
                  <MessageSquare className="h-3 w-3" />
                </Button>
              </div>
            </div>
          </CardContent>
        </Card>

      </div>

      {/* ─── ADMINISTRATIVE & OPERATIONS DECK ─── */}
      <div className="border-t border-border pt-8 mt-4">
        
        <div className="mb-6 flex items-center gap-2">
          <Badge className="bg-emerald-500/15 border border-emerald-500/30 text-emerald-700 dark:text-emerald-400">
            <Activity className="h-3 w-3 mr-1" />
            Operations Portal
          </Badge>
          <h2 className="text-xl font-bold tracking-tight text-foreground">Financials, Fundraisers & Promotions</h2>
        </div>

        <div className="grid gap-4 md:grid-cols-2 md:gap-8 lg:grid-cols-4 mb-8">
          {canViewActiveResidents && (
            <Card className="bg-card border-border text-card-foreground shadow-sm">
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                  Active Residents
                </CardTitle>
                <Users className="h-4 w-4 text-primary" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-extrabold text-foreground">452</div>
                <p className="text-[10px] text-muted-foreground mt-1">
                  +12 from last month
                </p>
              </CardContent>
            </Card>
          )}

          {canViewBilling && (
            <>
              <Card className="bg-card border-border text-card-foreground shadow-sm">
                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                  <CardTitle className="text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                    Total Collected ({currentMonth})
                  </CardTitle>
                  <DollarSign className="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                </CardHeader>
                <CardContent>
                  <div className="text-2xl font-extrabold text-foreground">
                    JMD {totalCollected.toLocaleString('en-JM', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                  </div>
                  <p className="text-[10px] text-muted-foreground mt-1">
                    from {mockTransactions.filter(t => t.status === 'Paid').length} households
                  </p>
                </CardContent>
              </Card>

              <Card className="bg-card border-border text-card-foreground shadow-sm">
                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                  <CardTitle className="text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                    Outstanding Dues
                  </CardTitle>
                  <DollarSign className="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                  <div className="text-2xl font-extrabold text-foreground">
                    JMD {outstandingDues.toLocaleString('en-JM', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                  </div>
                  <p className="text-[10px] text-muted-foreground mt-1">
                    from {mockTransactions.filter(t => t.status !== 'Paid').length} household
                  </p>
                </CardContent>
              </Card>
            </>
          )}

          {canViewUpcomingVisitors && (
            <Card className="bg-card border-border text-card-foreground shadow-sm">
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                  Upcoming Visitors
                </CardTitle>
                <CalendarCheck className="h-4 w-4 text-primary" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-extrabold text-foreground">15</div>
                <p className="text-[10px] text-muted-foreground mt-1">
                  +3 scheduled today
                </p>
              </CardContent>
            </Card>
          )}
        </div>

        {canViewFundraiser && (
          <div className="grid gap-6 md:grid-cols-2 mb-8">
            {activeFundraisers.map((fundraiser) => (
              <FundraiserProgressCard 
                key={fundraiser.id}
                fundraiser={fundraiser} 
                donations={mockDonations.filter(d => d.fundraiserId === fundraiser.id)} 
              />
            ))}
          </div>
        )}

        <div className="grid gap-6 md:gap-8 lg:grid-cols-2 xl:grid-cols-3">
          {canViewRecentVisitors && (
            <Card className="xl:col-span-2 bg-card border-border text-card-foreground shadow-sm">
              <CardHeader className="flex flex-row items-center">
                <div className="grid gap-1">
                  <CardTitle className="text-base font-bold text-foreground">Recent Visitors Log</CardTitle>
                  <CardDescription className="text-xs text-muted-foreground">
                    A log of the most recent visitors approved at the gate.
                  </CardDescription>
                </div>
                <Button asChild size="sm" variant="outline" className="ml-auto gap-1 border-border text-foreground hover:bg-muted">
                  <Link href="/dashboard/visitors">
                    View All
                    <ArrowUpRight className="h-3.5 w-3.5" />
                  </Link>
                </Button>
              </CardHeader>
              <CardContent>
                <Table>
                  <TableHeader>
                    <TableRow className="border-border hover:bg-transparent">
                      <TableHead className="text-muted-foreground font-semibold">Visitor</TableHead>
                      <TableHead className="hidden md:table-cell text-muted-foreground font-semibold">Homeowner</TableHead>
                      <TableHead className="text-right text-muted-foreground font-semibold">Date</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    <TableRow className="border-border hover:bg-muted/30">
                      <TableCell>
                        <div className="font-semibold text-sm text-foreground">Liam Johnson</div>
                        <div className="text-xs text-muted-foreground">liam@example.com</div>
                      </TableCell>
                      <TableCell className="hidden md:table-cell text-xs text-muted-foreground">
                        Olivia Davis (Lot 42)
                      </TableCell>
                      <TableCell className="text-right text-xs"><ClientFormattedDate date={new Date("2025-06-23")} formatString="yyyy-MM-dd" /></TableCell>
                    </TableRow>
                    <TableRow className="border-border hover:bg-muted/30">
                      <TableCell>
                        <div className="font-semibold text-sm text-foreground">Noah Williams</div>
                        <div className="text-xs text-muted-foreground">noah@example.com</div>
                      </TableCell>
                      <TableCell className="hidden md:table-cell text-xs text-muted-foreground">
                        Marcus Vance (Lot 12)
                      </TableCell>
                      <TableCell className="text-right text-xs"><ClientFormattedDate date={new Date("2025-06-24")} formatString="yyyy-MM-dd" /></TableCell>
                    </TableRow>
                  </TableBody>
                </Table>
              </CardContent>
            </Card>
          )}

          {canViewBilling && (
            <Card className="bg-card border-border text-card-foreground shadow-sm">
              <CardHeader className="flex flex-row items-center">
                <div className="grid gap-1">
                  <CardTitle className="text-base font-bold text-foreground">Recent Payments</CardTitle>
                  <CardDescription className="text-xs text-muted-foreground">
                    A log of maintenance fees and dues.
                  </CardDescription>
                </div>
                <Button asChild size="sm" variant="outline" className="ml-auto gap-1 border-border text-foreground hover:bg-muted">
                  <Link href="/dashboard/billing">
                    View All
                    <ArrowUpRight className="h-3.5 w-3.5" />
                  </Link>
                </Button>
              </CardHeader>
              <CardContent>
                <Table>
                  <TableHeader>
                    <TableRow className="border-border hover:bg-transparent">
                      <TableHead className="text-muted-foreground font-semibold">Homeowner</TableHead>
                      <TableHead className="text-muted-foreground font-semibold">Status</TableHead>
                      <TableHead className="text-right text-muted-foreground font-semibold">Amount (JMD)</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {mockTransactions.slice(0,3).map(transaction => (
                      <TableRow key={transaction.id} className="border-border hover:bg-muted/30">
                        <TableCell>
                          <div className="font-semibold text-sm text-foreground">{transaction.homeowner.split('(')[0].trim()}</div>
                          <div className="text-xs text-muted-foreground">
                            {transaction.homeowner.match(/\(([^)]+)\)/)?.[1]}
                          </div>
                        </TableCell>
                        <TableCell>
                          <Badge className="text-[10px]" variant={transaction.status === 'Paid' ? 'secondary' : 'destructive'}>
                            {transaction.status}
                          </Badge>
                        </TableCell>
                        <TableCell className="text-right font-mono text-xs text-foreground">{transaction.amount.toFixed(2)}</TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </CardContent>
            </Card>
          )}

          <Card className="bg-card border-border text-card-foreground shadow-sm">
            <CardHeader>
              <CardTitle className="text-base font-bold text-foreground">Community Perks & Offers</CardTitle>
              <CardDescription className="text-xs text-muted-foreground">
                Special offers from verified local businesses.
              </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-4">
              {mockPromotions.map((promo) => (
                <div key={promo.id} className="flex items-center gap-4 group p-2 rounded-lg hover:bg-muted/50 transition-colors border border-transparent hover:border-border">
                  <div className="h-12 w-12 rounded-lg overflow-hidden relative border border-border shrink-0">
                    <Image 
                      alt={promo.title} 
                      className="object-cover" 
                      fill 
                      src={promo.imageUrl} 
                      data-ai-hint={promo.aiHint}
                    />
                  </div>
                  <div className="grid gap-0.5">
                    <p className="text-xs font-bold leading-snug group-hover:text-primary transition-colors text-foreground">
                      {promo.title}
                    </p>
                    <p className="text-[10px] text-muted-foreground leading-normal">
                      {promo.description}
                    </p>
                  </div>
                </div>
              ))}
            </CardContent>
          </Card>

        </div>

      </div>

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
