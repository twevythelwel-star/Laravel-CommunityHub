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
  AlertTriangle
} from 'lucide-react';
import { cn } from '@/lib/utils';
import axios from 'axios';

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
  report: {
    passId: string;
    category: string;
    userName: string;
    property?: string;
    gate?: string;
    primaryReason?: string;
    details?: string[];
  };
  occurredAt: string;
};

export default function GateScannerPage({ gates = [], recentScans = [], guard }: Props) {
  const { toast } = useToast();
  const [selectedGate, setSelectedGate] = useState<string>(gates[0]?.id || 'GATE-01');
  const [tokenInput, setTokenInput] = useState<string>('');
  const [isScanning, setIsScanning] = useState<boolean>(false);
  const [isConfirming, setIsConfirming] = useState<boolean>(false);
  const [outcome, setOutcome] = useState<ScanOutcome | null>(null);
  const [soundEnabled, setSoundEnabled] = useState<boolean>(true);
  const [scansList, setScansList] = useState<RecentScan[]>(recentScans);

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

    setIsScanning(true);
    try {
      const res = await axios.post('/dashboard/gate-pass/scan', {
        token: raw,
        gate: selectedGate,
      });

      const data = res.data;
      const isGranted = data.decision === 'CHECK_IN' || data.decision === 'CHECK_OUT';
      playAudioCue(isGranted);

      const newOutcome: ScanOutcome = {
        decision: data.decision,
        status: data.report?.status || (isGranted ? 'VALID' : 'INVALID'),
        scanId: data.scanId || null,
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

          <div className="flex items-center gap-3">
            {/* Gate Selector */}
            <div className="flex items-center gap-2 bg-muted/60 px-3 py-1.5 rounded-lg border border-border/60">
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
              className="gap-1.5"
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
            {/* Direct Input & Scanner Gun Field */}
            <Card className="border-border shadow-xs">
              <CardHeader className="pb-3">
                <CardTitle className="text-base flex items-center justify-between">
                  <span>Pass Token or 6-Digit PIN</span>
                  <Badge variant="outline" className="font-mono text-xs">
                    Scanner Ready
                  </Badge>
                </CardTitle>
                <CardDescription>
                  Scan QR code with handheld scanner, or type 6-digit offline PIN.
                </CardDescription>
              </CardHeader>
              <CardContent>
                <form onSubmit={handleScanSubmit} className="flex gap-2">
                  <div className="relative flex-1">
                    <Input
                      ref={inputRef}
                      type="text"
                      value={tokenInput}
                      onChange={(e) => setTokenInput(e.target.value)}
                      placeholder="Point scanner gun or enter PIN / Token (e.g. 482195)..."
                      className="font-mono text-base tracking-wide h-12 pr-10"
                      disabled={isScanning}
                      autoFocus
                    />
                    <KeyRound className="absolute right-3 top-3.5 h-5 w-5 text-muted-foreground/60" />
                  </div>
                  <Button
                    type="submit"
                    disabled={isScanning || !tokenInput.trim()}
                    className="h-12 px-6 font-semibold"
                  >
                    {isScanning ? 'Verifying...' : 'Verify Pass'}
                  </Button>
                </form>

                <NfcTagReadButton
                  disabled={isScanning}
                  onRead={(code) => {
                    setTokenInput(code);
                    inputRef.current?.focus();
                  }}
                />
              </CardContent>
            </Card>

            {/* Verification Result Display */}
            {outcome ? (
              <Card
                className={cn(
                  'border-2 shadow-lg transition-all duration-300',
                  outcome.decision === 'REJECT'
                    ? 'border-rose-500 bg-rose-500/5 dark:bg-rose-950/20'
                    : 'border-emerald-500 bg-emerald-500/5 dark:bg-emerald-950/20'
                )}
              >
                <CardHeader className="pb-4 border-b border-border/40">
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                      {outcome.decision === 'REJECT' ? (
                        <div className="h-12 w-12 rounded-xl bg-rose-500/20 text-rose-600 dark:text-rose-400 flex items-center justify-center">
                          <XCircle className="h-7 w-7" />
                        </div>
                      ) : (
                        <div className="h-12 w-12 rounded-xl bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                          <CheckCircle2 className="h-7 w-7" />
                        </div>
                      )}
                      <div>
                        <CardTitle className="text-xl font-bold tracking-tight">
                          {outcome.decision === 'CHECK_IN' && 'ACCESS GRANTED — CHECK IN'}
                          {outcome.decision === 'CHECK_OUT' && 'ACCESS GRANTED — CHECK OUT'}
                          {outcome.decision === 'REJECT' && 'ACCESS REFUSED / FORBIDDEN'}
                        </CardTitle>
                        <CardDescription className="font-mono text-xs">
                          Evaluated at {outcome.occurredAt} &middot; Pass ID: {outcome.report.passId}
                        </CardDescription>
                      </div>
                    </div>

                    <Badge
                      variant={outcome.decision === 'REJECT' ? 'destructive' : 'default'}
                      className="text-xs px-3 py-1 font-bold"
                    >
                      {outcome.decision}
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
                    <div className="p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-800 dark:text-rose-300 space-y-1">
                      <div className="flex items-center gap-2 font-bold text-sm">
                        <ShieldAlert className="h-4 w-4" />
                        <span>Security Advisory:</span>
                      </div>
                      <p className="text-xs">
                        {outcome.report.primaryReason || 'Cryptographic signature mismatch or expired token window.'}
                      </p>
                    </div>
                  )}

                  {/* One-Tap Admission Action */}
                  <div className="flex items-center gap-3 pt-2">
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
                      <Button
                        type="button"
                        variant="outline"
                        size="lg"
                        onClick={() => {
                          setOutcome(null);
                          inputRef.current?.focus();
                        }}
                        className="w-full h-12 text-sm font-medium gap-2"
                      >
                        <RotateCcw className="h-4 w-4" />
                        Reset for Next Visitor
                      </Button>
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
    </DashboardLayout>
  );
}
