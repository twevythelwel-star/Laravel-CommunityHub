'use client';

import React, { useState, useEffect } from 'react';
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
  Shield, 
  ShieldCheck, 
  ShieldAlert, 
  CheckCircle2, 
  XCircle, 
  Scan, 
  Clock, 
  MapPin, 
  Lock, 
  Unlock, 
  AlertTriangle,
  RotateCcw,
  Sparkles,
  Layers,
  ArrowRight,
  ExternalLink
} from 'lucide-react';
import { 
  GateId, 
  PassCategory, 
  GatePassValidationReport 
} from '@/lib/gate-pass-engine/types';
import { 
  validateGatePassToken, 
  generateDynamicGatePassToken, 
  CATEGORY_CONFIGS, 
  DEMO_PASS_DIRECTORY 
} from '@/lib/gate-pass-engine/engine';
import { CategoryShapeIcon } from '@/lib/gate-pass-engine/shapes';
import { useToast } from '@/hooks/use-toast';
import { cn } from '@/lib/utils';

interface GateScannerDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  initialToken?: string;
}

export function GateScannerDialog({
  open,
  onOpenChange,
  initialToken,
}: GateScannerDialogProps) {
  const { toast } = useToast();
  const [selectedGate, setSelectedGate] = useState<GateId>('GATE-01');
  const [inputToken, setInputToken] = useState<string>(initialToken || '');
  const [report, setReport] = useState<GatePassValidationReport | null>(null);
  const [isScanning, setIsScanning] = useState(false);

  // Auto-scan initial token if provided
  useEffect(() => {
    if (initialToken) {
      setInputToken(initialToken);
      runValidation(initialToken, selectedGate);
    }
  }, [initialToken, selectedGate]);

  const runValidation = (tokenToValidate: string, gate: GateId) => {
    setIsScanning(true);
    setTimeout(() => {
      const result = validateGatePassToken(tokenToValidate, {
        currentGate: gate,
        bypassReplayCheck: true, // Allow rapid demonstration testing
      });
      setReport(result);
      setIsScanning(false);
    }, 250);
  };

  const handleScanSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!inputToken.trim()) return;
    runValidation(inputToken.trim(), selectedGate);
  };

  // Quick preset test generators
  const handleTestPass = (profile: typeof DEMO_PASS_DIRECTORY[0]) => {
    const token = generateDynamicGatePassToken({
      passId: profile.passId,
      category: profile.category,
      userId: profile.id,
      userName: profile.userName,
      property: profile.property,
      gate: profile.gate,
      colorVariant: profile.colorVariant?.name,
    });
    setInputToken(token);
    runValidation(token, selectedGate);
  };

  // Test failure case: Expired token
  const handleTestExpired = () => {
    // Generate token with an expired past window
    const nowSec = Math.floor(Date.now() / 1000) - 120; // 2 minutes ago
    const expiredToken = `CH-PASS:v1.${btoa(JSON.stringify({
      v: 1,
      pid: 'GP-HO-EXP1',
      cat: 'HOMEOWNER',
      uid: 'usr_exp_01',
      nam: 'Expired Resident',
      prop: 'Lot 14',
      gate: 'GATE-ANY',
      vf: nowSec - 30,
      vu: nowSec,
      nonce: 'NONCE-EXP',
      t: nowSec - 30,
      sig: 'SIG-MOCK-EXPIRED',
    }))}.SIG-MOCK-EXPIRED`;
    setInputToken(expiredToken);
    runValidation(expiredToken, selectedGate);
  };

  // Test failure case: Tampered signature
  const handleTestTampered = () => {
    const validToken = generateDynamicGatePassToken({
      passId: 'GP-HO-TAMPER',
      category: 'HOMEOWNER',
      userId: 'usr_tamper',
      userName: 'Forged Identity',
      property: 'Lot 99',
    });
    // Tamper with signature
    const tamperedToken = validToken.slice(0, -4) + 'XXXX';
    setInputToken(tamperedToken);
    runValidation(tamperedToken, selectedGate);
  };

  // Test failure case: Out of shift Staff
  const handleTestOutOfShiftStaff = () => {
    const token = generateDynamicGatePassToken({
      passId: 'GP-STF-NIGHT',
      category: 'STAFF',
      userId: 'usr_staff_night',
      userName: 'Night Ingress Worker',
      property: 'Maintenance Depot',
      gate: 'GATE-01',
    });
    // Simulate night time scan at 2:00 AM
    const nightDate = new Date();
    nightDate.setHours(2, 30, 0, 0);

    setIsScanning(true);
    setTimeout(() => {
      const result = validateGatePassToken(token, {
        currentGate: selectedGate,
        currentDate: nightDate,
        bypassReplayCheck: true,
      });
      setReport(result);
      setIsScanning(false);
    }, 250);
  };

  // Test failure case: Non-GPE Generic QR
  const handleTestNonGPE = () => {
    const genericQR = 'https://example.com/unauthorized-visitor-checkin';
    setInputToken(genericQR);
    runValidation(genericQR, selectedGate);
  };

  const handleGrantIngress = () => {
    toast({
      title: "Ingress Authorized & Gate Opened",
      description: `Barrier raised at ${selectedGate} for ${report?.userName} (${report?.passId}).`,
    });
  };

  const handleLogIncident = () => {
    toast({
      variant: "destructive",
      title: "Security Incident Recorded",
      description: `Disallowed ingress flagged at ${selectedGate}: ${report?.primaryReason}`,
    });
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-2xl max-h-[92vh] overflow-y-auto p-0 border shadow-2xl bg-card">
        {/* Modal Header */}
        <div className="p-6 pb-4 border-b bg-muted/40 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div className="flex items-center gap-2.5">
            <div className="w-10 h-10 rounded-xl bg-primary/10 border border-primary/20 flex items-center justify-center text-primary shadow-sm">
              <Scan className="w-5 h-5" />
            </div>
            <div>
              <DialogTitle className="text-xl font-bold text-foreground">
                Gate Pass Security Scanner & Verifier
              </DialogTitle>
              <DialogDescription className="text-xs text-muted-foreground">
                Digital Gate Pass Engine (GPE) • Multi-Tier Recognition & Cryptographic Validator
              </DialogDescription>
            </div>
          </div>

          {/* Gate Selector */}
          <div className="flex items-center gap-1.5 bg-background border rounded-lg p-1 text-xs">
            <span className="text-muted-foreground px-2 font-mono text-[11px]">PORTAL:</span>
            <button
              type="button"
              onClick={() => setSelectedGate('GATE-01')}
              className={cn(
                "px-2.5 py-1 rounded font-bold transition-colors",
                selectedGate === 'GATE-01' 
                  ? "bg-primary text-primary-foreground shadow-xs" 
                  : "text-muted-foreground hover:text-foreground"
              )}
            >
              Main Gate (01)
            </button>
            <button
              type="button"
              onClick={() => setSelectedGate('GATE-02')}
              className={cn(
                "px-2.5 py-1 rounded font-bold transition-colors",
                selectedGate === 'GATE-02' 
                  ? "bg-primary text-primary-foreground shadow-xs" 
                  : "text-muted-foreground hover:text-foreground"
              )}
            >
              North Gate (02)
            </button>
          </div>
        </div>

        <div className="p-6 space-y-6">
          {/* Token Input Form */}
          <form onSubmit={handleScanSubmit} className="space-y-2">
            <label className="text-xs font-bold text-foreground uppercase tracking-wider block">
              Scan / Paste Token Payload:
            </label>
            <div className="flex gap-2">
              <Input
                value={inputToken}
                onChange={(e) => setInputToken(e.target.value)}
                placeholder="Scan QR or paste 'CH-GPE:v1....'"
                className="font-mono text-xs h-9 bg-muted/20"
              />
              <Button type="submit" size="sm" className="h-9 px-4 text-xs font-bold gap-1.5" disabled={isScanning}>
                <Scan className="w-3.5 h-3.5" />
                <span>Verify</span>
              </Button>
            </div>
          </form>

          {/* Quick Simulation Profiles */}
          <div className="space-y-2">
            <span className="text-[11px] font-bold text-muted-foreground uppercase tracking-wider block">
              Quick Test Simulation Passes (All 7 Profile Shapes):
            </span>
            <div className="grid grid-cols-2 sm:grid-cols-3 gap-2">
              {DEMO_PASS_DIRECTORY.slice(0, 8).map((pass) => {
                const conf = CATEGORY_CONFIGS[pass.category];
                const activeColor = pass.colorVariant?.hex || conf.themeColor;
                const colorName = pass.colorVariant?.name.split(' ')[0] || 'Class';
                return (
                  <button
                    key={pass.id}
                    type="button"
                    onClick={() => handleTestPass(pass)}
                    className="p-2 rounded-lg border text-left flex items-center gap-2 hover:bg-muted/70 transition-colors bg-card shadow-xs group"
                  >
                    <div 
                      className="w-6 h-6 rounded-md flex items-center justify-center shrink-0 border border-white/20 text-white shadow-xs"
                      style={{ backgroundColor: activeColor }}
                    >
                      <CategoryShapeIcon shape={conf.shape} className="w-3.5 h-3.5" />
                    </div>
                    <div className="truncate flex-1">
                      <div className="text-xs font-semibold text-foreground truncate group-hover:text-primary">
                        {pass.userName}
                      </div>
                      <div className="text-[10px] text-muted-foreground truncate font-mono flex items-center gap-1">
                        <span>{conf.shape}</span>
                        <span>•</span>
                        <span className="font-semibold text-foreground/80">{colorName}</span>
                      </div>
                    </div>
                  </button>
                );
              })}
            </div>

            {/* Test Failure Scenarios */}
            <div className="pt-2 flex flex-wrap gap-2 items-center">
              <span className="text-[10px] font-mono font-bold text-muted-foreground">ATTACK / REJECTION SIMULATIONS:</span>
              <Button 
                variant="outline" 
                size="sm" 
                onClick={handleTestNonGPE}
                className="h-6 text-[10px] text-purple-600 border-purple-500/30 hover:bg-purple-500/10"
              >
                Test Generic Non-GPE QR
              </Button>
              <Button 
                variant="outline" 
                size="sm" 
                onClick={handleTestExpired}
                className="h-6 text-[10px] text-amber-600 border-amber-500/30 hover:bg-amber-500/10"
              >
                Test Expired Token
              </Button>
              <Button 
                variant="outline" 
                size="sm" 
                onClick={handleTestTampered}
                className="h-6 text-[10px] text-red-600 border-red-500/30 hover:bg-red-500/10"
              >
                Test Forged Signature
              </Button>
              <Button 
                variant="outline" 
                size="sm" 
                onClick={handleTestOutOfShiftStaff}
                className="h-6 text-[10px] text-orange-600 border-orange-500/30 hover:bg-orange-500/10"
              >
                Test Out-of-Shift Staff
              </Button>
            </div>
          </div>

          {/* ── VALIDATION DECISION DISPLAY ── */}
          {report && (
            <div className="border rounded-2xl overflow-hidden shadow-lg transition-all animate-in fade-in duration-300">
              {/* PRIMARY DECISION BANNER */}
              <div 
                className={cn(
                  "p-5 text-white flex flex-col sm:flex-row sm:items-center justify-between gap-4",
                  report.status === 'ALLOW' 
                    ? "bg-gradient-to-r from-emerald-600 to-teal-700" 
                    : "bg-gradient-to-r from-red-600 to-rose-700"
                )}
              >
                <div className="flex items-center gap-3">
                  <div className="w-12 h-12 rounded-2xl bg-white/20 border-2 border-white/40 flex items-center justify-center shrink-0 shadow-md">
                    {report.status === 'ALLOW' ? (
                      <CheckCircle2 className="w-7 h-7 text-white" />
                    ) : (
                      <XCircle className="w-7 h-7 text-white" />
                    )}
                  </div>
                  <div>
                    <div className="flex items-center gap-2">
                      <span className="font-mono text-xs font-bold uppercase tracking-wider bg-black/30 px-2 py-0.5 rounded">
                        {report.status === 'ALLOW' ? '🟢 VALID' : '🔴 INVALID'}
                      </span>
                      <span className="font-mono text-xs text-white/80">
                        {report.passId}
                      </span>
                    </div>
                    <h2 className="text-2xl font-black tracking-tight mt-1">
                      {report.status === 'ALLOW' ? 'ACCESS: APPROVED' : 'ACCESS: DENIED'}
                    </h2>
                  </div>
                </div>

                {/* Category & Shape Chip */}
                <div className="bg-black/30 backdrop-blur-sm border border-white/20 rounded-xl p-2.5 px-4 flex items-center gap-3">
                  <CategoryShapeIcon shape={report.shape} className="w-6 h-6 text-white" />
                  <div className="leading-tight">
                    <span className="text-[10px] text-white/70 font-mono uppercase block">{report.shape} SHAPE</span>
                    <strong className="text-sm text-white font-bold">{report.category}</strong>
                  </div>
                </div>
              </div>

              {/* DETAILS & REASON */}
              <div className="p-5 bg-card space-y-4">
                {report.status === 'DENY' && (
                  <div className="p-3.5 rounded-xl bg-red-500/10 border border-red-500/20 text-red-600 dark:text-red-400 text-xs flex items-start gap-2.5">
                    <AlertTriangle className="w-4 h-4 shrink-0 mt-0.5" />
                    <div>
                      <strong className="block font-bold">REASON FOR DENIAL:</strong>
                      <span className="font-mono">{report.primaryReason}</span>
                    </div>
                  </div>
                )}

                {/* Holder & Property Info */}
                <div className="grid grid-cols-2 sm:grid-cols-3 gap-3 text-xs">
                  <div className="p-3 bg-muted/40 rounded-xl border">
                    <span className="text-muted-foreground text-[10px] uppercase font-mono block">Pass Holder</span>
                    <strong className="text-foreground text-sm">{report.userName}</strong>
                  </div>
                  <div className="p-3 bg-muted/40 rounded-xl border">
                    <span className="text-muted-foreground text-[10px] uppercase font-mono block">Assigned Property</span>
                    <strong className="text-foreground text-sm">{report.property}</strong>
                  </div>
                  <div className="p-3 bg-muted/40 rounded-xl border col-span-2 sm:col-span-1">
                    <span className="text-muted-foreground text-[10px] uppercase font-mono block">Scanned Ingress Portal</span>
                    <strong className="text-foreground text-sm font-mono">{report.gateChecked}</strong>
                  </div>
                </div>

                {/* ── 6-FACTOR IMMEDIATE SECURITY VERIFICATION MATRIX ── */}
                <div className="space-y-2 pt-2 border-t">
                  <div className="flex items-center justify-between">
                    <span className="text-[11px] font-bold text-foreground uppercase tracking-wider block">
                      6-Factor Officer Ingress Verification Matrix:
                    </span>
                    <span className="text-[10px] font-mono text-muted-foreground">
                      GPE ID: {report.communityId} • {report.accessZone}
                    </span>
                  </div>

                  <div className="grid grid-cols-2 sm:grid-cols-3 gap-2">
                    {/* Factor 1: Shape */}
                    <div className={cn(
                      "p-2.5 rounded-xl border flex flex-col justify-between gap-1 transition-colors",
                      report.checks?.shapeCategoryMatch
                        ? "bg-emerald-500/5 border-emerald-500/30"
                        : "bg-red-500/5 border-red-500/30"
                    )}>
                      <div className="flex items-center justify-between">
                        <span className="text-[10px] font-mono font-bold uppercase text-muted-foreground">1. SHAPE</span>
                        {report.checks?.shapeCategoryMatch ? (
                          <CheckCircle2 className="w-3.5 h-3.5 text-emerald-600" />
                        ) : (
                          <XCircle className="w-3.5 h-3.5 text-red-600" />
                        )}
                      </div>
                      <div className="flex items-center gap-1.5 mt-0.5">
                        <CategoryShapeIcon shape={report.shape} className="w-3.5 h-3.5 text-foreground shrink-0" />
                        <span className="text-xs font-bold text-foreground truncate">{report.shape}</span>
                      </div>
                      <span className="text-[10px] text-muted-foreground font-mono truncate">
                        = {report.category}
                      </span>
                    </div>

                    {/* Factor 2: Color */}
                    <div className={cn(
                      "p-2.5 rounded-xl border flex flex-col justify-between gap-1 transition-colors",
                      report.checks?.colorClassMatch
                        ? "bg-emerald-500/5 border-emerald-500/30"
                        : "bg-red-500/5 border-red-500/30"
                    )}>
                      <div className="flex items-center justify-between">
                        <span className="text-[10px] font-mono font-bold uppercase text-muted-foreground">2. COLOR</span>
                        <Badge variant="outline" className="text-[9px] px-1 py-0 h-4 border-emerald-500/30 text-emerald-600">
                          APPROVED
                        </Badge>
                      </div>
                      <div className="flex items-center gap-1.5 mt-0.5">
                        <div 
                          className="w-3 h-3 rounded-full border border-white/30 shrink-0" 
                          style={{ backgroundColor: report.visualIdentity?.colorHex || CATEGORY_CONFIGS[report.category]?.themeColor || '#64748b' }}
                        />
                        <span className="text-xs font-bold text-foreground truncate">
                          {report.visualIdentity?.colorVariantName || 'Assigned Palette'}
                        </span>
                      </div>
                      <span className="text-[10px] text-muted-foreground font-mono truncate">
                        = Visual Identifier (Non-Credential)
                      </span>
                    </div>

                    {/* Factor 3: QR Payload */}
                    <div className={cn(
                      "p-2.5 rounded-xl border flex flex-col justify-between gap-1 transition-colors",
                      report.checks?.payloadAuthenticity
                        ? "bg-emerald-500/5 border-emerald-500/30"
                        : "bg-red-500/5 border-red-500/30"
                    )}>
                      <div className="flex items-center justify-between">
                        <span className="text-[10px] font-mono font-bold uppercase text-muted-foreground">3. PAYLOAD</span>
                        {report.checks?.payloadAuthenticity ? (
                          <CheckCircle2 className="w-3.5 h-3.5 text-emerald-600" />
                        ) : (
                          <XCircle className="w-3.5 h-3.5 text-red-600" />
                        )}
                      </div>
                      <span className="text-xs font-bold text-foreground truncate mt-0.5">
                        {report.userName || 'Unknown'}
                      </span>
                      <span className="text-[10px] text-muted-foreground font-mono truncate">
                        = {report.passId}
                      </span>
                    </div>

                    {/* Factor 4: Signature */}
                    <div className={cn(
                      "p-2.5 rounded-xl border flex flex-col justify-between gap-1 transition-colors",
                      report.checks?.cryptographicSignature
                        ? "bg-emerald-500/5 border-emerald-500/30"
                        : "bg-red-500/5 border-red-500/30"
                    )}>
                      <div className="flex items-center justify-between">
                        <span className="text-[10px] font-mono font-bold uppercase text-muted-foreground">4. SIGNATURE</span>
                        {report.checks?.cryptographicSignature ? (
                          <CheckCircle2 className="w-3.5 h-3.5 text-emerald-600" />
                        ) : (
                          <XCircle className="w-3.5 h-3.5 text-red-600" />
                        )}
                      </div>
                      <span className="text-xs font-bold text-foreground truncate mt-0.5">
                        HMAC-SHA256
                      </span>
                      <span className="text-[10px] text-muted-foreground font-mono truncate">
                        = GPE Engine Auth
                      </span>
                    </div>

                    {/* Factor 5: GPS / Gate */}
                    <div className={cn(
                      "p-2.5 rounded-xl border flex flex-col justify-between gap-1 transition-colors",
                      report.checks?.physicalGateAuth
                        ? "bg-emerald-500/5 border-emerald-500/30"
                        : "bg-red-500/5 border-red-500/30"
                    )}>
                      <div className="flex items-center justify-between">
                        <span className="text-[10px] font-mono font-bold uppercase text-muted-foreground">5. GPS / GATE</span>
                        {report.checks?.physicalGateAuth ? (
                          <CheckCircle2 className="w-3.5 h-3.5 text-emerald-600" />
                        ) : (
                          <XCircle className="w-3.5 h-3.5 text-red-600" />
                        )}
                      </div>
                      <span className="text-xs font-bold text-foreground truncate mt-0.5 font-mono">
                        {report.gateChecked}
                      </span>
                      <span className="text-[10px] text-muted-foreground font-mono truncate">
                        = Physical Clearance
                      </span>
                    </div>

                    {/* Factor 6: Time */}
                    <div className={cn(
                      "p-2.5 rounded-xl border flex flex-col justify-between gap-1 transition-colors",
                      report.checks?.temporalTimeAuth
                        ? "bg-emerald-500/5 border-emerald-500/30"
                        : "bg-red-500/5 border-red-500/30"
                    )}>
                      <div className="flex items-center justify-between">
                        <span className="text-[10px] font-mono font-bold uppercase text-muted-foreground">6. TIME</span>
                        {report.checks?.temporalTimeAuth ? (
                          <CheckCircle2 className="w-3.5 h-3.5 text-emerald-600" />
                        ) : (
                          <XCircle className="w-3.5 h-3.5 text-red-600" />
                        )}
                      </div>
                      <span className="text-xs font-bold text-foreground truncate mt-0.5">
                        {report.secondsRemaining > 0 ? `${report.secondsRemaining}s left` : 'Expired'}
                      </span>
                      <span className="text-[10px] text-muted-foreground font-mono truncate">
                        = Temporal Auth
                      </span>
                    </div>
                  </div>

                  {/* Security Principle Callout */}
                  <div className="p-2.5 rounded-lg bg-blue-500/5 border border-blue-500/20 text-[11px] text-muted-foreground flex items-start gap-2">
                    <ShieldCheck className="w-4 h-4 text-blue-600 shrink-0 mt-0.5" />
                    <div>
                      <strong className="text-foreground font-semibold block">Security Decoupling Principle:</strong>
                      <span>Color is a human visual differentiator from the approved WCAG palette to prevent optical collisions. Access clearance is granted strictly via cryptographic HMAC signature and active token verification.</span>
                    </div>
                  </div>
                </div>

                {/* ── 4-LAYER VALIDATION BREAKDOWN ── */}
                <div className="space-y-2 pt-2 border-t">
                  <span className="text-[11px] font-bold text-foreground uppercase tracking-wider block">
                    Three-Layer Verification Breakdown:
                  </span>
                  
                  <div className="grid gap-2">
                    {/* Stage 1: Structure */}
                    <div className="flex items-center justify-between p-2.5 rounded-lg border bg-muted/20 text-xs">
                      <div className="flex items-center gap-2">
                        {report.stages.structure.passed ? (
                          <CheckCircle2 className="w-4 h-4 text-emerald-600 shrink-0" />
                        ) : (
                          <XCircle className="w-4 h-4 text-red-600 shrink-0" />
                        )}
                        <div>
                          <strong className="text-foreground">1. QR Structure & Format:</strong>
                          <span className="text-muted-foreground ml-1">{report.stages.structure.message}</span>
                        </div>
                      </div>
                      <Badge variant="outline" className={report.stages.structure.passed ? "text-emerald-600 border-emerald-500/30" : "text-red-600 border-red-500/30"}>
                        {report.stages.structure.passed ? "PASSED" : "FAILED"}
                      </Badge>
                    </div>

                    {/* Stage 2: Cryptography */}
                    <div className="flex items-center justify-between p-2.5 rounded-lg border bg-muted/20 text-xs">
                      <div className="flex items-center gap-2">
                        {report.stages.cryptography.passed ? (
                          <CheckCircle2 className="w-4 h-4 text-emerald-600 shrink-0" />
                        ) : (
                          <XCircle className="w-4 h-4 text-red-600 shrink-0" />
                        )}
                        <div>
                          <strong className="text-foreground">2. Cryptographic Signature:</strong>
                          <span className="text-muted-foreground ml-1">{report.stages.cryptography.message}</span>
                        </div>
                      </div>
                      <Badge variant="outline" className={report.stages.cryptography.passed ? "text-emerald-600 border-emerald-500/30" : "text-red-600 border-red-500/30"}>
                        {report.stages.cryptography.passed ? "VERIFIED" : "TAMPERED"}
                      </Badge>
                    </div>

                    {/* Stage 3: Server/Cache */}
                    <div className="flex items-center justify-between p-2.5 rounded-lg border bg-muted/20 text-xs">
                      <div className="flex items-center gap-2">
                        {report.stages.serverCache.passed ? (
                          <CheckCircle2 className="w-4 h-4 text-emerald-600 shrink-0" />
                        ) : (
                          <XCircle className="w-4 h-4 text-red-600 shrink-0" />
                        )}
                        <div>
                          <strong className="text-foreground">3. Server/Cache & Anti-Replay:</strong>
                          <span className="text-muted-foreground ml-1">{report.stages.serverCache.message}</span>
                        </div>
                      </div>
                      <Badge variant="outline" className={report.stages.serverCache.passed ? "text-emerald-600 border-emerald-500/30" : "text-red-600 border-red-500/30"}>
                        {report.stages.serverCache.passed ? "ACTIVE" : "FLAGGED"}
                      </Badge>
                    </div>

                    {/* Stage 4: Access Policy */}
                    <div className="flex items-center justify-between p-2.5 rounded-lg border bg-muted/20 text-xs">
                      <div className="flex items-center gap-2">
                        {report.stages.accessPolicy.passed ? (
                          <CheckCircle2 className="w-4 h-4 text-emerald-600 shrink-0" />
                        ) : (
                          <XCircle className="w-4 h-4 text-red-600 shrink-0" />
                        )}
                        <div>
                          <strong className="text-foreground">4. Category Access Policy:</strong>
                          <span className="text-muted-foreground ml-1">{report.stages.accessPolicy.message}</span>
                        </div>
                      </div>
                      <Badge variant="outline" className={report.stages.accessPolicy.passed ? "text-emerald-600 border-emerald-500/30" : "text-red-600 border-red-500/30"}>
                        {report.stages.accessPolicy.passed ? "CLEARANCE OK" : "POLICY DENY"}
                      </Badge>
                    </div>
                  </div>
                </div>

                {/* Authorized Zones Preview */}
                {report.status === 'ALLOW' && (
                  <div className="p-3 bg-emerald-500/5 border border-emerald-500/20 rounded-xl space-y-1">
                    <span className="text-[10px] font-mono uppercase text-emerald-600 font-bold block">
                      Granted Clearance Zones ({report.category}):
                    </span>
                    <ul className="text-xs text-muted-foreground space-y-0.5 list-disc list-inside">
                      {report.policy.authorizedZones.map((zone, idx) => (
                        <li key={idx}><span className="text-foreground font-medium">{zone}</span></li>
                      ))}
                    </ul>
                  </div>
                )}

                {/* Security Officer Action Footer */}
                <div className="pt-3 border-t flex flex-wrap gap-2 justify-end">
                  <Button 
                    variant="outline" 
                    size="sm" 
                    onClick={handleLogIncident}
                    className="text-xs text-red-600 border-red-500/30 hover:bg-red-500/10"
                  >
                    Flag Incident Report
                  </Button>
                  {report.status === 'ALLOW' && (
                    <Button 
                      size="sm" 
                      onClick={handleGrantIngress}
                      className="text-xs font-bold gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white shadow-md"
                    >
                      <Unlock className="w-3.5 h-3.5" />
                      <span>Authorize Ingress & Open Gate</span>
                    </Button>
                  )}
                </div>
              </div>
            </div>
          )}
        </div>
      </DialogContent>
    </Dialog>
  );
}
