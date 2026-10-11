import React, { useState, useRef, useEffect, useCallback } from 'react';
import { Head } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { useToast } from '@/hooks/use-toast';
import NfcTagReadButton from '@/components/dashboard/nfc-tag-read-button';
import {
  Scan,
  ShieldCheck,
  ShieldAlert,
  Clock,
  Car,
  User,
  MapPin,
  CheckCircle2,
  XCircle,
  Volume2,
  VolumeX,
  RotateCcw,
  Sparkles,
  ArrowRight,
  KeyRound,
  History,
  AlertTriangle,
  Nfc,
  Radio,
  LogIn,
  LogOut,
  Shield,
  FileCheck2,
  Camera,
  Zap,
  Eye,
  Activity,
  AlertOctagon,
  RefreshCw,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import axios from 'axios';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';

type GateOption = {
  id: string;
  name: string;
};

type RecentScan = {
  id: number;
  userName: string;
  userRole: string;
  result: string;
  gate: string;
  occurredAt: string;
  denyReason?: string | null;
};

type Props = {
  gates: GateOption[];
  recentScans: RecentScan[];
  guard: {
    name: string;
    role: string;
  };
};

type ScanOutcome = {
  decision: 'CHECK_IN' | 'CHECK_OUT' | 'REJECT';
  status: string;
  scanId: string | null;
  isPassbackViolation?: boolean;
  canOverride?: boolean;
  checkedInAt?: string | null;
  report: {
    passId: string;
    category: string;
    userName: string;
    property?: string;
    gate?: string;
    primaryReason?: string;
    denyReason?: string;
    isPassbackViolation?: boolean;
    isPassbackOverride?: boolean;
    overrideReason?: string;
    checkedInAt?: string;
    details?: string[];
  };
  occurredAt: string;
};

/** One barrier cycle as counted by a gate's occupancy sensor. */
interface SensorEvent {
  id: number;
  gate: string;
  is_tailgating: boolean;
  resolution_status: string;
  authorized_occupants: number;
  detected_occupants: number;
  occurred_at: string;
  sensor_metadata?: { device_name?: string } | null;
}

export default function GateScannerPage({ gates = [], recentScans = [], guard }: Props) {
  const { toast } = useToast();
  const [selectedGate, setSelectedGate] = useState<string>(gates[0]?.id || 'GATE-01');
  const [direction, setDirection] = useState<'in' | 'out'>('in');
  const [tokenInput, setTokenInput] = useState<string>('');
  const [lastScannedToken, setLastScannedToken] = useState<string>('');
  const [isScanning, setIsScanning] = useState<boolean>(false);
  const [isConfirming, setIsConfirming] = useState<boolean>(false);
  const [outcome, setOutcome] = useState<ScanOutcome | null>(null);
  const [soundEnabled, setSoundEnabled] = useState<boolean>(true);
  const [scansList, setScansList] = useState<RecentScan[]>(recentScans);

  // Anti-Passback Security Override Modal State
  const [showOverrideModal, setShowOverrideModal] = useState<boolean>(false);
  const [overrideReason, setOverrideReason] = useState<string>('');
  const [isOverriding, setIsOverriding] = useState<boolean>(false);

  // ANPR Plate Fast Scan State
  const [anprPlate, setAnprPlate] = useState<string>('XXX-1234');
  const [isAnprLoading, setIsAnprLoading] = useState<boolean>(false);
  const [anprLookupResult, setAnprLookupResult] = useState<any | null>(null);

  // Occupancy sensor readings, reported by the gate's own hardware.
  const [sensorEvents, setSensorEvents] = useState<SensorEvent[]>([]);
  const [sensorFeedFailed, setSensorFeedFailed] = useState<boolean>(false);
  const openSensorAlertIds = useRef<Set<number>>(new Set());

  const inputRef = useRef<HTMLInputElement>(null);

  // Focus input automatically on mount and after confirmation
  useEffect(() => {
    inputRef.current?.focus();
  }, [outcome]);

  // Synthetic Audio Chime using Web Audio API
  const playAudioCue = useCallback((success: boolean) => {
    if (!soundEnabled) return;
    try {
      const AudioContextClass = window.AudioContext || (window as unknown as { webkitAudioContext: typeof AudioContext }).webkitAudioContext;
      const ctx = new AudioContextClass();
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();

      osc.connect(gain);
      gain.connect(ctx.destination);

      if (success) {
        osc.type = 'sine';
        osc.frequency.setValueAtTime(587.33, ctx.currentTime); // D5
        osc.frequency.setValueAtTime(880, ctx.currentTime + 0.1); // A5
        gain.gain.setValueAtTime(0.3, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.4);
        osc.start(ctx.currentTime);
        osc.stop(ctx.currentTime + 0.4);
      } else {
        osc.type = 'sawtooth';
        osc.frequency.setValueAtTime(220, ctx.currentTime); // A3
        osc.frequency.setValueAtTime(164.81, ctx.currentTime + 0.15); // E3
        gain.gain.setValueAtTime(0.35, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.45);
        osc.start(ctx.currentTime);
        osc.stop(ctx.currentTime + 0.45);
      }
    } catch {
      // AudioContext unavailable or blocked by browser policy
    }
  }, [soundEnabled]);

  const handleScanSubmit = async (e?: React.FormEvent) => {
    if (e) e.preventDefault();
    const raw = tokenInput.trim();
    if (!raw) return;

    setLastScannedToken(raw);
    setIsScanning(true);
    try {
      const res = await axios.post('/dashboard/gate-pass/scan', {
        token: raw,
        gate: selectedGate,
        direction: direction,
      });

      const data = res.data;
      const isGranted = data.decision === 'CHECK_IN' || data.decision === 'CHECK_OUT';
      playAudioCue(isGranted);

      const isPassback = Boolean(
        data.isPassbackViolation ||
        data.report?.denyReason === 'ALREADY_CHECKED_IN' ||
        data.report?.primaryReason?.includes('Already Checked In')
      );

      const newOutcome: ScanOutcome = {
        decision: data.decision,
        status: data.report?.status || (isGranted ? 'VALID' : 'INVALID'),
        scanId: data.scanId || null,
        isPassbackViolation: isPassback,
        canOverride: data.canOverride ?? isPassback,
        checkedInAt: data.checkedInAt || data.report?.checkedInAt,
        report: data.report || {
          passId: 'UNKNOWN',
          category: 'VISITOR',
          userName: 'Unknown Visitor',
        },
        occurredAt: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' }),
      };

      setOutcome(newOutcome);

      // Add to local recent scan log
      setScansList((prev) => [
        {
          id: Date.now(),
          userName: newOutcome.report.userName,
          userRole: newOutcome.report.category,
          result: isGranted ? 'GRANT' : 'DENY',
          gate: gates.find((g) => g.id === selectedGate)?.name || selectedGate,
          occurredAt: 'Just now',
          denyReason: isGranted ? null : (newOutcome.report.primaryReason || 'Verification failed'),
        },
        ...prev.slice(0, 11),
      ]);

      setTokenInput('');
    } catch (err: unknown) {
      playAudioCue(false);
      const errMsg = axios.isAxiosError(err) && err.response?.data?.message
        ? err.response.data.message
        : 'Failed to process gate token. Please verify token syntax.';

      toast({
        variant: 'destructive',
        title: 'Scan Error',
        description: errMsg,
      });
    } finally {
      setIsScanning(false);
    }
  };

  const handleOverrideSubmit = async () => {
    if (!outcome) return;
    setIsOverriding(true);
    try {
      const res = await axios.post('/dashboard/gate-pass/scan', {
        token: lastScannedToken || outcome.report.passId,
        gate: selectedGate,
        direction: 'in',
        override_passback: true,
        override_reason: overrideReason.trim(),
      });

      const data = res.data;
      const isGranted = data.decision === 'CHECK_IN';
      playAudioCue(isGranted);

      const updatedOutcome: ScanOutcome = {
        decision: data.decision,
        status: data.report?.status || 'VALID',
        scanId: data.scanId || null,
        isPassbackViolation: false,
        canOverride: false,
        report: data.report || {
          passId: outcome.report.passId,
          category: outcome.report.category,
          userName: outcome.report.userName,
          primaryReason: data.report?.primaryReason,
        },
        occurredAt: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' }),
      };

      setOutcome(updatedOutcome);
      setShowOverrideModal(false);

      toast({
        title: 'Security Override Approved',
        description: `Anti-passback cleared for ${outcome.report.userName}. Open barrier confirmation enabled.`,
      });
    } catch (err: unknown) {
      playAudioCue(false);
      const errMsg = axios.isAxiosError(err) && err.response?.data?.message
        ? err.response.data.message
        : 'Security override failed. Ensure pass is active.';
      toast({
        variant: 'destructive',
        title: 'Override Error',
        description: errMsg,
      });
    } finally {
      setIsOverriding(false);
    }
  };

  const handleConfirmAdmission = async () => {
    if (!outcome?.scanId) return;

    setIsConfirming(true);
    try {
      await axios.post(`/dashboard/gate-pass/scans/${outcome.scanId}/confirm`, { accept: true });
      playAudioCue(true);

      toast({
        title: outcome.decision === 'CHECK_IN' ? 'Guest Admitted' : 'Exit Logged',
        description: `${outcome.report.userName} entry verified. Resident host notified.`,
      });

      // Clear outcome for next scan
      setOutcome(null);
      inputRef.current?.focus();
    } catch (err: unknown) {
      const errMsg = axios.isAxiosError(err) && err.response?.data?.message
        ? err.response.data.message
        : 'Scan expired or already confirmed.';

      toast({
        variant: 'destructive',
        title: 'Confirmation Failed',
        description: errMsg,
      });
    } finally {
      setIsConfirming(false);
    }
  };

  const handleAnprFastLookup = async (e?: React.FormEvent) => {
    if (e) e.preventDefault();
    if (!anprPlate.trim()) return;

    setIsAnprLoading(true);
    setAnprLookupResult(null);

    try {
      const res = await axios.get('/dashboard/vehicles/anpr/lookup', {
        params: { plate: anprPlate.trim() },
      });
      setAnprLookupResult(res.data);
      if (res.data.found && res.data.authorized) {
        playAudioCue(true);
        toast({
          title: 'ANPR Fast Lane: AUTHORIZED',
          description: `${res.data.owner?.name} (${res.data.vehicle?.make} ${res.data.vehicle?.model}) - Gate Barrier Opened.`,
        });
      } else {
        playAudioCue(false);
        toast({
          variant: 'destructive',
          title: 'ANPR Fast Lane: HOLD VEHICLE',
          description: res.data.message || 'Plate not authorized.',
        });
      }
    } catch (err: any) {
      // An unregistered plate is a 404 with its own message: hold the vehicle,
      // rather than reporting a lookup error.
      if (err.response?.status === 404 && err.response.data) {
        setAnprLookupResult(err.response.data);
        playAudioCue(false);
        toast({
          variant: 'destructive',
          title: 'ANPR Fast Lane: HOLD VEHICLE',
          description: err.response.data.message || 'Plate not authorized.',
        });
        return;
      }
      setAnprLookupResult({
        found: false,
        authorized: false,
        message: 'Plate lookup error.',
      });
    } finally {
      setIsAnprLoading(false);
    }
  };

  // Recent sensor readings; an alert that was not open before sounds the warning tone.
  const loadSensorEvents = useCallback(async () => {
    try {
      const res = await axios.get<{ events: SensorEvent[] }>('/dashboard/gate-sensor/recent');
      const events = res.data.events ?? [];
      const open = events.filter((e) => e.is_tailgating && e.resolution_status === 'UNRESOLVED');
      if (open.some((e) => !openSensorAlertIds.current.has(e.id))) {
        playAudioCue(false);
      }
      openSensorAlertIds.current = new Set(open.map((e) => e.id));
      setSensorEvents(events);
      setSensorFeedFailed(false);
    } catch {
      setSensorFeedFailed(true);
    }
  }, [playAudioCue]);

  useEffect(() => {
    loadSensorEvents();
    const timer = setInterval(loadSensorEvents, 15000);
    return () => clearInterval(timer);
  }, [loadSensorEvents]);

  const handleResolveSensorAlert = async (eventId: number, status: string) => {
    try {
      await axios.post('/dashboard/gate-sensor/resolve', {
        event_id: eventId,
        status,
        notes: `Handled by gate kiosk officer: ${guard?.name || 'Security'}`,
      });
      toast({
        title: status === 'CONFIRMED_VIOLATION' ? 'Patrol Dispatched' : 'Alert Dismissed',
        description: `Tailgating event marked ${status === 'CONFIRMED_VIOLATION' ? 'confirmed' : 'dismissed'}.`,
      });
      loadSensorEvents();
    } catch {
      toast({
        variant: 'destructive',
        title: 'Error resolving event',
      });
    }
  };

  const openSensorAlerts = sensorEvents.filter((e) => e.is_tailgating && e.resolution_status === 'UNRESOLVED');
  const latestSensorReading = sensorEvents[0];

  return (
    <DashboardLayout>
      <Head title="Gate Scanner Kiosk" />

      <div className="space-y-6 max-w-7xl mx-auto px-2 sm:px-4 py-4">
        {/* Kiosk Header */}
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-border/60 pb-4">
          <div>
            <div className="flex items-center gap-2">
              <span className="h-3 w-3 rounded-full bg-emerald-500 animate-pulse"></span>
              <h1 className="text-2xl font-bold tracking-tight text-foreground flex items-center gap-2">
                <Scan className="h-6 w-6 text-primary" />
                Gate Verification Kiosk
              </h1>
            </div>
            <p className="text-sm text-muted-foreground mt-0.5">
              High-speed cryptographic clearance station for security officers.
            </p>
          </div>

          <div className="flex flex-wrap items-center gap-3">
            {/* Lane Direction Toggle */}
            <div className="flex items-center p-1 bg-muted/80 rounded-xl border border-border/60 shadow-xs">
              <button
                type="button"
                onClick={() => setDirection('in')}
                className={cn(
                  'flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-all',
                  direction === 'in'
                    ? 'bg-emerald-600 text-white shadow-xs'
                    : 'text-muted-foreground hover:text-foreground'
                )}
              >
                <LogIn className="w-3.5 h-3.5" />
                <span>Inbound (Entry)</span>
              </button>
              <button
                type="button"
                onClick={() => setDirection('out')}
                className={cn(
                  'flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-all',
                  direction === 'out'
                    ? 'bg-sky-600 text-white shadow-xs'
                    : 'text-muted-foreground hover:text-foreground'
                )}
              >
                <LogOut className="w-3.5 h-3.5" />
                <span>Outbound (Exit)</span>
              </button>
            </div>

            {/* Gate Selector */}
            <div className="flex items-center gap-2 bg-muted/60 px-3 py-1.5 rounded-xl border border-border/60">
              <MapPin className="h-4 w-4 text-muted-foreground" />
              <label htmlFor="gate-selector" className="text-xs font-semibold text-muted-foreground uppercase">Gate:</label>
              <select
                id="gate-selector"
                value={selectedGate}
                onChange={(e) => setSelectedGate(e.target.value)}
                className="bg-transparent text-sm font-bold text-foreground focus:outline-hidden cursor-pointer"
              >
                {gates.map((g) => (
                  <option key={g.id} value={g.id} className="bg-popover text-foreground">
                    {g.name} ({g.id})
                  </option>
                ))}
              </select>
            </div>

            {/* Sound Toggle */}
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={() => setSoundEnabled(!soundEnabled)}
              title={soundEnabled ? 'Mute Audio Signals' : 'Enable Audio Signals'}
              className="gap-1.5 rounded-xl"
            >
              {soundEnabled ? <Volume2 className="h-4 w-4 text-primary" /> : <VolumeX className="h-4 w-4 text-muted-foreground" />}
              <span className="hidden sm:inline text-xs">{soundEnabled ? 'Sound On' : 'Muted'}</span>
            </Button>
          </div>
        </div>

        {/* Main Scanner Grid */}
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
          {/* Active Scan Station (8 cols) */}
          <div className="lg:col-span-8 space-y-6">
            {/* Direct Input, NFC Antenna & Scanner Gun Field */}
            <Card className="border-border shadow-xs">
              <CardHeader className="pb-3">
                <CardTitle className="text-base flex items-center justify-between">
                  <span className="flex items-center gap-2">
                    <span>Gate Authenticator</span>
                    <Badge variant="outline" className="border-emerald-500/30 text-emerald-400 bg-emerald-500/10 font-mono text-[11px] flex items-center gap-1">
                      <Radio className="w-3 h-3 animate-pulse" />
                      QR + NFC + Mobile Wallet
                    </Badge>
                  </span>
                  <div className="flex items-center gap-2">
                    <Badge
                      variant="outline"
                      className={cn(
                        'text-xs font-semibold px-2.5 py-0.5',
                        direction === 'in'
                          ? 'border-emerald-500/40 text-emerald-400 bg-emerald-500/10'
                          : 'border-sky-500/40 text-sky-400 bg-sky-500/10'
                      )}
                    >
                      {direction === 'in' ? '🟢 Inbound (Entry)' : '🔵 Outbound (Exit)'}
                    </Badge>
                    <Badge variant="outline" className="font-mono text-xs">
                      Scanner Ready
                    </Badge>
                  </div>
                </CardTitle>
                <CardDescription>
                  Scan QR code with camera, tap contactless NFC card/phone, or enter 6-digit offline PIN.
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-3">
                <form onSubmit={handleScanSubmit} className="flex gap-2">
                  <div className="relative flex-1">
                    <Input
                      ref={inputRef}
                      type="text"
                      value={tokenInput}
                      onChange={(e) => setTokenInput(e.target.value)}
                      placeholder="Scan QR or wallet pass, tap NFC, or enter PIN..."
                      className="font-mono text-base tracking-wide h-12 pr-10"
                      disabled={isScanning}
                      autoFocus
                    />
                    <KeyRound className="absolute right-3 top-3.5 h-5 w-5 text-muted-foreground/60" />
                  </div>
                  <Button
                    type="submit"
                    disabled={isScanning || !tokenInput.trim()}
                    className={cn(
                      'h-12 px-6 font-semibold',
                      direction === 'out' && 'bg-sky-600 hover:bg-sky-700 text-white'
                    )}
                  >
                    {isScanning
                      ? 'Verifying...'
                      : direction === 'in'
                        ? 'Verify Entry'
                        : 'Verify Exit'}
                  </Button>
                </form>

                <NfcTagReadButton
                  disabled={isScanning}
                  onRead={(code) => {
                    setTokenInput(code);
                    inputRef.current?.focus();
                  }}
                />

                {/* Contactless NFC Terminal Status */}
                <div className="flex items-center justify-between p-2.5 rounded-lg bg-slate-900/50 border border-slate-800 text-xs">
                  <div className="flex items-center gap-2 text-slate-300">
                    <div className="w-6 h-6 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center">
                      <Nfc className="w-3.5 h-3.5" />
                    </div>
                    <span>NFC Antenna Field: <strong className="text-emerald-400">Listening (13.56 MHz)</strong></span>
                  </div>
                  <span className="text-[11px] text-muted-foreground font-mono">
                    ISO 14443 Type A / Apple VAS / Google Smart Tap
                  </span>
                </div>
              </CardContent>
            </Card>

            {/* ANPR & Optical Sensor Systems Grid */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              {/* ANPR Plate Camera Lookup Card */}
              <Card className="border border-border/70 bg-card/80 shadow-xs">
                <CardHeader className="py-2.5 px-3.5 bg-muted/20 border-b border-border/40">
                  <div className="flex items-center justify-between">
                    <CardTitle className="text-xs font-bold flex items-center gap-1.5">
                      <Camera className="h-4 w-4 text-blue-400" />
                      ANPR Optical Camera
                    </CardTitle>
                    <Badge variant="outline" className="text-[10px] font-mono text-blue-400 border-blue-500/30">
                      Fast Lane Ready
                    </Badge>
                  </div>
                </CardHeader>
                <CardContent className="p-3 space-y-2.5">
                  <form onSubmit={handleAnprFastLookup} className="flex gap-2">
                    <Input
                      placeholder="e.g. XXX-1234 or 9821-JA"
                      value={anprPlate}
                      onChange={(e) => setAnprPlate(e.target.value.toUpperCase())}
                      className="font-mono text-xs uppercase h-8"
                    />
                    <Button
                      type="submit"
                      size="sm"
                      disabled={isAnprLoading || !anprPlate.trim()}
                      className="h-8 text-xs bg-blue-600 hover:bg-blue-500 text-white px-3"
                    >
                      {isAnprLoading ? <RefreshCw className="h-3 w-3 animate-spin" /> : 'Scan Plate'}
                    </Button>
                  </form>

                  {anprLookupResult && (
                    <div
                      className={cn(
                        'p-2 rounded-lg border text-xs space-y-1',
                        anprLookupResult.authorized
                          ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-300'
                          : 'bg-destructive/10 border-destructive/30 text-destructive'
                      )}
                    >
                      <div className="flex items-center justify-between font-bold text-[11px]">
                        <span>{anprLookupResult.authorized ? 'BOOM GATE OPEN — ANPR PASS' : 'ANPR HOLD — UNVERIFIED'}</span>
                        <span className="font-mono text-[10px]">{anprLookupResult.normalized_plate}</span>
                      </div>
                      {anprLookupResult.owner && (
                        <div className="text-[11px] text-foreground font-sans">
                          {anprLookupResult.owner.name} &bull; {anprLookupResult.vehicle?.color} {anprLookupResult.vehicle?.make} {anprLookupResult.vehicle?.model} &bull; {anprLookupResult.vehicle?.parking_location || 'Resident Bay'}
                        </div>
                      )}
                    </div>
                  )}
                </CardContent>
              </Card>

              {/* Tailgating & Optical Sensor Monitor Card */}
              <Card className="border border-border/70 bg-card/80 shadow-xs">
                <CardHeader className="py-2.5 px-3.5 bg-muted/20 border-b border-border/40">
                  <div className="flex items-center justify-between">
                    <CardTitle className="text-xs font-bold flex items-center gap-1.5">
                      <Activity className="h-4 w-4 text-amber-400" />
                      Optical Sensor & Tailgating
                    </CardTitle>
                    <Badge variant={openSensorAlerts.length ? 'destructive' : 'outline'} className="text-[10px] font-mono">
                      {openSensorAlerts.length ? `${openSensorAlerts.length} open` : 'Clear'}
                    </Badge>
                  </div>
                </CardHeader>
                <CardContent className="p-3 space-y-2.5">
                  {openSensorAlerts.map((alert) => (
                    <div key={alert.id} className="p-2 rounded-lg bg-destructive/15 border-2 border-destructive/60 text-destructive text-xs space-y-1.5">
                      <div className="flex items-center justify-between font-bold text-[11px]">
                        <span className="flex items-center gap-1">
                          <AlertOctagon className="h-3.5 w-3.5" /> Possible tailgating at {alert.gate}
                        </span>
                        <span className="text-[10px] font-mono">
                          {new Date(alert.occurred_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                        </span>
                      </div>
                      <p className="text-[11px] text-destructive-foreground dark:text-red-200">
                        Allowed {alert.authorized_occupants} &bull; counted {alert.detected_occupants}
                        {alert.sensor_metadata?.device_name ? ` (${alert.sensor_metadata.device_name})` : ''}
                      </p>
                      <div className="flex gap-1.5 pt-0.5">
                        <Button size="sm" variant="destructive" onClick={() => handleResolveSensorAlert(alert.id, 'CONFIRMED_VIOLATION')} className="h-6 text-[10px] px-2">
                          Dispatch Patrol
                        </Button>
                        <Button size="sm" variant="outline" onClick={() => handleResolveSensorAlert(alert.id, 'DISMISSED')} className="h-6 text-[10px] px-2 border-destructive/40 text-destructive">
                          Dismiss
                        </Button>
                      </div>
                    </div>
                  ))}

                  {openSensorAlerts.length === 0 && (
                    <p className="text-[11px] text-muted-foreground">
                      {sensorFeedFailed
                        ? 'Could not load sensor readings. Retrying…'
                        : latestSensorReading
                          ? `No open tailgating alerts. Last reading ${new Date(latestSensorReading.occurred_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })} at ${latestSensorReading.gate}.`
                          : 'No sensor readings yet. Gate sensors report here once registered (php artisan gate-devices:register).'}
                    </p>
                  )}
                </CardContent>
              </Card>
            </div>

            {/* Verification Result Display */}
            {outcome ? (
              <Card
                className={cn(
                  'border-2 shadow-lg transition-all duration-300',
                  outcome.decision === 'REJECT'
                    ? outcome.isPassbackViolation
                      ? 'border-amber-500 bg-amber-500/5 dark:bg-amber-950/20'
                      : 'border-rose-500 bg-rose-500/5 dark:bg-rose-950/20'
                    : 'border-emerald-500 bg-emerald-500/5 dark:bg-emerald-950/20'
                )}
              >
                <CardHeader className="pb-4 border-b border-border/40">
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                      {outcome.decision === 'REJECT' ? (
                        outcome.isPassbackViolation ? (
                          <div className="h-12 w-12 rounded-xl bg-amber-500/20 text-amber-500 flex items-center justify-center">
                            <AlertTriangle className="h-7 w-7 animate-pulse" />
                          </div>
                        ) : (
                          <div className="h-12 w-12 rounded-xl bg-rose-500/20 text-rose-600 dark:text-rose-400 flex items-center justify-center">
                            <XCircle className="h-7 w-7" />
                          </div>
                        )
                      ) : (
                        <div className="h-12 w-12 rounded-xl bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                          <CheckCircle2 className="h-7 w-7" />
                        </div>
                      )}
                      <div>
                        <CardTitle className="text-xl font-bold tracking-tight">
                          {outcome.decision === 'CHECK_IN' && 'ACCESS GRANTED — CHECK IN'}
                          {outcome.decision === 'CHECK_OUT' && 'ACCESS GRANTED — CHECK OUT'}
                          {outcome.decision === 'REJECT' && (
                            outcome.isPassbackViolation
                              ? 'ANTI-PASSBACK VIOLATION DETECTED'
                              : 'ACCESS REFUSED / FORBIDDEN'
                          )}
                        </CardTitle>
                        <CardDescription className="font-mono text-xs">
                          Evaluated at {outcome.occurredAt} &middot; Pass ID: {outcome.report.passId}
                        </CardDescription>
                      </div>
                    </div>

                    <Badge
                      variant={outcome.decision === 'REJECT' ? (outcome.isPassbackViolation ? 'outline' : 'destructive') : 'default'}
                      className={cn(
                        'text-xs px-3 py-1 font-bold',
                        outcome.isPassbackViolation && 'border-amber-500/50 text-amber-400 bg-amber-500/10'
                      )}
                    >
                      {outcome.isPassbackViolation ? 'ANTI-PASSBACK' : outcome.decision}
                    </Badge>
                  </div>
                </CardHeader>

                <CardContent className="pt-6 space-y-6">
                  {/* Holder Information Grid */}
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div className="space-y-1 bg-card/60 p-3.5 rounded-xl border border-border/50">
                      <span className="text-xs text-muted-foreground flex items-center gap-1.5">
                        <User className="h-3.5 w-3.5" /> Person Name
                      </span>
                      <p className="text-lg font-bold text-foreground">{outcome.report.userName}</p>
                    </div>

                    <div className="space-y-1 bg-card/60 p-3.5 rounded-xl border border-border/50">
                      <span className="text-xs text-muted-foreground flex items-center gap-1.5">
                        <MapPin className="h-3.5 w-3.5" /> Property / Destination
                      </span>
                      <p className="text-lg font-bold text-foreground">
                        {outcome.report.property || 'Community Common Area'}
                      </p>
                    </div>

                    <div className="space-y-1 bg-card/60 p-3.5 rounded-xl border border-border/50">
                      <span className="text-xs text-muted-foreground">Category</span>
                      <p className="text-sm font-semibold text-foreground uppercase tracking-wide">
                        {outcome.report.category}
                      </p>
                    </div>

                    <div className="space-y-1 bg-card/60 p-3.5 rounded-xl border border-border/50">
                      <span className="text-xs text-muted-foreground">Status Flag</span>
                      <p className="text-sm font-semibold text-foreground">{outcome.status}</p>
                    </div>
                  </div>

                  {/* Refusal / Warning Reason */}
                  {outcome.decision === 'REJECT' && (
                    outcome.isPassbackViolation ? (
                      /* Anti-Passback High-Impact Security Banner */
                      <div className="p-4 rounded-xl bg-amber-500/10 border-2 border-amber-500/40 text-amber-100 dark:bg-amber-950/40 dark:border-amber-500/50 space-y-3 shadow-md">
                        <div className="flex items-center justify-between">
                          <div className="flex items-center gap-2 font-bold text-base text-amber-400">
                            <AlertTriangle className="h-5 w-5 text-amber-400 animate-pulse" />
                            <span>⚠️ Already Checked In — Anti-Passback Violation</span>
                          </div>
                          <Badge variant="outline" className="border-amber-500/50 text-amber-300 bg-amber-500/20 text-[10px] font-mono">
                            PASSBACK RULE ACTIVE
                          </Badge>
                        </div>
                        <p className="text-xs leading-relaxed text-amber-200/90 font-medium">
                          {outcome.report.primaryReason || 'Passholder is recorded inside property. Duplicate entry without prior departure prohibited.'}
                        </p>
                        <div className="pt-1 flex flex-wrap items-center gap-3">
                          <Button
                            type="button"
                            onClick={() => setShowOverrideModal(true)}
                            className="bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs h-9 px-4 gap-2 shadow-xs"
                          >
                            <Shield className="w-4 h-4" />
                            Investigate & Authorize Override
                          </Button>
                          <span className="text-[11px] text-amber-300/70">
                            Prevents credential sharing and duplicate ingress without egress.
                          </span>
                        </div>
                      </div>
                    ) : (
                      <div className="p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-800 dark:text-rose-300 space-y-1">
                        <div className="flex items-center gap-2 font-bold text-sm">
                          <ShieldAlert className="h-4 w-4" />
                          <span>Security Advisory:</span>
                        </div>
                        <p className="text-xs">
                          {outcome.report.primaryReason || 'Cryptographic signature mismatch or expired token window.'}
                        </p>
                      </div>
                    )
                  )}

                  {/* One-Tap Admission Action */}
                  <div className="flex flex-col sm:flex-row items-center gap-3 pt-2">
                    {outcome.scanId && outcome.decision !== 'REJECT' ? (
                      <Button
                        type="button"
                        size="lg"
                        disabled={isConfirming}
                        onClick={handleConfirmAdmission}
                        className="w-full h-14 text-base font-bold bg-emerald-600 hover:bg-emerald-700 text-white gap-2 shadow-md"
                      >
                        <ShieldCheck className="h-5 w-5" />
                        {isConfirming
                          ? 'Admitting Guest...'
                          : outcome.decision === 'CHECK_IN'
                            ? 'Admit Guest & Open Barrier'
                            : 'Confirm Exit & Log Departure'}
                      </Button>
                    ) : (
                      <>
                        {outcome.isPassbackViolation && (
                          <Button
                            type="button"
                            size="lg"
                            onClick={() => setShowOverrideModal(true)}
                            className="w-full sm:w-1/2 h-14 text-sm font-bold bg-amber-500 hover:bg-amber-400 text-slate-950 gap-2 shadow-md"
                          >
                            <KeyRound className="h-5 w-5" />
                            Authorize Officer Override
                          </Button>
                        )}
                        <Button
                          type="button"
                          variant="outline"
                          size="lg"
                          onClick={() => {
                            setOutcome(null);
                            inputRef.current?.focus();
                          }}
                          className={cn('w-full h-14 text-sm font-medium gap-2', outcome.isPassbackViolation && 'sm:w-1/2')}
                        >
                          <RotateCcw className="h-4 w-4" />
                          Reset for Next Visitor
                        </Button>
                      </>
                    )}
                  </div>
                </CardContent>
              </Card>
            ) : (
              <Card className="border-dashed border-2 border-border/80 bg-muted/20 text-center py-16">
                <div className="max-w-md mx-auto space-y-3 px-4">
                  <div className="h-16 w-16 mx-auto rounded-full bg-primary/10 text-primary flex items-center justify-center">
                    <Scan className="h-8 w-8 animate-pulse" />
                  </div>
                  <h3 className="text-lg font-bold text-foreground">Waiting for Next Pass Scan</h3>
                  <p className="text-xs text-muted-foreground">
                    Direct your optical scanner at the visitor's mobile screen or printed pass, or type their 6-digit offline PIN above.
                  </p>
                </div>
              </Card>
            )}
          </div>

          {/* Recent Gate Activity (4 cols) */}
          <div className="lg:col-span-4 space-y-6">
            <Card className="border-border shadow-xs">
              <CardHeader className="pb-3 border-b border-border/60">
                <CardTitle className="text-sm font-semibold flex items-center gap-2">
                  <History className="h-4 w-4 text-muted-foreground" />
                  Recent Gate Clearances
                </CardTitle>
                <CardDescription className="text-xs">
                  Live verification audit feed
                </CardDescription>
              </CardHeader>
              <CardContent className="p-0 divide-y divide-border/60 max-h-[500px] overflow-y-auto">
                {scansList.length === 0 ? (
                  <p className="text-xs text-muted-foreground p-4 text-center">No scans recorded this session.</p>
                ) : (
                  scansList.map((scan) => (
                    <div key={scan.id} className="p-3.5 hover:bg-muted/40 transition text-xs space-y-1">
                      <div className="flex items-center justify-between">
                        <span className="font-semibold text-foreground truncate max-w-[150px]">{scan.userName}</span>
                        <Badge
                          variant={scan.result === 'GRANT' ? 'default' : 'destructive'}
                          className="text-[10px] uppercase font-bold"
                        >
                          {scan.result}
                        </Badge>
                      </div>
                      <div className="flex items-center justify-between text-muted-foreground text-[11px]">
                        <span>{scan.userRole} &middot; {scan.gate}</span>
                        <span>{scan.occurredAt}</span>
                      </div>
                      {scan.denyReason && (
                        <p className="text-[10px] text-destructive italic truncate">
                          Reason: {scan.denyReason}
                        </p>
                      )}
                    </div>
                  ))
                )}
              </CardContent>
            </Card>

            {/* Officer On Duty Info */}
            <div className="rounded-xl border border-border/60 bg-muted/30 p-4 text-xs space-y-2">
              <span className="font-bold text-foreground uppercase tracking-wider text-[10px] block">
                Officer on Duty
              </span>
              <div className="flex items-center justify-between">
                <span className="text-muted-foreground">Guard:</span>
                <span className="font-semibold text-foreground">{guard?.name || 'Security Personnel'}</span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-muted-foreground">Duty Station:</span>
                <span className="font-semibold text-foreground">{selectedGate}</span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-muted-foreground">Crypto Protocol:</span>
                <span className="font-mono text-foreground font-semibold">HMAC-SHA256 (Slot: 30s)</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Anti-Passback Security Override Confirmation Dialog */}
      <Dialog open={showOverrideModal} onOpenChange={setShowOverrideModal}>
        <DialogContent className="sm:max-w-lg border-border bg-card">
          <DialogHeader>
            <div className="flex items-center gap-3">
              <div className="w-10 h-10 rounded-xl bg-amber-500/20 text-amber-500 flex items-center justify-center">
                <Shield className="w-5 h-5" />
              </div>
              <div>
                <DialogTitle className="text-lg font-bold text-foreground">
                  Security Override Admission
                </DialogTitle>
                <DialogDescription className="text-xs text-muted-foreground">
                  Passholder is flagged as already inside. Provide official justification to authorize re-entry.
                </DialogDescription>
              </div>
            </div>
          </DialogHeader>

          <div className="space-y-4 py-2">
            {outcome && (
              <div className="bg-muted/40 p-3 rounded-xl border border-border/60 text-xs space-y-1.5">
                <div className="flex justify-between">
                  <span className="text-muted-foreground">Passholder:</span>
                  <span className="font-bold text-foreground">{outcome.report.userName}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-muted-foreground">Credential ID:</span>
                  <span className="font-mono text-foreground font-semibold">{outcome.report.passId}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-muted-foreground">Destination:</span>
                  <span className="text-foreground">{outcome.report.property || 'Community Area'}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-muted-foreground">Current Census Status:</span>
                  <span className="text-amber-500 font-semibold">Active Ingress (Checked In)</span>
                </div>
              </div>
            )}

            <div className="space-y-2">
              <label className="text-xs font-semibold text-foreground uppercase tracking-wider block">
                Quick Justification Preset
              </label>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                {[
                  'Exited without badge scan (Tailgate/Escort)',
                  'Turnstile/barrier mechanical fault',
                  'Supervisor verbal verification',
                  'Short errand / resident re-entry',
                ].map((preset) => (
                  <button
                    key={preset}
                    type="button"
                    onClick={() => setOverrideReason(preset)}
                    className={cn(
                      'p-2 rounded-lg border text-left text-xs transition-colors',
                      overrideReason === preset
                        ? 'border-amber-500/80 bg-amber-500/15 text-amber-300 font-medium'
                        : 'border-border/60 bg-muted/30 text-muted-foreground hover:text-foreground hover:bg-muted/60'
                    )}
                  >
                    {preset}
                  </button>
                ))}
              </div>
            </div>

            <div className="space-y-1.5">
              <label htmlFor="override-reason-input" className="text-xs font-semibold text-foreground block">
                Officer Override Notes / Log Reason:
              </label>
              <Input
                id="override-reason-input"
                type="text"
                value={overrideReason}
                onChange={(e) => setOverrideReason(e.target.value)}
                placeholder="Enter justification for access log audit..."
                className="h-10 text-xs"
              />
              <span className="text-[11px] text-muted-foreground block">
                This note will be permanently logged in the Immutable Access Audit Trail under Officer {guard?.name}.
              </span>
            </div>
          </div>

          <DialogFooter className="gap-2 sm:gap-0">
            <Button
              type="button"
              variant="outline"
              onClick={() => setShowOverrideModal(false)}
              disabled={isOverriding}
            >
              Cancel
            </Button>
            <Button
              type="button"
              onClick={handleOverrideSubmit}
              disabled={isOverriding || !overrideReason.trim()}
              className="bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold gap-2"
            >
              <FileCheck2 className="w-4 h-4" />
              {isOverriding ? 'Recording Override...' : 'Confirm Override & Admit'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </DashboardLayout>
  );
}
