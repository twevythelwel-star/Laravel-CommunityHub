
import { useState, useEffect, useMemo } from 'react';
import dynamic from '@/lib/dynamic';
import { useAuth } from '@/context/auth-context';
import { useToast } from '@/hooks/use-toast';
import { 
  BoundaryPoint,
  BoundaryConfig,
  CommunityInfo
} from '@/lib/boundary-manager/types';
import { 
  INITIAL_BOUNDARY_POINTS,
  validateBoundary,
  saveDraftDebounced,
  publishBoundary,
  parseCoordinateString,
  parseBulkCoordinates
} from '@/lib/boundary-manager/service';
import { toDMS } from '@/lib/geofence-utils';
import { Card, CardContent, CardDescription, CardHeader, CardTitle, CardFooter } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Textarea } from '@/components/ui/textarea';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { 
  ShieldCheck, 
  MapPin, 
  Plus, 
  Trash2, 
  Save, 
  CheckCircle2, 
  AlertTriangle, 
  History, 
  Lock, 
  Unlock, 
  Compass, 
  Building2,
  Clipboard,
  FileText,
  MousePointerClick,
  Check,
  Edit3,
  Crosshair
} from 'lucide-react';

// Dynamic import for Leaflet boundary map
const BoundaryPointMap = dynamic(() => import('./boundary-point-map'), {
  ssr: false,
  loading: () => (
    <div className="h-[540px] w-full flex flex-col items-center justify-center bg-muted/20 gap-3 rounded-2xl border">
      <Compass className="h-8 w-8 text-primary animate-spin" />
      <p className="text-sm text-muted-foreground font-medium">Loading Boundary Mapping Engine...</p>
    </div>
  ),
});

/**
 * Community boundary editor.
 *
 * Previously self-contained: it booted from
 * `localStorage['community-boundary-config-v2']` and wrote back to it on every
 * edit, so each administrator edited a private copy and "publishing" changed
 * nothing for anyone else. It now receives the server's config and writes
 * drafts back through BoundaryController.
 *
 * Local state is still the editor's source of truth while typing — the server
 * is written on a debounce, and a draft is not in force at the gates until it
 * is published.
 */
export type BoundaryPointManagerProps = {
  /** Latest draft (or published fallback) from the server. */
  initialConfig: BoundaryConfig;
  /** Community metadata, previously the hardcoded COMMUNITY_INFO constant. */
  community: CommunityInfo;
  /** Whether the viewer holds `manageBoundary`. */
  canManage?: boolean;
};

export default function BoundaryPointManager({
  initialConfig,
  community,
  canManage = false,
}: BoundaryPointManagerProps) {
  const { user } = useAuth();
  const { toast } = useToast();

  const [config, setConfig] = useState<BoundaryConfig>(initialConfig);
  const [points, setPoints] = useState<BoundaryPoint[]>(config.points);
  const [selectedPointIndex, setSelectedPointIndex] = useState<number | null>(0);
  const [activeTab, setActiveTab] = useState<'mapping' | 'audit'>('mapping');

  // String buffers for responsive manual typing without numeric parse locks
  const [inputBuffer, setInputBuffer] = useState<Record<number, { lat: string; lng: string }>>({});
  const [quickPasteInputs, setQuickPasteInputs] = useState<Record<number, string>>({});

  // Direct Point Coordinate Updater State (dedicated top-level control)
  const [directPasteInput, setDirectPasteInput] = useState<string>('');
  const [directLatInput, setDirectLatInput] = useState<string>('');
  const [directLngInput, setDirectLngInput] = useState<string>('');

  // Map view focus target (dynamically shifts view to Point 1 or selected coordinate)
  const [focusTarget, setFocusTarget] = useState<{ lat: number; lng: number; zoom?: number; timestamp: number } | null>(null);

  // Interactive map modes
  const [isClickToPlaceActive, setIsClickToPlaceActive] = useState<boolean>(false);
  const [isBulkModalOpen, setIsBulkModalOpen] = useState<boolean>(false);
  const [isPublishing, setIsPublishing] = useState<boolean>(false);
  const [bulkText, setBulkText] = useState<string>('');

  // Helper to sync local input buffer from points without circular dependency
  const syncInputBufferFromPoints = (pts: BoundaryPoint[]) => {
    const nextBuffer: Record<number, { lat: string; lng: string }> = {};
    pts.forEach((p, idx) => {
      nextBuffer[idx] = {
        lat: p.lat.toString(),
        lng: p.lng.toString(),
      };
    });
    setInputBuffer(nextBuffer);
  };

  // Load stored configuration on mount
  useEffect(() => {
    const loaded = initialConfig;
    setConfig(loaded);
    setPoints(loaded.points);
    syncInputBufferFromPoints(loaded.points);
    if (loaded.points.length > 0) {
      setDirectLatInput(loaded.points[0].lat.toString());
      setDirectLngInput(loaded.points[0].lng.toString());
      // Automatically shift map view to focus on Point 1 and hold coordinates
      setFocusTarget({
        lat: loaded.points[0].lat,
        lng: loaded.points[0].lng,
        zoom: 17,
        timestamp: Date.now(),
      });
    }
    // Re-seeds if the server sends a newer config (another admin published).
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [initialConfig.version, initialConfig.status]);

  // Synchronize direct updater when selected point changes
  useEffect(() => {
    const idx = selectedPointIndex ?? 0;
    const pt = points[idx];
    if (pt) {
      setDirectLatInput(pt.lat.toString());
      setDirectLngInput(pt.lng.toString());
    }
  }, [selectedPointIndex, points]);

  // Permission check: allow System Admin and Admin to manage boundaries
  const isSystemAdmin = user?.role === 'System Admin' || user?.role === 'Admin';
  /*
   * Was hardcoded `true` ("always allow editing in the management panel"), so
   * the permission checks below could never fire. It now reflects the server's
   * `manageBoundary` gate, which is the thing that actually decides.
   */
  const canEditBoundary = canManage;

  // Live validation calculations
  const validation = useMemo(() => {
    return validateBoundary(points);
  }, [points]);

  // Manual input handler for Latitude (updates buffer while typing without truncating decimals)
  const handleLatChange = (index: number, val: string) => {
    if (!canEditBoundary) return;
    setInputBuffer(prev => ({
      ...prev,
      [index]: {
        lat: val,
        lng: prev[index]?.lng ?? (points[index]?.lng.toString() || ''),
      },
    }));
  };

  // Manual input handler for Longitude (updates buffer while typing without truncating decimals)
  const handleLngChange = (index: number, val: string) => {
    if (!canEditBoundary) return;
    setInputBuffer(prev => ({
      ...prev,
      [index]: {
        lat: prev[index]?.lat ?? (points[index]?.lat.toString() || ''),
        lng: val,
      },
    }));
  };

  // Dedicated helper to commit coordinates, hold them in persistent storage, and shift map view to focus
  const commitPointCoordinates = (
    index: number,
    cleanLat: number,
    cleanLng: number,
    sourceDesc?: string
  ) => {
    let updatedPoints: BoundaryPoint[] = [];
    setPoints(prev => {
      const updated = [...prev];
      if (updated[index]) {
        updated[index] = { ...updated[index], lat: cleanLat, lng: cleanLng };
      }
      updatedPoints = updated;
      return updated;
    });

    // Update string buffers for editing without locking decimals
    setInputBuffer(prev => ({
      ...prev,
      [index]: { lat: cleanLat.toString(), lng: cleanLng.toString() },
    }));

    // If active point is this point, sync direct inputs
    if (selectedPointIndex === index) {
      setDirectLatInput(cleanLat.toString());
      setDirectLngInput(cleanLng.toString());
    }

    // Hold and persist new coordinates into local storage immediately until updated later
    setConfig(prev => {
      const nextPts = updatedPoints.length > 0 ? updatedPoints : prev.points;
      const updatedConfig: BoundaryConfig = {
        ...prev,
        points: nextPts,
        status: 'DRAFT',
      };
      saveDraftDebounced(updatedConfig.points);
      return updatedConfig;
    });

    // Shift map view area to focus on point 1 (or the target point being updated)
    setFocusTarget({
      lat: cleanLat,
      lng: cleanLng,
      zoom: 17,
      timestamp: Date.now(),
    });

    toast({
      title: `${points[index]?.label || `Point ${index + 1}`} Coordinates Updated & Held`,
      description: `${sourceDesc ? `${sourceDesc} — ` : ''}Map view shifted to Lat: ${cleanLat}, Lng: ${cleanLng}. Coordinates held in memory and local storage until updated.`,
    });
  };

  // Helper to shift map view to focus on any specific point
  const handleFocusPoint = (index: number) => {
    const pt = points[index];
    if (!pt) return;
    setSelectedPointIndex(index);
    setFocusTarget({
      lat: pt.lat,
      lng: pt.lng,
      zoom: 17,
      timestamp: Date.now(),
    });
    toast({
      title: `Map Focused on ${pt.label}`,
      description: `Shifted view to Lat: ${pt.lat}, Lng: ${pt.lng}`,
    });
  };

  // Commit point coordinates from card inputs (onBlur, Enter, or explicit Apply button)
  const handleCommitPoint = (index: number) => {
    if (!canEditBoundary) return;
    const current = inputBuffer[index];
    if (!current) return;

    const latNum = parseFloat(current.lat);
    const lngNum = parseFloat(current.lng);

    if (isNaN(latNum) || latNum < -90 || latNum > 90) {
      toast({
        variant: 'destructive',
        title: 'Invalid Latitude',
        description: `Latitude must be a valid number between -90° and 90°. Entered: "${current.lat}".`,
      });
      return;
    }

    if (isNaN(lngNum) || lngNum < -180 || lngNum > 180) {
      toast({
        variant: 'destructive',
        title: 'Invalid Longitude',
        description: `Longitude must be a valid number between -180° and 180°. Entered: "${current.lng}".`,
      });
      return;
    }

    const cleanLat = +latNum.toFixed(6);
    const cleanLng = +lngNum.toFixed(6);

    commitPointCoordinates(index, cleanLat, cleanLng, 'Card inputs applied');
  };

  // Apply Direct Paste from Google Maps to currently selected point
  const handleApplyDirectPaste = () => {
    if (!canEditBoundary) return;
    const idx = selectedPointIndex ?? 0;
    if (!directPasteInput.trim()) {
      toast({
        variant: 'destructive',
        title: 'Empty Coordinate Input',
        description: 'Please paste or type coordinates from Google Maps (e.g. 18.44976, -77.9144841).',
      });
      return;
    }

    const parsed = parseCoordinateString(directPasteInput);
    if (parsed) {
      setDirectPasteInput('');
      commitPointCoordinates(idx, parsed.lat, parsed.lng, 'Google Maps paste applied');
    } else {
      toast({
        variant: 'destructive',
        title: 'Could Not Parse Coordinates',
        description: 'Format unrecognized. Example: 18.44976, -77.9144841 or 18°28\'36.7"N 77°55\'33.7"W',
      });
    }
  };

  // Apply Direct Manual Lat/Lng from the dedicated top updater
  const handleApplyDirectManual = () => {
    if (!canEditBoundary) return;
    const idx = selectedPointIndex ?? 0;
    const latNum = parseFloat(directLatInput);
    const lngNum = parseFloat(directLngInput);

    if (isNaN(latNum) || latNum < -90 || latNum > 90) {
      toast({
        variant: 'destructive',
        title: 'Invalid Latitude',
        description: 'Latitude must be a valid number between -90° and 90°. Example: 18.449760',
      });
      return;
    }

    if (isNaN(lngNum) || lngNum < -180 || lngNum > 180) {
      toast({
        variant: 'destructive',
        title: 'Invalid Longitude',
        description: 'Longitude must be a valid number between -180° and 180°. Example: -77.914484',
      });
      return;
    }

    const cleanLat = +latNum.toFixed(6);
    const cleanLng = +lngNum.toFixed(6);

    commitPointCoordinates(idx, cleanLat, cleanLng, 'Direct updater applied');
  };

  // Single-line Quick Paste parser for an individual point card
  const handleQuickPaste = (index: number, pasteStr: string) => {
    if (!canEditBoundary || !pasteStr.trim()) return;
    const parsed = parseCoordinateString(pasteStr);
    if (parsed) {
      setQuickPasteInputs(prev => ({ ...prev, [index]: '' }));
      commitPointCoordinates(index, parsed.lat, parsed.lng, 'Single card paste applied');
    } else {
      toast({
        variant: 'destructive',
        title: 'Unrecognized Coordinate Format',
        description: 'Please enter decimal (e.g. 18.476861, -77.926028) or DMS (e.g. 18°28\'36.7"N 77°55\'33.7"W).',
      });
    }
  };

  // Handle map marker drag
  const handlePointDrag = (index: number, lat: number, lng: number) => {
    if (!canEditBoundary) return;
    let updatedPoints: BoundaryPoint[] = [];
    setPoints(prev => {
      const updated = [...prev];
      updated[index] = {
        ...updated[index],
        lat,
        lng,
      };
      updatedPoints = updated;
      return updated;
    });
    setInputBuffer(prev => ({
      ...prev,
      [index]: { lat: lat.toString(), lng: lng.toString() },
    }));
    if (selectedPointIndex === index) {
      setDirectLatInput(lat.toString());
      setDirectLngInput(lng.toString());
    }
    // Hold dragged coordinates in storage
    setConfig(prev => {
      const updatedConfig: BoundaryConfig = {
        ...prev,
        points: updatedPoints.length > 0 ? updatedPoints : prev.points,
        status: 'DRAFT',
      };
      saveDraftDebounced(updatedConfig.points);
      return updatedConfig;
    });
  };

  // Handle direct map click when Click-To-Place mode is active
  const handleMapClick = (lat: number, lng: number) => {
    if (!canEditBoundary || selectedPointIndex === null) return;
    if (isClickToPlaceActive) {
      commitPointCoordinates(selectedPointIndex, lat, lng, 'Map clicked location');
    }
  };

  // Add Point (4-8 rule: max 8)
  const handleAddPoint = () => {
    if (!canEditBoundary) return;
    if (points.length >= 8) {
      toast({
        variant: 'destructive',
        title: 'Maximum Points Reached (8 Max)',
        description: 'The community boundary manager restricts polygons to a maximum of 8 points.',
      });
      return;
    }

    const nextId = points.length + 1;
    const lastPoint = points[points.length - 1] || { lat: 18.476861, lng: -77.926028 };
    const angle = (nextId / 8) * 2 * Math.PI;
    const offsetLat = +(lastPoint.lat + 0.0006 * Math.sin(angle)).toFixed(6);
    const offsetLng = +(lastPoint.lng + 0.0006 * Math.cos(angle)).toFixed(6);

    const newPoint: BoundaryPoint = {
      id: nextId,
      label: `Point ${nextId}`,
      lat: offsetLat,
      lng: offsetLng,
      isOptional: nextId > 4,
    };

    const nextPts = [...points, newPoint];
    setPoints(nextPts);
    syncInputBufferFromPoints(nextPts);
    setSelectedPointIndex(points.length);
    const updatedCfg: BoundaryConfig = { ...config, points: nextPts, status: 'DRAFT' };
    setConfig(updatedCfg);
    saveDraftDebounced(updatedCfg.points);
    setFocusTarget({ lat: newPoint.lat, lng: newPoint.lng, zoom: 17, timestamp: Date.now() });

    toast({
      title: `Point ${nextId} Added`,
      description: `New boundary vertex created. Coordinates held in storage and focused on map.`,
    });
  };

  // Remove Point (4-8 rule: min 4)
  const handleRemovePoint = (index: number) => {
    if (!canEditBoundary) return;
    if (points.length <= 4) {
      toast({
        variant: 'destructive',
        title: 'Minimum 4 Points Required',
        description: 'A community boundary requires at least 4 points. Points 1 to 4 cannot be removed.',
      });
      return;
    }

    const updated = points.filter((_, i) => i !== index).map((p, i) => ({
      ...p,
      id: i + 1,
      label: `Point ${i + 1}`,
      isOptional: i >= 4,
    }));

    setPoints(updated);
    syncInputBufferFromPoints(updated);
    if (selectedPointIndex === index) {
      setSelectedPointIndex(Math.max(0, index - 1));
    }
    const updatedCfg: BoundaryConfig = { ...config, points: updated, status: 'DRAFT' };
    setConfig(updatedCfg);
    saveDraftDebounced(updatedCfg.points);

    toast({
      title: 'Point Removed',
      description: `Boundary resized to ${updated.length} points and updated in storage.`,
    });
  };

  // Load Preset
  const handleLoadPreset = (presetType: 'montego' | 'quad' | 'octagon') => {
    if (!canEditBoundary) return;

    let newPts: BoundaryPoint[] = [];
    if (presetType === 'montego') {
      newPts = INITIAL_BOUNDARY_POINTS;
    } else if (presetType === 'quad') {
      newPts = [
        { id: 1, label: 'Point 1', lat: 18.476861, lng: -77.926028, isOptional: false },
        { id: 2, label: 'Point 2', lat: 18.478350, lng: -77.926028, isOptional: false },
        { id: 3, label: 'Point 3', lat: 18.478350, lng: -77.923500, isOptional: false },
        { id: 4, label: 'Point 4', lat: 18.476861, lng: -77.923500, isOptional: false },
      ];
    } else if (presetType === 'octagon') {
      newPts = [
        { id: 1, label: 'Point 1', lat: 18.476861, lng: -77.926028, isOptional: false },
        { id: 2, label: 'Point 2', lat: 18.478100, lng: -77.927500, isOptional: false },
        { id: 3, label: 'Point 3', lat: 18.479400, lng: -77.926500, isOptional: false },
        { id: 4, label: 'Point 4', lat: 18.479600, lng: -77.924200, isOptional: false },
        { id: 5, label: 'Point 5', lat: 18.478400, lng: -77.922800, isOptional: true },
        { id: 6, label: 'Point 6', lat: 18.476000, lng: -77.922500, isOptional: true },
        { id: 7, label: 'Point 7', lat: 18.474600, lng: -77.924500, isOptional: true },
        { id: 8, label: 'Point 8', lat: 18.475200, lng: -77.926600, isOptional: true },
      ];
    }

    setPoints(newPts);
    syncInputBufferFromPoints(newPts);
    setSelectedPointIndex(0);
    const updatedCfg: BoundaryConfig = { ...config, points: newPts, status: 'DRAFT' };
    setConfig(updatedCfg);
    saveDraftDebounced(updatedCfg.points);
    setFocusTarget({ lat: newPts[0].lat, lng: newPts[0].lng, zoom: 17, timestamp: Date.now() });

    toast({
      title: 'Preset Boundary Loaded',
      description: `Loaded ${newPts.length}-point template. Focused on Point 1: ${newPts[0].lat}, ${newPts[0].lng}.`,
    });
  };

  // Bulk Coordinates Dialog Apply
  const handleApplyBulkText = () => {
    if (!canEditBoundary) return;
    const { points: parsedPoints } = parseBulkCoordinates(bulkText);
    
    if (parsedPoints.length < 4) {
      toast({
        variant: 'destructive',
        title: 'Minimum 4 Points Required',
        description: `Parsed ${parsedPoints.length} valid points from text. At least 4 points are required (maximum 8).`,
      });
      return;
    }

    const finalPoints = parsedPoints.slice(0, 8);
    setPoints(finalPoints);
    syncInputBufferFromPoints(finalPoints);
    setSelectedPointIndex(0);
    const updatedCfg: BoundaryConfig = { ...config, points: finalPoints, status: 'DRAFT' };
    setConfig(updatedCfg);
    saveDraftDebounced(updatedCfg.points);
    setFocusTarget({ lat: finalPoints[0].lat, lng: finalPoints[0].lng, zoom: 17, timestamp: Date.now() });
    setIsBulkModalOpen(false);

    toast({
      title: 'Bulk Coordinates Applied Successfully',
      description: `Loaded ${finalPoints.length} points and held in storage. Map focused on Point 1.`,
    });
  };

  // Open Bulk Editor with current coordinates pre-loaded
  const handleOpenBulkEditor = () => {
    const formatted = points.map(p => `${p.label}: ${p.lat}, ${p.lng}`).join('\n');
    setBulkText(formatted);
    setIsBulkModalOpen(true);
  };

  // Save Draft
  const handleSaveDraft = () => {
    if (!canEditBoundary) {
      toast({ variant: 'destructive', title: 'Permission Denied', description: 'Only Admins can modify boundaries.' });
      return;
    }

    const updatedConfig: BoundaryConfig = {
      ...config,
      status: 'DRAFT',
      points,
    };
    setConfig(updatedConfig);
    saveDraftDebounced(updatedConfig.points);

    toast({
      title: 'Draft Saved',
      description: `Saved ${points.length} boundary points locally as draft. Not yet published to live gates.`,
    });
  };

  // Save & Publish Boundary
  const handlePublishBoundary = () => {
    if (!canEditBoundary) {
      toast({
        variant: 'destructive',
        title: 'Permission Denied',
        description: 'Only Administrators possess authority to publish official community boundaries.',
      });
      return;
    }

    if (!validation.canPublish) {
      toast({
        variant: 'destructive',
        title: 'Boundary Validation Failed',
        description: validation.errors[0] || 'Points do not form a valid, non-intersecting polygon.',
      });
      return;
    }

    /*
     * Publishing is a server operation.
     *
     * The previous version built the next version number, the audit entry and a
     * PUBLISHED config here, then wrote the whole thing to localStorage — and
     * announced "Published!" regardless. Nothing reached another user, and the
     * version counter was whatever this browser happened to hold.
     *
     * The server now assigns the version, writes the audit row, supersedes the
     * previous boundary and re-validates the polygon, so the announcement below
     * only fires once it has actually happened.
     */
    setIsPublishing(true);

    publishBoundary(points, {
      notes: `Published from the boundary manager: ${validation.areaAcres} acres across ${points.length} vertices.`,
      onSuccess: () => {
        setIsPublishing(false);

        toast({
          title: 'Boundary Published',
          description:
            'The official geofence is now live across the community map, resident dashboards and the gate pass engine.',
        });
      },
      onError: (errors) => {
        setIsPublishing(false);

        toast({
          variant: 'destructive',
          title: 'Boundary Not Published',
          description:
            errors?.points ?? 'The server rejected this boundary. Check the point count and shape.',
        });
      },
    });
  };

  return (
    <div className="space-y-6">
      {/* ── System Admin Security & Authority Banner ── */}
      <div className="p-4 rounded-2xl border flex flex-col md:flex-row items-start md:items-center justify-between gap-4 bg-emerald-500/10 border-emerald-500/30 text-emerald-950 dark:text-emerald-100">
        <div className="flex items-center gap-3">
          <div className="p-2 rounded-xl bg-emerald-500/20 text-emerald-600">
            <ShieldCheck className="h-6 w-6" />
          </div>
          <div>
            <div className="flex items-center gap-2">
              <h2 className="text-base font-bold tracking-tight">
                Community Boundary Management & Coordinate Authority
              </h2>
              <Badge variant="outline" className="font-mono text-[10px] border-emerald-500/40 text-emerald-700 dark:text-emerald-300 bg-emerald-500/10">
                FULL EDIT &amp; COORDINATE UPDATE RIGHTS
              </Badge>
            </div>
            <p className="text-xs text-muted-foreground mt-0.5">
              Authorized to enter point coordinates, paste Google Maps locations (e.g. 18.44976, -77.9144841), drag terrain vertices, and publish geofence perimeters.
            </p>
          </div>
        </div>

        <div className="flex items-center gap-2 self-stretch md:self-auto flex-wrap">
          {isSystemAdmin && (
            <Button
              variant="outline"
              size="sm"
              onClick={handleOpenBulkEditor}
              className="text-xs h-8 gap-1.5 font-medium border-border"
            >
              <FileText className="h-3.5 w-3.5 text-primary" />
              <span>Bulk Paste Coordinates</span>
            </Button>
          )}

          <Button
            variant="outline"
            size="sm"
            onClick={() => setActiveTab(activeTab === 'mapping' ? 'audit' : 'mapping')}
            className="text-xs h-8 gap-1.5 font-medium border-border"
          >
            <History className="h-3.5 w-3.5" />
            <span>{activeTab === 'mapping' ? 'View Audit History' : 'Back to Boundary Map'}</span>
          </Button>
        </div>
      </div>

      {/* ── Bulk Coordinates Paste Modal ── */}
      <Dialog open={isBulkModalOpen} onOpenChange={setIsBulkModalOpen}>
        <DialogContent className="sm:max-w-lg">
          <DialogHeader>
            <div className="flex items-center gap-2 mb-1">
              <span className="p-1.5 rounded-lg bg-primary/10 text-primary">
                <FileText className="h-5 w-5" />
              </span>
              <DialogTitle className="text-lg font-bold">Bulk Paste Coordinate List</DialogTitle>
            </div>
            <DialogDescription className="text-xs">
              Paste 4 to 8 coordinates (one per line). Supports raw decimal degrees (e.g. <code>18.476861, -77.926028</code>) or DMS (e.g. <code>18°28&apos;36.7&quot;N 77°55&apos;33.7&quot;W</code>) copied directly from Google Maps or surveyor reports.
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-2 py-2">
            <Label className="text-xs font-semibold">Coordinate Lines (4 to 8 required)</Label>
            <Textarea
              rows={8}
              value={bulkText}
              onChange={(e) => setBulkText(e.target.value)}
              placeholder={`Point 1: 18.476861, -77.926028\nPoint 2: 18.478350, -77.927200\nPoint 3: 18.479100, -77.924800\nPoint 4: 18.478400, -77.923200`}
              className="font-mono text-xs leading-relaxed"
            />
            <div className="text-[11px] text-muted-foreground">
              Tip: You can paste without &quot;Point 1:&quot; prefixes. Any standard Lat, Lng format will be automatically recognized.
            </div>
          </div>

          <DialogFooter className="gap-2 sm:gap-0">
            <Button variant="outline" size="sm" onClick={() => setIsBulkModalOpen(false)}>
              Cancel
            </Button>
            <Button size="sm" onClick={handleApplyBulkText} className="gap-1.5">
              <Check className="h-4 w-4" />
              <span>Apply Coordinates</span>
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {activeTab === 'audit' ? (
        /* ── Audit History View ── */
        <Card className="border shadow-md">
          <CardHeader>
            <div className="flex items-center justify-between">
              <div>
                <CardTitle className="text-xl font-bold flex items-center gap-2">
                  <History className="h-5 w-5 text-primary" />
                  <span>Boundary Modification Audit Ledger</span>
                </CardTitle>
                <CardDescription className="text-xs">
                  Cryptographically structured audit trail documenting all published versions and boundary revisions.
                </CardDescription>
              </div>
              <Badge variant="outline" className="font-mono text-xs">
                {config.auditHistory.length} Log Entries
              </Badge>
            </div>
          </CardHeader>
          <CardContent>
            <div className="rounded-xl border overflow-hidden">
              <table className="w-full text-left text-xs">
                <thead className="bg-muted/60 text-muted-foreground font-semibold border-b">
                  <tr>
                    <th className="p-3">Community</th>
                    <th className="p-3">Action</th>
                    <th className="p-3">Changed By</th>
                    <th className="p-3">Revision</th>
                    <th className="p-3">Vertices</th>
                    <th className="p-3">Area Enclosed</th>
                    <th className="p-3">Date / Timestamp</th>
                    <th className="p-3 text-right">Status</th>
                  </tr>
                </thead>
                <tbody className="divide-y">
                  {config.auditHistory.map((item) => (
                    <tr key={item.id} className="hover:bg-muted/30 transition-colors">
                      <td className="p-3 font-medium text-foreground">{item.community}</td>
                      <td className="p-3">
                        <span className="font-semibold text-emerald-600 dark:text-emerald-400">
                          {item.action}
                        </span>
                      </td>
                      <td className="p-3">
                        <span className="text-foreground font-medium">{item.changedBy}</span>
                        <span className="text-muted-foreground block text-[10px]">({item.role})</span>
                      </td>
                      <td className="p-3 font-mono font-bold">
                        v{item.previousVersion} → v{item.newVersion}
                      </td>
                      <td className="p-3 font-mono">{item.pointsCount} Points</td>
                      <td className="p-3 font-mono">{item.areaAcres} Acres</td>
                      <td className="p-3 text-muted-foreground font-mono">{item.timestamp}</td>
                      <td className="p-3 text-right">
                        <Badge variant="outline" className="font-mono text-[10px] border-emerald-500/40 text-emerald-600 bg-emerald-500/10">
                          PUBLISHED
                        </Badge>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </CardContent>
        </Card>
      ) : (
        /* ── Main Boundary Point Manager ── */
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
          
          {/* Left Column: Community Info & Boundary Point Inputs (5 cols) */}
          <div className="lg:col-span-5 space-y-6">

            {/* 1. Community Information Card */}
            <Card className="border shadow-sm">
              <CardHeader className="pb-3">
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <Building2 className="h-4 w-4 text-primary" />
                    <CardTitle className="text-base font-bold">Community Information</CardTitle>
                  </div>
                  <Badge variant="secondary" className="font-mono text-[10px]">
                    Version {config.version} ({config.status})
                  </Badge>
                </div>
              </CardHeader>
              <CardContent className="space-y-3 text-xs">
                <div className="grid grid-cols-2 gap-2">
                  <div className="p-2.5 rounded-lg bg-muted/40 border">
                    <span className="text-[10px] text-muted-foreground uppercase block font-semibold">Community</span>
                    <strong className="text-foreground text-xs">{community.name}</strong>
                  </div>
                  <div className="p-2.5 rounded-lg bg-muted/40 border">
                    <span className="text-[10px] text-muted-foreground uppercase block font-semibold">Jurisdiction</span>
                    <strong className="text-foreground text-xs">{community.jurisdiction}</strong>
                  </div>
                </div>

                <div className="p-2.5 rounded-lg bg-muted/40 border">
                  <span className="text-[10px] text-muted-foreground uppercase block font-semibold">Survey Datum</span>
                  <span className="text-foreground font-mono font-medium text-xs">{community.datum}</span>
                </div>

                {/* Benchmark Reference Coordinate Highlight */}
                <div className="p-3 rounded-xl bg-indigo-500/10 border border-indigo-500/30 text-indigo-950 dark:text-indigo-100">
                  <div className="flex items-center justify-between mb-1.5 flex-wrap gap-1">
                    <span className="text-[10px] font-bold uppercase tracking-wider text-indigo-700 dark:text-indigo-300 flex items-center gap-1">
                      <MapPin className="h-3 w-3" />
                      Point 1 Reference Benchmark
                    </span>
                    <div className="flex items-center gap-1.5">
                      <Badge variant="outline" className="text-[9px] h-4 border-indigo-500/40 text-indigo-700 dark:text-indigo-300">
                        {points[0] ? `${toDMS(points[0].lat, true)} ${toDMS(points[0].lng, false)}` : '18°28\'36.7"N 77°55\'33.7"W'}
                      </Badge>
                      <Button
                        type="button"
                        size="sm"
                        variant="secondary"
                        onClick={() => handleFocusPoint(0)}
                        className="h-5 px-2 text-[10px] gap-1 font-bold text-indigo-700 dark:text-indigo-300 bg-indigo-100/80 dark:bg-indigo-950/80 hover:bg-indigo-200 border border-indigo-300/40"
                        title="Shift map view directly to Point 1"
                      >
                        <Crosshair className="h-2.5 w-2.5" />
                        <span>Focus Point 1 on Map</span>
                      </Button>
                    </div>
                  </div>
                  <div className="font-mono text-xs font-bold text-foreground flex items-center justify-between">
                    <span>
                      Latitude: {points[0]?.lat ?? community.referenceCoord.lat} • Longitude: {points[0]?.lng ?? community.referenceCoord.lng}
                    </span>
                    <Badge variant="secondary" className="text-[9px] font-mono border text-emerald-600 bg-emerald-500/10">
                      ✓ Held in Storage
                    </Badge>
                  </div>
                  <p className="text-[11px] text-muted-foreground mt-0.5">
                    {community.referenceCoord.description}
                  </p>
                </div>
              </CardContent>
            </Card>

            {/* 2. Boundary Mapping (4-8 Points Rule with Manual Entry) */}
            <Card className="border shadow-sm">
              <CardHeader className="pb-3">
                <div className="flex items-center justify-between flex-wrap gap-2">
                  <div>
                    <CardTitle className="text-base font-bold flex items-center gap-2">
                      <MapPin className="h-4 w-4 text-emerald-600" />
                      <span>Boundary Mapping</span>
                    </CardTitle>
                    <CardDescription className="text-xs">
                      Enter coordinates manually, paste from Google Maps, or drag pins on terrain.
                    </CardDescription>
                  </div>

                  {/* 4-8 Points Rule Status Badge */}
                  <Badge 
                    variant={validation.pointCount >= 4 && validation.pointCount <= 8 ? 'default' : 'destructive'} 
                    className="font-mono text-xs"
                  >
                    {validation.pointCount} of 8 Points
                  </Badge>
                </div>
              </CardHeader>

              <CardContent className="space-y-4">
                {/* Template Preset Buttons & Manual Tools */}
                {canEditBoundary && (
                  <div className="space-y-2">
                    <div className="flex items-center justify-between">
                      <Label className="text-[11px] font-semibold text-muted-foreground">Boundary Presets</Label>
                      <button
                        type="button"
                        onClick={() => setIsClickToPlaceActive(!isClickToPlaceActive)}
                        className={`text-[11px] font-semibold flex items-center gap-1 px-2 py-0.5 rounded transition-colors ${
                          isClickToPlaceActive 
                            ? 'bg-emerald-500 text-white shadow-sm' 
                            : 'text-primary hover:underline'
                        }`}
                      >
                        <MousePointerClick className="h-3 w-3" />
                        <span>{isClickToPlaceActive ? 'Click-to-Place Active' : 'Enable Click-to-Place'}</span>
                      </button>
                    </div>

                    <div className="grid grid-cols-3 gap-1.5">
                      <Button 
                        type="button" 
                        variant="outline" 
                        size="sm" 
                        onClick={() => handleLoadPreset('montego')} 
                        className="text-[11px] h-8 px-2"
                      >
                        Standard (6 Pts)
                      </Button>
                      <Button 
                        type="button" 
                        variant="outline" 
                        size="sm" 
                        onClick={() => handleLoadPreset('quad')} 
                        className="text-[11px] h-8 px-2"
                      >
                        Min Quad (4 Pts)
                      </Button>
                      <Button 
                        type="button" 
                        variant="outline" 
                        size="sm" 
                        onClick={() => handleLoadPreset('octagon')} 
                        className="text-[11px] h-8 px-2"
                      >
                        Octagon (8 Pts)
                      </Button>
                    </div>
                  </div>
                )}

                {/* ── Direct Point Coordinate Updater Widget ── */}
                {canEditBoundary && (
                  <div className="p-3.5 rounded-xl bg-primary/5 border-2 border-primary/30 space-y-3">
                    <div className="flex items-center justify-between flex-wrap gap-2">
                      <div className="flex items-center gap-2">
                        <span className="p-1.5 rounded-lg bg-primary text-primary-foreground">
                          <MapPin className="h-4 w-4" />
                        </span>
                        <div>
                          <h4 className="font-bold text-xs text-foreground flex items-center gap-1.5">
                            Direct Coordinate Updater
                            <Badge variant="outline" className="text-[9px] h-4 font-mono border-primary/40 text-primary">
                              Target: {points[selectedPointIndex ?? 0]?.label}
                            </Badge>
                          </h4>
                          <p className="text-[10px] text-muted-foreground">Select a boundary point, then paste Google Maps coordinates or type exact values</p>
                        </div>
                      </div>

                      <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={() => handleFocusPoint(selectedPointIndex ?? 0)}
                        className="h-7 text-xs gap-1 font-semibold text-primary border-primary/30 hover:bg-primary/10"
                        title="Center map on currently selected point"
                      >
                        <Crosshair className="h-3.5 w-3.5" />
                        <span>Focus Point {(selectedPointIndex ?? 0) + 1} on Map</span>
                      </Button>
                    </div>

                    {/* Point Selectors */}
                    <div>
                      <div className="text-[10px] text-muted-foreground font-semibold uppercase mb-1">Select Point to Update:</div>
                      <div className="flex items-center gap-1.5 flex-wrap">
                        {points.map((pt, i) => (
                          <Button
                            key={pt.id}
                            type="button"
                            size="sm"
                            variant={selectedPointIndex === i ? 'default' : 'outline'}
                            onClick={() => setSelectedPointIndex(i)}
                            className="h-7 px-2.5 text-xs font-mono"
                          >
                            Point {i + 1}
                          </Button>
                        ))}
                      </div>
                    </div>

                    {/* Single-string paste from Google Maps */}
                    <div className="space-y-1">
                      <Label className="text-[11px] font-semibold text-foreground flex items-center justify-between">
                        <span>Paste from Google Maps (Coordinates or URL)</span>
                        <span className="text-[10px] font-mono text-muted-foreground font-normal">e.g. 18.44976, -77.9144841</span>
                      </Label>
                      <div className="flex gap-1.5">
                        <Input
                          type="text"
                          value={directPasteInput}
                          onChange={(e) => setDirectPasteInput(e.target.value)}
                          onKeyDown={(e) => {
                            if (e.key === 'Enter') {
                              e.preventDefault();
                              handleApplyDirectPaste();
                            }
                          }}
                          placeholder="Paste e.g. 18.44976, -77.9144841 or @18.44976,-77.9144841"
                          className="h-8 text-xs font-mono bg-background"
                        />
                        <Button
                          type="button"
                          size="sm"
                          onClick={handleApplyDirectPaste}
                          className="h-8 px-3 text-xs font-semibold gap-1 shrink-0 bg-primary"
                        >
                          <Check className="h-3.5 w-3.5" />
                          <span>Update Point {(selectedPointIndex ?? 0) + 1}</span>
                        </Button>
                      </div>
                    </div>

                    {/* Separate Lat and Lng inputs */}
                    <div className="pt-2 border-t border-border/60">
                      <div className="text-[10px] text-muted-foreground font-semibold uppercase mb-1">Or enter Latitude & Longitude manually:</div>
                      <div className="grid grid-cols-1 sm:grid-cols-12 gap-2 items-end">
                        <div className="sm:col-span-5">
                          <Label className="text-[10px] font-mono text-muted-foreground">Latitude (°N)</Label>
                          <Input
                            type="text"
                            inputMode="decimal"
                            value={directLatInput}
                            onChange={(e) => setDirectLatInput(e.target.value)}
                            onKeyDown={(e) => {
                              if (e.key === 'Enter') {
                                e.preventDefault();
                                handleApplyDirectManual();
                              }
                            }}
                            placeholder="e.g. 18.449760"
                            className="h-8 text-xs font-mono bg-background"
                          />
                        </div>
                        <div className="sm:col-span-5">
                          <Label className="text-[10px] font-mono text-muted-foreground">Longitude (°W)</Label>
                          <Input
                            type="text"
                            inputMode="decimal"
                            value={directLngInput}
                            onChange={(e) => setDirectLngInput(e.target.value)}
                            onKeyDown={(e) => {
                              if (e.key === 'Enter') {
                                e.preventDefault();
                                handleApplyDirectManual();
                              }
                            }}
                            placeholder="e.g. -77.914484"
                            className="h-8 text-xs font-mono bg-background"
                          />
                        </div>
                        <div className="sm:col-span-2">
                          <Button
                            type="button"
                            size="sm"
                            variant="secondary"
                            onClick={handleApplyDirectManual}
                            className="w-full h-8 text-xs font-semibold"
                          >
                            Apply
                          </Button>
                        </div>
                      </div>
                    </div>
                  </div>
                )}

                {/* Point Cards List (1 to 8) with Direct Manual Inputs & Paste Bars */}
                <div className="space-y-3 max-h-[440px] overflow-y-auto pr-1">
                  {points.map((pt, idx) => {
                    const isPointOne = idx === 0;
                    const isRequired = idx < 4;
                    const isSelected = selectedPointIndex === idx;
                    const currentLatInput = inputBuffer[idx]?.lat ?? pt.lat.toString();
                    const currentLngInput = inputBuffer[idx]?.lng ?? pt.lng.toString();

                    return (
                      <div
                        key={pt.id}
                        onClick={() => setSelectedPointIndex(idx)}
                        className={`p-3 rounded-xl border transition-all cursor-pointer ${
                          isSelected 
                            ? 'border-primary shadow-sm bg-primary/5 ring-1 ring-primary' 
                            : 'border-border bg-card hover:border-muted-foreground/30'
                        }`}
                      >
                        <div className="flex items-center justify-between mb-2">
                          <div className="flex items-center gap-2">
                            <span className={`w-6 h-6 rounded-full flex items-center justify-center font-bold text-xs text-white ${
                              isPointOne 
                                ? 'bg-indigo-600' 
                                : isSelected 
                                ? 'bg-primary' 
                                : 'bg-emerald-600'
                            }`}>
                              {idx + 1}
                            </span>
                            <span className="font-semibold text-xs text-foreground">
                              {pt.label} {isPointOne ? '(Supplied Benchmark)' : ''}
                            </span>
                          </div>

                          <div className="flex items-center gap-1.5">
                            <Button
                              type="button"
                              size="sm"
                              variant="ghost"
                              onClick={(e) => {
                                e.stopPropagation();
                                handleFocusPoint(idx);
                              }}
                              className="h-5 px-1.5 text-[10px] gap-1 text-primary hover:bg-primary/10 font-medium"
                              title={`Shift map view to focus on Point ${idx + 1}`}
                            >
                              <Crosshair className="h-3 w-3" />
                              <span>Focus</span>
                            </Button>

                            <Badge 
                              variant={isRequired ? 'default' : 'secondary'} 
                              className="text-[10px] h-5 font-mono"
                            >
                              {isRequired ? 'Required' : 'Optional'}
                            </Badge>

                            {/* Delete button (only for optional points 5..8, or when points > 4) */}
                            {canEditBoundary && points.length > 4 && idx >= 4 && (
                              <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                onClick={(e) => {
                                  e.stopPropagation();
                                  handleRemovePoint(idx);
                                }}
                                className="h-6 w-6 text-destructive hover:bg-destructive/10"
                                title="Remove Point"
                              >
                                <Trash2 className="h-3.5 w-3.5" />
                              </Button>
                            )}
                          </div>
                        </div>

                        {/* Manual Coordinate Input Fields */}
                        <div className="grid grid-cols-2 gap-2 text-xs">
                          <div>
                            <Label className="text-[10px] text-muted-foreground font-mono">Latitude (°N)</Label>
                            <Input
                              type="text"
                              inputMode="decimal"
                              value={currentLatInput}
                              disabled={!canEditBoundary}
                              onChange={(e) => handleLatChange(idx, e.target.value)}
                              onBlur={() => handleCommitPoint(idx)}
                              onKeyDown={(e) => {
                                if (e.key === 'Enter') {
                                  e.preventDefault();
                                  handleCommitPoint(idx);
                                }
                              }}
                              placeholder="e.g. 18.476861"
                              className="h-8 text-xs font-mono bg-background"
                            />
                          </div>
                          <div>
                            <Label className="text-[10px] text-muted-foreground font-mono">Longitude (°W)</Label>
                            <Input
                              type="text"
                              inputMode="decimal"
                              value={currentLngInput}
                              disabled={!canEditBoundary}
                              onChange={(e) => handleLngChange(idx, e.target.value)}
                              onBlur={() => handleCommitPoint(idx)}
                              onKeyDown={(e) => {
                                if (e.key === 'Enter') {
                                  e.preventDefault();
                                  handleCommitPoint(idx);
                                }
                              }}
                              placeholder="e.g. -77.926028"
                              className="h-8 text-xs font-mono bg-background"
                            />
                          </div>
                        </div>

                        {/* Apply Coordinate button for this point */}
                        {canEditBoundary && (
                          <div className="mt-2 flex items-center justify-between">
                            <span className="text-[10px] text-muted-foreground">Press Enter or click Apply to save coordinates</span>
                            <Button
                              type="button"
                              size="sm"
                              variant="secondary"
                              onClick={(e) => {
                                e.stopPropagation();
                                handleCommitPoint(idx);
                              }}
                              className="h-6 px-2.5 text-[10px] font-semibold"
                            >
                              Apply Point {idx + 1}
                            </Button>
                          </div>
                        )}

                        {/* Direct Google Maps Paste Box for this point */}
                        {canEditBoundary && (
                          <div className="mt-2 pt-2 border-t flex items-center gap-1">
                            <div className="relative flex-1">
                              <Input
                                type="text"
                                placeholder={`Paste "18.476861, -77.926028" or DMS...`}
                                value={quickPasteInputs[idx] || ''}
                                onChange={(e) => setQuickPasteInputs(prev => ({ ...prev, [idx]: e.target.value }))}
                                onKeyDown={(e) => {
                                  if (e.key === 'Enter') {
                                    e.preventDefault();
                                    handleQuickPaste(idx, quickPasteInputs[idx] || '');
                                  }
                                }}
                                className="h-7 text-[10px] font-mono pr-12 pl-2 bg-muted/30"
                              />
                              <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                onClick={() => handleQuickPaste(idx, quickPasteInputs[idx] || '')}
                                disabled={!quickPasteInputs[idx]?.trim()}
                                className="absolute right-0.5 top-0.5 h-6 px-2 text-[10px] font-semibold text-primary"
                              >
                                Set
                              </Button>
                            </div>
                          </div>
                        )}

                        {/* DMS Readout & Map Draggable notice */}
                        <div className="mt-1.5 flex items-center justify-between text-[10px] text-muted-foreground font-mono">
                          <span>DMS: {toDMS(pt.lat, true)} {toDMS(pt.lng, false)}</span>
                          {canEditBoundary && (
                            <span className="text-emerald-600 font-sans">
                              {isSelected && isClickToPlaceActive ? '🎯 Click map to place' : '⇄ Draggable on map'}
                            </span>
                          )}
                        </div>
                      </div>
                    );
                  })}
                </div>

                {/* Add Point Button (Active if points < 8) */}
                {canEditBoundary && (
                  <Button
                    type="button"
                    variant="outline"
                    onClick={handleAddPoint}
                    disabled={points.length >= 8}
                    className="w-full h-9 text-xs gap-1.5 font-semibold border-dashed hover:border-primary"
                  >
                    <Plus className="h-4 w-4" />
                    <span>
                      {points.length < 8 
                        ? `Add Point ${points.length + 1} (Up to 8 Points)` 
                        : 'Maximum 8 Points Limit Reached'}
                    </span>
                  </Button>
                )}
              </CardContent>
            </Card>

          </div>

          {/* Right Column: Preview Boundary Map & Save/Publish Controls (7 cols) */}
          <div className="lg:col-span-7 space-y-6">

            {/* 3. Preview Boundary Card */}
            <Card className="border shadow-md">
              <CardHeader className="pb-3">
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <Compass className="h-5 w-5 text-primary" />
                    <CardTitle className="text-lg font-bold">Preview Boundary</CardTitle>
                  </div>

                  {/* Usable Boundary Indicator */}
                  <Badge 
                    variant={validation.canPublish ? 'default' : 'destructive'} 
                    className="font-mono text-xs flex items-center gap-1"
                  >
                    {validation.canPublish ? (
                      <>
                        <CheckCircle2 className="h-3 w-3" />
                        <span>USABLE BOUNDARY</span>
                      </>
                    ) : (
                      <>
                        <AlertTriangle className="h-3 w-3" />
                        <span>INVALID PERIMETER</span>
                      </>
                    )}
                  </Badge>
                </div>
                <CardDescription className="text-xs">
                  {isClickToPlaceActive 
                    ? `🎯 Click anywhere on the map to set coordinates for ${points[selectedPointIndex || 0]?.label}.`
                    : canEditBoundary 
                    ? 'Connected sequentially. You can drag markers, manually type Lat/Lng, or paste from Google Maps.' 
                    : 'Interactive preview in read-only mode.'}
                </CardDescription>
              </CardHeader>

              <CardContent className="p-3">
                {/* Boundary Leaflet Map */}
                <BoundaryPointMap
                  points={points}
                  onPointDrag={handlePointDrag}
                  selectedPointIndex={selectedPointIndex}
                  onSelectPoint={setSelectedPointIndex}
                  onMapClick={handleMapClick}
                  isEditable={canEditBoundary}
                  status={config.status}
                  validation={validation}
                  focusTarget={focusTarget}
                />

                {/* Validation Warnings / Errors Banner */}
                {validation.errors.length > 0 && (
                  <div className="mt-3 p-3 rounded-xl bg-destructive/10 border border-destructive/30 text-destructive text-xs space-y-1">
                    <div className="font-bold flex items-center gap-1.5">
                      <AlertTriangle className="h-4 w-4" />
                      <span>Boundary Usability Issues Detected:</span>
                    </div>
                    {validation.errors.map((err, i) => (
                      <div key={i} className="pl-5">• {err}</div>
                    ))}
                  </div>
                )}

                {/* Enclosed Area Metrics Grid */}
                <div className="grid grid-cols-3 gap-3 mt-3 p-3 bg-muted/40 rounded-xl border text-center text-xs">
                  <div>
                    <span className="text-muted-foreground block text-[10px] uppercase font-semibold">Enclosed Area</span>
                    <strong className="text-foreground text-sm font-mono">{validation.areaAcres} Acres</strong>
                    <span className="text-[10px] text-muted-foreground block">({validation.areaHectares} Hectares)</span>
                  </div>
                  <div>
                    <span className="text-muted-foreground block text-[10px] uppercase font-semibold">Perimeter Length</span>
                    <strong className="text-foreground text-sm font-mono">{validation.perimeterMeters} m</strong>
                    <span className="text-[10px] text-muted-foreground block">Total Fence Line</span>
                  </div>
                  <div>
                    <span className="text-muted-foreground block text-[10px] uppercase font-semibold">Boundary Points</span>
                    <strong className="text-emerald-600 text-sm font-mono">{points.length} Vertices</strong>
                    <span className="text-[10px] text-muted-foreground block">4–8 Rule Enforced</span>
                  </div>
                </div>
              </CardContent>

              {/* 4. Save / Publish Footer */}
              <CardFooter className="pt-2 border-t flex items-center justify-between flex-wrap gap-3">
                <div className="flex items-center gap-2 text-xs text-muted-foreground">
                  <span className="w-2 h-2 rounded-full bg-emerald-500" />
                  <span>
                    Last Published: <strong>{config.lastPublishedAt || 'Never'}</strong> by {config.lastPublishedBy || 'None'}
                  </span>
                </div>

                <div className="flex items-center gap-2">
                  {canEditBoundary && (
                    <Button
                      type="button"
                      variant="outline"
                      size="sm"
                      onClick={handleSaveDraft}
                      className="text-xs h-9 gap-1.5"
                    >
                      <Save className="h-3.5 w-3.5" />
                      <span>Save Draft</span>
                    </Button>
                  )}

                  <Button
                    type="button"
                    variant="default"
                    size="sm"
                    onClick={handlePublishBoundary}
                    disabled={!canEditBoundary || !validation.canPublish}
                    className="text-xs h-9 gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold shadow-md"
                  >
                    <CheckCircle2 className="h-3.5 w-3.5" />
                    <span>Save & Publish Boundary</span>
                  </Button>
                </div>
              </CardFooter>
            </Card>

            {/* 5. Permission Enforcement Matrix Card */}
            <Card className="border shadow-sm">
              <CardHeader className="pb-2">
                <CardTitle className="text-xs font-bold uppercase tracking-wider text-muted-foreground">
                  Security & Permission Enforcement Matrix
                </CardTitle>
              </CardHeader>
              <CardContent className="text-xs">
                <div className="grid grid-cols-2 sm:grid-cols-5 gap-2 text-center">
                  <div className="p-2 rounded-lg bg-emerald-500/10 border border-emerald-500/20">
                    <strong className="block text-[11px] text-emerald-700 dark:text-emerald-300">System Admin</strong>
                    <span className="text-[10px] text-emerald-600 font-mono">Manual • Drag • Publish (✓)</span>
                  </div>
                  <div className="p-2 rounded-lg bg-muted/40 border">
                    <strong className="block text-[11px] text-foreground">Community Admin</strong>
                    <span className="text-[10px] text-muted-foreground font-mono">View Only (✗)</span>
                  </div>
                  <div className="p-2 rounded-lg bg-muted/40 border">
                    <strong className="block text-[11px] text-foreground">Security</strong>
                    <span className="text-[10px] text-muted-foreground font-mono">Operational Map (✗)</span>
                  </div>
                  <div className="p-2 rounded-lg bg-muted/40 border">
                    <strong className="block text-[11px] text-foreground">Homeowner</strong>
                    <span className="text-[10px] text-muted-foreground font-mono">No Modify (✗)</span>
                  </div>
                  <div className="p-2 rounded-lg bg-muted/40 border">
                    <strong className="block text-[11px] text-foreground">Renter</strong>
                    <span className="text-[10px] text-muted-foreground font-mono">No Modify (✗)</span>
                  </div>
                </div>
              </CardContent>
            </Card>

          </div>

        </div>
      )}
    </div>
  );
}
