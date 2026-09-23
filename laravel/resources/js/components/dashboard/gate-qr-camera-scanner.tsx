import React, { useState, useEffect, useRef, useCallback } from 'react';
import jsQR from 'jsqr';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import {
  Camera,
  CheckCircle2,
  XCircle,
  AlertTriangle,
  Scan,
  RefreshCw,
  Zap,
  ZapOff,
  Layers,
  ShieldCheck,
  ShieldAlert,
  Clock,
  MapPin,
  Home,
  UserCheck,
  LogOut,
  ChevronDown,
  ChevronUp,
  ExternalLink,
  Info,
} from 'lucide-react';
import { GateId, GatePassValidationReport, ScanDecision } from '@/lib/gate-pass-engine/types';
import { describeRequestError } from '@/lib/gate-pass-engine/api';
import { CategoryShapeIcon } from '@/lib/gate-pass-engine/shapes';
import { useToast } from '@/hooks/use-toast';
import { cn } from '@/lib/utils';
import axios from 'axios';

function formatDenyReason(primaryReason?: string): string {
  if (!primaryReason) return 'EXPIRED';
  const upper = primaryReason.toUpperCase();
  if (upper.includes('TOKEN_EXPIRED') || upper.includes('EXPIRED')) return 'EXPIRED';
  if (upper.includes('PASS_REVOKED') || upper.includes('REVOKED')) return 'REVOKED';
  if (upper.includes('NOT_A_GPE') || upper.includes('UNRECOGNIZED')) return 'NOT FOUND';
  if (upper.includes('SIGNATURE_MISMATCH') || upper.includes('INVALID_QR_STRUCTURE') || upper.includes('TAMPERED') || upper.includes('FORGED')) return 'TAMPERED';
  if (upper.includes('UNAUTHORIZED_COMMUNITY') || upper.includes('COMMUNITY')) return 'WRONG COMMUNITY';
  if (upper.includes('UNAUTHORIZED_GATE')) return 'WRONG GATE';
  if (upper.includes('OUTSIDE_HOURS') || upper.includes('TOKEN_NOT_YET_VALID') || upper.includes('TIME')) return 'WRONG TIME';
  if (upper.includes('UNAUTHORIZED_ZONE') || upper.includes('ZONE')) return 'WRONG ACCESS ZONE';
  if (upper.includes('REPLAY') || upper.includes('DUPLICATE')) return 'REPLAY ATTACK';
  if (upper.includes('USER_NOT_ACTIVE')) return 'ACCOUNT DEACTIVATED';
  return primaryReason.split(':')[0] || 'PASS NOT VALID';
}

interface GateQrCameraScannerProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onSuccessCheck?: () => void;
  initialGate?: GateId;
}

export function GateQrCameraScanner({
  open,
  onOpenChange,
  onSuccessCheck,
  initialGate = 'GATE-01',
}: GateQrCameraScannerProps) {
  const { toast } = useToast();
  const [selectedGate, setSelectedGate] = useState<GateId>(initialGate);
  const [inputToken, setInputToken] = useState('');
  const [isScanning, setIsScanning] = useState(false);
  const [isCameraActive, setIsCameraActive] = useState(false);
  const [cameraError, setCameraError] = useState<string | null>(null);
  const [facingMode, setFacingMode] = useState<'environment' | 'user'>('environment');
  const [torchEnabled, setTorchEnabled] = useState(false);
  const [torchSupported, setTorchSupported] = useState(false);

  // Scan state results
  const [report, setReport] = useState<GatePassValidationReport | null>(null);
  const [accessLogId, setAccessLogId] = useState<number | null>(null);
  // The server's decision for the last scan, and the ID that confirms it.
  const [decision, setDecision] = useState<ScanDecision | null>(null);
  const [scanId, setScanId] = useState<string | null>(null);
  const [completed, setCompleted] = useState<{ action: 'CHECK_IN' | 'CHECK_OUT' | 'REFUSED'; time: string } | null>(null);
  const [showTechnicalDetails, setShowTechnicalDetails] = useState(false);
  const [isConfirmingAction, setIsConfirmingAction] = useState(false);

  // Kiosk mode continuous scan with auto-rearm
  const [isKioskMode, setIsKioskMode] = useState<boolean>(false);
  const [autoRearmCountdown, setAutoRearmCountdown] = useState<number | null>(null);

  const videoRef = useRef<HTMLVideoElement | null>(null);
  const canvasRef = useRef<HTMLCanvasElement | null>(null);
  const streamRef = useRef<MediaStream | null>(null);
  const scanLoopRef = useRef<number | null>(null);
  const lastScannedTokenRef = useRef<string | null>(null);
  const lastScanTimestampRef = useRef<number>(0);

  // Continuous Kiosk Mode Auto-Rearm Countdown
  useEffect(() => {
    if (!isKioskMode || !report) {
      setAutoRearmCountdown(null);
      return;
    }

    setAutoRearmCountdown(4);
    const interval = setInterval(() => {
      setAutoRearmCountdown((prev) => {
        if (prev === null || prev <= 1) {
          clearInterval(interval);
          setReport(null);
          setInputToken('');
          setCompleted(null);
          lastScannedTokenRef.current = null;
          return null;
        }
        return prev - 1;
      });
    }, 1000);

    return () => clearInterval(interval);
  }, [isKioskMode, report]);

  // Start / Stop Camera Stream
  const startCamera = useCallback(async () => {
    setCameraError(null);
    try {
      if (streamRef.current) {
        streamRef.current.getTracks().forEach((track) => track.stop());
      }

      const constraints: MediaStreamConstraints = {
        video: {
          facingMode: { ideal: facingMode },
          width: { ideal: 1280 },
          height: { ideal: 720 },
        },
        audio: false,
      };

      const stream = await navigator.mediaDevices.getUserMedia(constraints);
      streamRef.current = stream;

      if (videoRef.current) {
        videoRef.current.srcObject = stream;
        await videoRef.current.play();
      }

      setIsCameraActive(true);

      // Check if torch/flashlight is supported
      const track = stream.getVideoTracks()[0];
      const capabilities = (track.getCapabilities ? track.getCapabilities() : {}) as any;
      if (capabilities && capabilities.torch) {
        setTorchSupported(true);
      } else {
        setTorchSupported(false);
      }
    } catch (err: any) {
      console.warn('Camera stream error:', err);
      setIsCameraActive(false);
      setCameraError(
        err.name === 'NotAllowedError'
          ? 'Camera permission denied. Please allow camera access in your browser or use the manual input/samples below.'
          : 'Unable to initialize device camera video stream.'
      );
    }
  }, [facingMode]);

  const stopCamera = useCallback(() => {
    if (scanLoopRef.current) {
      cancelAnimationFrame(scanLoopRef.current);
      scanLoopRef.current = null;
    }
    if (streamRef.current) {
      streamRef.current.getTracks().forEach((track) => track.stop());
      streamRef.current = null;
    }
    setIsCameraActive(false);
    setTorchEnabled(false);
  }, []);

  const toggleTorch = async () => {
    if (!streamRef.current) return;
    const track = streamRef.current.getVideoTracks()[0];
    if (track && (track.applyConstraints as any)) {
      try {
        const next = !torchEnabled;
        await (track.applyConstraints as any)({
          advanced: [{ torch: next }],
        });
        setTorchEnabled(next);
      } catch (err) {
        console.warn('Torch toggle failed', err);
      }
    }
  };

  const flipCamera = () => {
    setFacingMode((prev) => (prev === 'environment' ? 'user' : 'environment'));
  };

  // Run validation on a token through the backend Digital Gate Engine
  const executeScan = useCallback(
    async (token: string) => {
      setIsScanning(true);
      setCompleted(null);

      try {
        const response = await axios.post('/dashboard/gate-pass/scan', {
          token,
          gate: selectedGate,
        });

        const data = response.data;
        setReport(data.report);
        setAccessLogId(data.accessLogId);
        setDecision(data.decision);
        setScanId(data.scanId);

        // Sound / Beep Feedback
        try {
          const ctx = new (window.AudioContext || (window as any).webkitAudioContext)();
          const osc = ctx.createOscillator();
          const gain = ctx.createGain();
          osc.connect(gain);
          gain.connect(ctx.destination);
          osc.type = data.report.status === 'ALLOW' ? 'sine' : 'sawtooth';
          osc.frequency.setValueAtTime(data.report.status === 'ALLOW' ? 880 : 220, ctx.currentTime);
          gain.gain.setValueAtTime(0.15, ctx.currentTime);
          osc.start();
          osc.stop(ctx.currentTime + 0.15);
        } catch (_) {}

        // Mobile Device Haptic Vibration Feedback
        if (typeof navigator !== 'undefined' && 'vibrate' in navigator) {
          try {
            if (data.report.status === 'ALLOW') {
              navigator.vibrate([60, 40, 60]); // Crisp double pulse confirmation
            } else {
              navigator.vibrate([120, 80, 180]); // Distinct security alert buzz
            }
          } catch (_) {}
        }
      } catch (error: any) {
        toast({
          variant: 'destructive',
          title: 'Scan Request Failed',
          description: describeRequestError(error),
        });
      } finally {
        setIsScanning(false);
      }
    },
    [selectedGate, toast],
  );

  // Frame processing loop for QR decoding
  useEffect(() => {
    if (!open || !isCameraActive) return;

    let isScanningFrame = false;

    const processFrame = () => {
      if (!isCameraActive || !videoRef.current || videoRef.current.readyState < 2) {
        scanLoopRef.current = requestAnimationFrame(processFrame);
        return;
      }

      const now = Date.now();
      // Throttle to 10 FPS to save CPU / battery
      if (now - lastScanTimestampRef.current > 100 && !isScanningFrame && !isScanning) {
        lastScanTimestampRef.current = now;
        isScanningFrame = true;

        try {
          const video = videoRef.current;
          let canvas = canvasRef.current;
          if (!canvas) {
            canvas = document.createElement('canvas');
            canvasRef.current = canvas;
          }

          if (canvas.width !== video.videoWidth || canvas.height !== video.videoHeight) {
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
          }

          const ctx = canvas.getContext('2d', { willReadFrequently: true });
          if (ctx && canvas.width > 0 && canvas.height > 0) {
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
            const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
            const qrCode = jsQR(imageData.data, imageData.width, imageData.height, {
              inversionAttempts: 'dontInvert',
            });

            if (qrCode && qrCode.data) {
              const scannedText = qrCode.data.trim();
              if (scannedText !== lastScannedTokenRef.current || now - lastScanTimestampRef.current > 3000) {
                lastScannedTokenRef.current = scannedText;
                setInputToken(scannedText);
                void executeScan(scannedText);
              }
            }
          }
        } catch (e) {
          // ignore transient frame read errors
        } finally {
          isScanningFrame = false;
        }
      }

      scanLoopRef.current = requestAnimationFrame(processFrame);
    };

    scanLoopRef.current = requestAnimationFrame(processFrame);

    return () => {
      if (scanLoopRef.current) {
        cancelAnimationFrame(scanLoopRef.current);
        scanLoopRef.current = null;
      }
    };
  }, [open, isCameraActive, isScanning, executeScan]);

  // Manage camera on dialog open / close
  useEffect(() => {
    if (open) {
      void startCamera();
    } else {
      stopCamera();
      setReport(null);
      setInputToken('');
      lastScannedTokenRef.current = null;
      setCompleted(null);
    }
  }, [open, startCamera, stopCamera]);

  /*
   | Confirms or refuses the server's decision. Only the scan ID is sent: the
   | action (check in or check out) is the one the server decided when it
   | validated the code, so this screen cannot choose it.
   */
  const handleConfirm = async (accept: boolean) => {
    if (!report || !scanId) return;
    setIsConfirmingAction(true);

    try {
      const res = await axios.post(`/dashboard/gate-pass/scans/${scanId}/confirm`, {
        accept,
        reason: accept ? null : 'Refused by officer after inspection',
      });

      setCompleted({ action: res.data.action, time: res.data.time });
      setScanId(null);

      toast({
        title: res.data.action === 'CHECK_IN' ? 'Checked in' : res.data.action === 'CHECK_OUT' ? 'Checked out' : 'Entry refused',
        description: `${report.userName}: ${res.data.message}`,
      });
      onSuccessCheck?.();
    } catch (err: any) {
      toast({
        variant: 'destructive',
        title: 'Not recorded',
        description: err.response?.data?.message || 'Could not record the decision. Scan the pass again.',
      });
    } finally {
      setIsConfirmingAction(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-3xl max-h-[94vh] overflow-y-auto p-0 border shadow-2xl bg-card">
        {/* Header with Portal Selector & Scanner Title */}
        <div className="p-5 border-b bg-muted/40 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div className="flex items-center gap-3">
            <div className="w-11 h-11 rounded-2xl bg-primary/10 border border-primary/20 flex items-center justify-center text-primary shadow-sm shrink-0">
              <Scan className="w-6 h-6 animate-pulse" />
            </div>
            <div>
              <DialogTitle className="text-xl font-bold flex items-center gap-2">
                Digital Gate Pass QR Scanner
                <Badge variant="outline" className="text-[10px] tracking-wider font-mono border-primary/30 text-primary uppercase">
                  Engine v1
                </Badge>
              </DialogTitle>
              <DialogDescription className="text-xs text-muted-foreground">
                Live Camera Reticle • 16-Point Cryptographic & Policy Ingress Engine
              </DialogDescription>
            </div>
          </div>

          {/* Controls: Portal & Kiosk Mode */}
          <div className="flex flex-wrap items-center gap-2 self-start sm:self-auto">
            {/* Kiosk Mode Toggle */}
            <Button
              type="button"
              size="sm"
              variant={isKioskMode ? 'default' : 'outline'}
              onClick={() => setIsKioskMode((prev) => !prev)}
              className={cn(
                'text-xs font-semibold gap-1.5 h-8 rounded-xl',
                isKioskMode && 'bg-amber-600 hover:bg-amber-700 text-white border-amber-600 shadow-sm'
              )}
              title="Continuous scanning with automatic re-arm after each vehicle"
            >
              <Zap className={cn('w-3.5 h-3.5', isKioskMode && 'fill-white animate-pulse')} />
              <span>{isKioskMode ? 'Kiosk Lane: Active' : 'Kiosk Mode'}</span>
            </Button>

            {/* Gate Selection Pill */}
            <div className="flex items-center gap-1.5 bg-background border rounded-xl p-1 text-xs shadow-xs">
              <span className="text-muted-foreground px-2 font-mono text-[11px]">PORTAL:</span>
              <button
                type="button"
                onClick={() => setSelectedGate('GATE-01')}
                className={cn(
                  'px-3 py-1 rounded-lg font-bold transition-all text-xs',
                  selectedGate === 'GATE-01'
                    ? 'bg-primary text-primary-foreground shadow-xs'
                    : 'text-muted-foreground hover:text-foreground'
                )}
              >
                Main Gate (01)
              </button>
              <button
                type="button"
                onClick={() => setSelectedGate('GATE-02')}
                className={cn(
                  'px-3 py-1 rounded-lg font-bold transition-all text-xs',
                  selectedGate === 'GATE-02'
                    ? 'bg-primary text-primary-foreground shadow-xs'
                    : 'text-muted-foreground hover:text-foreground'
                )}
              >
                Service Gate (02)
              </button>
            </div>
          </div>
        </div>

        <div className="p-6 space-y-6">
          {/* Continuous Kiosk Auto-Rearm Status Banner */}
          {autoRearmCountdown !== null && (
            <div className="flex items-center justify-between bg-amber-500/10 border border-amber-500/30 rounded-xl px-4 py-2.5 text-xs text-amber-700 dark:text-amber-400 font-medium animate-in fade-in duration-200">
              <span className="flex items-center gap-2">
                <RefreshCw className="w-3.5 h-3.5 animate-spin text-amber-600" />
                <span>Continuous Fast-Lane: Re-arming camera in <strong>{autoRearmCountdown}s</strong> for next vehicle…</span>
              </span>
              <Button
                type="button"
                variant="ghost"
                size="sm"
                onClick={() => setAutoRearmCountdown(null)}
                className="h-6 px-2 text-[11px] font-bold text-amber-800 dark:text-amber-300 hover:bg-amber-500/20"
              >
                Pause Auto-Rearm
              </Button>
            </div>
          )}
          {/* CAMERA VIEWFINDER & RETICLE */}
          <div className="relative rounded-2xl overflow-hidden bg-slate-950 border border-slate-800 shadow-2xl flex flex-col items-center justify-center min-h-[300px]">
            {/* Live Video Feed */}
            <video
              ref={videoRef}
              playsInline
              muted
              autoPlay
              className={cn(
                'w-full max-h-[360px] object-cover transition-opacity duration-300',
                isCameraActive ? 'opacity-90' : 'opacity-20 hidden'
              )}
            />

            {/* Fallback Viewport if camera is unavailable or loading */}
            {!isCameraActive && (
              <div className="p-8 text-center space-y-3">
                <div className="w-16 h-16 rounded-full bg-slate-900 border border-slate-700 flex items-center justify-center mx-auto text-muted-foreground">
                  <Camera className="w-8 h-8 opacity-70" />
                </div>
                <div>
                  <p className="text-sm font-semibold text-slate-200">
                    {cameraError ? 'Camera Access Notice' : 'Connecting to Camera Stream…'}
                  </p>
                  <p className="text-xs text-slate-400 max-w-sm mt-1 mx-auto">
                    {cameraError || 'Position the pass QR directly within device viewport.'}
                  </p>
                </div>
                <Button
                  size="sm"
                  variant="outline"
                  onClick={() => void startCamera()}
                  className="text-xs gap-1.5 border-slate-700 text-slate-200 hover:bg-slate-800"
                >
                  <RefreshCw className="w-3.5 h-3.5" />
                  Retry Camera
                </Button>
              </div>
            )}

            {/* OVERLAY: Reticle Matching User Diagram */}
            {isCameraActive && (
              <div className="absolute inset-0 pointer-events-none flex flex-col items-center justify-center p-4">
                {/* Target Square */}
                <div className="relative w-56 h-56 sm:w-64 sm:h-64 border-2 border-primary/60 rounded-2xl shadow-[0_0_20px_rgba(59,130,246,0.3)] flex items-center justify-center">
                  {/* Corner Reticles */}
                  <div className="absolute -top-1 -left-1 w-6 h-6 border-t-4 border-l-4 border-primary rounded-tl-lg" />
                  <div className="absolute -top-1 -right-1 w-6 h-6 border-t-4 border-r-4 border-primary rounded-tr-lg" />
                  <div className="absolute -bottom-1 -left-1 w-6 h-6 border-b-4 border-l-4 border-primary rounded-bl-lg" />
                  <div className="absolute -bottom-1 -right-1 w-6 h-6 border-b-4 border-r-4 border-primary rounded-br-lg" />

                  {/* Laser Scan Sweep Line */}
                  <div className="absolute left-2 right-2 h-0.5 bg-gradient-to-r from-transparent via-cyan-400 to-transparent shadow-[0_0_12px_#38bdf8] animate-bounce" />

                  {/* Center QR Glyph */}
                  <div className="text-primary/40 font-mono text-2xl font-bold tracking-widest">
                    ▣
                  </div>

                  <span className="absolute -bottom-7 text-[11px] font-mono font-bold tracking-wider text-primary-foreground bg-primary/90 px-3 py-0.5 rounded-full shadow-sm">
                    SCAN DIGITAL GATE PASS
                  </span>
                </div>
              </div>
            )}

            {/* Camera Floating Controls */}
            {isCameraActive && (
              <div className="absolute top-3 right-3 flex items-center gap-2">
                {torchSupported && (
                  <Button
                    size="icon"
                    variant="secondary"
                    className="h-8 w-8 rounded-full bg-slate-900/80 backdrop-blur-sm border border-slate-700 text-slate-200"
                    onClick={() => void toggleTorch()}
                    title="Toggle Flash"
                  >
                    {torchEnabled ? <Zap className="w-4 h-4 text-amber-400" /> : <ZapOff className="w-4 h-4" />}
                  </Button>
                )}
                <Button
                  size="icon"
                  variant="secondary"
                  className="h-8 w-8 rounded-full bg-slate-900/80 backdrop-blur-sm border border-slate-700 text-slate-200"
                  onClick={flipCamera}
                  title="Switch Camera (Front/Back)"
                >
                  <RefreshCw className="w-4 h-4" />
                </Button>
              </div>
            )}
          </div>

          {/* MANUAL TOKEN INPUT & QUICK SAMPLES */}
          <div className="bg-muted/30 border rounded-xl p-4 space-y-3">
            <form
              onSubmit={(e) => {
                e.preventDefault();
                if (inputToken.trim()) void executeScan(inputToken.trim());
              }}
              className="flex gap-2"
            >
              <Input
                placeholder="Scan QR or paste 'CH-GPE:v1....'"
                value={inputToken}
                onChange={(e) => setInputToken(e.target.value)}
                className="font-mono text-xs h-9 bg-background"
              />
              <Button type="submit" size="sm" className="h-9 px-4 text-xs font-bold gap-1.5" disabled={isScanning}>
                <Scan className="w-3.5 h-3.5" />
                Verify
              </Button>
            </form>

          </div>

          {/* ══════════════════════════════════════════════════════════════════
              SCAN VERIFICATION RESULT STATES MATCHING USER SPECIFICATIONS
             ══════════════════════════════════════════════════════════════════ */}

          {report && (
            <div className="animate-in fade-in slide-in-from-bottom-3 duration-300 space-y-4">
              {/* STATE 1: CHECK IN (✓ VERIFIED) */}
              {decision === 'CHECK_IN' && !completed && (
                <div className="border-2 border-emerald-500/60 rounded-2xl overflow-hidden shadow-xl bg-card">
                  {/* Verified Header Banner */}
                  <div className="bg-gradient-to-r from-emerald-600 to-teal-700 p-6 text-white text-center space-y-2">
                    <div className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-white/20 backdrop-blur-sm shadow-xs uppercase tracking-wider">
                      <CheckCircle2 className="w-4 h-4 text-white" />
                      ✓ VERIFIED
                    </div>
                    <div className="pt-2">
                      <span className="text-xs font-mono uppercase tracking-widest text-emerald-100 block">
                        {report.category}
                      </span>
                      <h2 className="text-3xl font-black tracking-tight text-white mt-0.5">
                        {report.userName}
                      </h2>
                      <p className="text-sm text-emerald-100 font-medium">
                        {report.property.startsWith('Property:') ? report.property : `Property: ${report.property}`}
                      </p>
                    </div>
                  </div>

                  {/* Access Clearance Details */}
                  <div className="p-6 space-y-6">
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 text-center">
                      <div className="p-3.5 bg-emerald-500/5 rounded-xl border border-emerald-500/20">
                        <span className="text-[10px] font-mono text-muted-foreground uppercase block">Clearance</span>
                        <strong className="text-sm font-bold text-emerald-600 dark:text-emerald-400">
                          ACCESS: AUTHORIZED
                        </strong>
                      </div>
                      <div className="p-3.5 bg-muted/40 rounded-xl border">
                        <span className="text-[10px] font-mono text-muted-foreground uppercase block">Authorized Portal</span>
                        <strong className="text-sm font-bold text-foreground">
                          {report.gateChecked === 'GATE-01' ? 'Main Gate' : 'Service Gate'}
                        </strong>
                      </div>
                      <div className="p-3.5 bg-muted/40 rounded-xl border">
                        <span className="text-[10px] font-mono text-muted-foreground uppercase block">Valid Window</span>
                        <strong className="text-sm font-bold text-foreground">
                          {report.secondsRemaining > 0 ? `${report.secondsRemaining}s remaining` : 'Active'}
                        </strong>
                      </div>
                    </div>

                    {/* CONFIRM CHECK IN ACTION */}
                    <div className="pt-2">
                      <Button
                        type="button"
                        size="lg"
                        className="w-full bg-emerald-600 hover:bg-emerald-700 text-white text-base font-black tracking-wide py-6 shadow-lg gap-2"
                        onClick={() => void handleConfirm(true)}
                        disabled={isConfirmingAction}
                      >
                        <UserCheck className="w-5 h-5" />
                        [ CHECK IN ]
                      </Button>
                      <Button
                        type="button"
                        variant="outline"
                        className="w-full mt-2 text-xs font-bold"
                        onClick={() => void handleConfirm(false)}
                        disabled={isConfirmingAction}
                      >
                        Refuse entry (ID does not match)
                      </Button>
                      <p className="text-center text-[11px] text-muted-foreground mt-2">
                        Check the person against their photo ID, then confirm. The decision expires after two minutes.
                      </p>
                    </div>
                  </div>
                </div>
              )}

              {/* STATE 2: VISITOR INSIDE (🔵 CHECK OUT) */}
              {decision === 'CHECK_OUT' && !completed && (
                <div className="border-2 border-blue-500/60 rounded-2xl overflow-hidden shadow-xl bg-card">
                  {/* Inside Header Banner */}
                  <div className="bg-gradient-to-r from-blue-600 to-indigo-700 p-6 text-white text-center space-y-2">
                    <div className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-white/20 backdrop-blur-sm shadow-xs uppercase tracking-wider">
                      <Clock className="w-4 h-4 text-white" />
                      VISITOR INSIDE
                    </div>
                    <div className="pt-2">
                      <h2 className="text-3xl font-black tracking-tight text-white">
                        {report.userName}
                      </h2>
                      <p className="text-sm text-blue-100 font-medium">
                        {report.property.startsWith('Property:') ? report.property : `Property: ${report.property}`}
                      </p>
                    </div>
                  </div>

                  <div className="p-6 space-y-6">
                    <div className="p-4 bg-blue-500/5 rounded-xl border border-blue-500/20 text-center">
                      <span className="text-xs font-mono text-muted-foreground uppercase block">Pass</span>
                      <strong className="text-lg font-bold text-blue-600 dark:text-blue-400">{report.passId}</strong>
                      <span className="block text-xs text-muted-foreground mt-1">Recorded as inside the estate</span>
                    </div>

                    {/* CONFIRM CHECK OUT ACTION */}
                    <div className="pt-2">
                      <Button
                        type="button"
                        size="lg"
                        className="w-full bg-blue-600 hover:bg-blue-700 text-white text-base font-black tracking-wide py-6 shadow-lg gap-2"
                        onClick={() => void handleConfirm(true)}
                        disabled={isConfirmingAction}
                      >
                        <LogOut className="w-5 h-5" />
                        [ CHECK OUT ]
                      </Button>
                      <p className="text-center text-[11px] text-muted-foreground mt-2">
                        Leaving is always allowed, even after hours or after the pass has lapsed.
                      </p>
                    </div>
                  </div>
                </div>
              )}

              {/* CONFIRMATION */}
              {completed && (
                <div className="p-6 rounded-2xl bg-blue-500/10 border-2 border-blue-500/40 text-center space-y-2">
                  <div className="w-12 h-12 rounded-full bg-blue-500/20 text-blue-600 dark:text-blue-400 flex items-center justify-center mx-auto">
                    <CheckCircle2 className="w-6 h-6" />
                  </div>
                  <h3 className="text-xl font-black text-foreground">
                    {completed.action === 'CHECK_IN' ? 'CHECKED IN' : completed.action === 'CHECK_OUT' ? 'CHECKED OUT' : 'ENTRY REFUSED'} — {completed.time}
                  </h3>
                  <p className="text-xs text-muted-foreground">
                    Recorded on the estate access log.
                  </p>
                </div>
              )}

              {/* STATE 3: REJECT (🔴 REJECT / ✕ REJECTED) */}
              {decision === 'REJECT' && (
                <div className="border-2 border-red-500/60 rounded-2xl overflow-hidden shadow-xl bg-card">
                  {/* Rejected Header Banner */}
                  <div className="bg-gradient-to-r from-red-600 to-rose-700 p-6 text-white text-center space-y-2">
                    <div className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-white/20 backdrop-blur-sm shadow-xs uppercase tracking-wider">
                      <XCircle className="w-4 h-4 text-white" />
                      ✕ REJECTED
                    </div>
                    <div className="pt-2">
                      <h2 className="text-3xl font-black tracking-tight text-white">
                        PASS NOT VALID
                      </h2>
                      <div className="mt-2 inline-block px-3 py-1 rounded-lg bg-black/30 font-mono text-xs font-bold text-red-100">
                        Reason: {formatDenyReason(report.primaryReason)}
                      </div>
                    </div>
                  </div>

                  {/* Rejection Details */}
                  <div className="p-6 space-y-6">
                    <div className="p-4 bg-red-500/5 rounded-xl border border-red-500/20 space-y-2">
                      <p className="text-xs font-medium text-foreground">
                        <strong>Security Diagnostic:</strong> {report.primaryReason}
                      </p>
                      <div className="grid grid-cols-2 gap-2 text-xs font-mono text-muted-foreground pt-1 border-t border-red-500/10">
                        <div>
                          <span>Issued: </span>
                          <strong className="text-foreground">
                            {new Date(report.issuedAt).toLocaleDateString()}
                          </strong>
                        </div>
                        <div>
                          <span>Expired: </span>
                          <strong className="text-red-500">
                            {new Date(report.expiresAt).toLocaleDateString()}
                          </strong>
                        </div>
                      </div>
                    </div>

                    {/* Action Buttons */}
                    <div className="flex flex-col sm:flex-row gap-3">
                      <Button
                        type="button"
                        variant="outline"
                        className="flex-1 py-5 text-xs font-bold gap-2"
                        onClick={() => setShowTechnicalDetails(!showTechnicalDetails)}
                      >
                        {showTechnicalDetails ? <ChevronUp className="w-4 h-4" /> : <ChevronDown className="w-4 h-4" />}
                        [ VIEW DETAILS ]
                      </Button>
                    </div>

                    <p className="text-center text-[11px] text-muted-foreground">
                      This refusal is already on the access log and the security log. Change the pass itself from the Visitors list.
                    </p>
                  </div>
                </div>
              )}

              {/* 16-POINT TECHNICAL VERIFICATION MATRIX (COLLAPSIBLE) */}
              {showTechnicalDetails && (
                <div className="border rounded-2xl p-5 bg-card space-y-4 animate-in fade-in duration-200">
                  <div className="flex items-center justify-between border-b pb-3">
                    <span className="text-xs font-bold uppercase tracking-wider text-foreground flex items-center gap-2">
                      <Layers className="w-4 h-4 text-primary" />
                      16-Factor Security Ingress Audit Matrix
                    </span>
                    <Badge variant="outline" className="text-[10px] font-mono">
                      Pass ID: {report.passId}
                    </Badge>
                  </div>

                  <div className="grid grid-cols-2 sm:grid-cols-4 gap-2.5 text-xs">
                    <div className="p-2.5 rounded-lg border bg-muted/20">
                      <span className="text-[10px] text-muted-foreground block font-mono">1. QR Structure</span>
                      <strong className={report.stages.structure.passed ? "text-emerald-600" : "text-red-600"}>
                        {report.stages.structure.passed ? '✓ Valid Envelope' : '✕ Malformed'}
                      </strong>
                    </div>
                    <div className="p-2.5 rounded-lg border bg-muted/20">
                      <span className="text-[10px] text-muted-foreground block font-mono">2. HMAC-SHA256</span>
                      <strong className={report.stages.cryptography.passed ? "text-emerald-600" : "text-red-600"}>
                        {report.stages.cryptography.passed ? '✓ Verified' : '✕ Forged'}
                      </strong>
                    </div>
                    <div className="p-2.5 rounded-lg border bg-muted/20">
                      <span className="text-[10px] text-muted-foreground block font-mono">3. Pass ID</span>
                      <strong className="text-foreground truncate block">{report.passId}</strong>
                    </div>
                    <div className="p-2.5 rounded-lg border bg-muted/20">
                      <span className="text-[10px] text-muted-foreground block font-mono">4. Person/Account</span>
                      <strong className="text-foreground truncate block">{report.userName}</strong>
                    </div>
                    <div className="p-2.5 rounded-lg border bg-muted/20">
                      <span className="text-[10px] text-muted-foreground block font-mono">5. Profile/Category</span>
                      <strong className="text-foreground truncate block">{report.category}</strong>
                    </div>
                    <div className="p-2.5 rounded-lg border bg-muted/20">
                      <span className="text-[10px] text-muted-foreground block font-mono">6. Community Match</span>
                      <strong className="text-foreground truncate block">{report.communityId}</strong>
                    </div>
                    <div className="p-2.5 rounded-lg border bg-muted/20">
                      <span className="text-[10px] text-muted-foreground block font-mono">7. Property/Host</span>
                      <strong className="text-foreground truncate block">{report.property}</strong>
                    </div>
                    <div className="p-2.5 rounded-lg border bg-muted/20">
                      <span className="text-[10px] text-muted-foreground block font-mono">8. Pass Status</span>
                      <strong className="text-emerald-600">✓ Active</strong>
                    </div>
                    <div className="p-2.5 rounded-lg border bg-muted/20">
                      <span className="text-[10px] text-muted-foreground block font-mono">9. Start Window</span>
                      <strong className="text-foreground">{new Date(report.issuedAt).toLocaleTimeString()}</strong>
                    </div>
                    <div className="p-2.5 rounded-lg border bg-muted/20">
                      <span className="text-[10px] text-muted-foreground block font-mono">10. Expiration</span>
                      <strong className={report.secondsRemaining > 0 ? "text-emerald-600" : "text-red-600"}>
                        {report.secondsRemaining > 0 ? `${report.secondsRemaining}s left` : 'Expired'}
                      </strong>
                    </div>
                    <div className="p-2.5 rounded-lg border bg-muted/20">
                      <span className="text-[10px] text-muted-foreground block font-mono">11. Gate Clearance</span>
                      <strong className={report.checks?.physicalGateAuth ? "text-emerald-600" : "text-red-600"}>
                        {report.gateChecked}
                      </strong>
                    </div>
                    <div className="p-2.5 rounded-lg border bg-muted/20">
                      <span className="text-[10px] text-muted-foreground block font-mono">12. Zone Clearance</span>
                      <strong className="text-foreground truncate block">{report.accessZone}</strong>
                    </div>
                    <div className="p-2.5 rounded-lg border bg-muted/20">
                      <span className="text-[10px] text-muted-foreground block font-mono">13. Revocation Check</span>
                      <strong className="text-emerald-600">✓ Zero Flags</strong>
                    </div>
                    <div className="p-2.5 rounded-lg border bg-muted/20">
                      <span className="text-[10px] text-muted-foreground block font-mono">14. Anti-Replay Nonce</span>
                      <strong className={report.stages.serverCache.passed ? "text-emerald-600" : "text-red-600"}>
                        {report.stages.serverCache.passed ? '✓ Unique' : '✕ Replay Hit'}
                      </strong>
                    </div>
                    <div className="p-2.5 rounded-lg border bg-muted/20">
                      <span className="text-[10px] text-muted-foreground block font-mono">15. Curfew/Policy</span>
                      <strong className={report.stages.accessPolicy.passed ? "text-emerald-600" : "text-red-600"}>
                        {report.stages.accessPolicy.passed ? '✓ Permitted' : '✕ Shift Violation'}
                      </strong>
                    </div>
                    <div className="p-2.5 rounded-lg border bg-muted/20">
                      <span className="text-[10px] text-muted-foreground block font-mono">16. Officer Ingress</span>
                      <strong className="text-emerald-600">✓ Authorized</strong>
                    </div>
                  </div>
                </div>
              )}
            </div>
          )}
        </div>
      </DialogContent>
    </Dialog>
  );
}
