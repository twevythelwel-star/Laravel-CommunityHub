'use client';

import React, { useState, useEffect, useMemo, useCallback } from 'react';
import { 
  PassCategory, 
  GateId,
  ApprovedColorVariant,
} from '@/lib/gate-pass-engine/types';
import { 
  CATEGORY_CONFIGS, 
  CATEGORY_POLICIES, 
  CATEGORY_PALETTES,
  generateDynamicGatePassToken, 
  formatPassId,
  getAssignedColorVariant,
  rotatePassVisualIdentity,
} from '@/lib/gate-pass-engine/engine';
import { CategoryShapeIcon } from '@/lib/gate-pass-engine/shapes';
import { ProfileCustomQRCode } from './ProfileCustomQRCode';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { 
  Copy, 
  Check, 
  RotateCw, 
  ShieldCheck, 
  Clock, 
  MapPin, 
  Smartphone, 
  Apple,
  ExternalLink,
  Sparkles,
  Layers,
  AlertTriangle,
  Eye,
  Shield,
  Palette
} from 'lucide-react';
import { useToast } from '@/hooks/use-toast';
import { cn } from '@/lib/utils';

export interface GatePassCardProps {
  category: PassCategory;
  userName: string;
  property: string;
  passId?: string;
  userId?: string;
  gate?: GateId;
  photoUrl?: string;
  status?: 'ACTIVE' | 'EXPIRED' | 'REVOKED';
  initialColorVariant?: ApprovedColorVariant;
  onScanInVerifier?: (token: string) => void;
  className?: string;
  compact?: boolean;
}

export function GatePassCard({
  category,
  userName,
  property,
  passId,
  userId = 'usr_1042',
  gate = 'GATE-ANY',
  photoUrl,
  status = 'ACTIVE',
  initialColorVariant,
  onScanInVerifier,
  className,
  compact = false,
}: GatePassCardProps) {
  const { toast } = useToast();
  const [copied, setCopied] = useState(false);
  const [secondsLeft, setSecondsLeft] = useState(30);
  const [isRotating, setIsRotating] = useState(false);
  const [qrViewMode, setQrViewMode] = useState<'standard' | 'shield_enclosure' | 'matrix_diagnostic'>('standard');
  const [visualSeq, setVisualSeq] = useState(1);

  const resolvedPassId = passId || formatPassId(category, userId);
  const [activeColorVariant, setActiveColorVariant] = useState<ApprovedColorVariant>(() => 
    initialColorVariant || getAssignedColorVariant(category, resolvedPassId, 1)
  );

  const config = CATEGORY_CONFIGS[category] || CATEGORY_CONFIGS.HOMEOWNER;
  const policy = CATEGORY_POLICIES[category] || CATEGORY_POLICIES.HOMEOWNER;

  // Generate dynamic rolling token
  const [currentToken, setCurrentToken] = useState(() => 
    generateDynamicGatePassToken({
      passId: resolvedPassId,
      category,
      userId,
      userName,
      property,
      gate,
      validDurationSeconds: 30,
      colorVariant: activeColorVariant.name,
      rotationSeq: 1,
    })
  );

  const rotateToken = useCallback(() => {
    setIsRotating(true);
    const newToken = generateDynamicGatePassToken({
      passId: resolvedPassId,
      category,
      userId,
      userName,
      property,
      gate,
      validDurationSeconds: 30,
      colorVariant: activeColorVariant.name,
      rotationSeq: visualSeq,
    });
    setCurrentToken(newToken);
    setSecondsLeft(30);
    setTimeout(() => setIsRotating(false), 600);
  }, [resolvedPassId, category, userId, userName, property, gate, activeColorVariant.name, visualSeq]);

  // On-demand visual regeneration: roll to next approved color variant
  const handleRollVisualIdentity = () => {
    setIsRotating(true);
    const result = rotatePassVisualIdentity(resolvedPassId, category, visualSeq);
    setVisualSeq(result.nextSeq);
    setActiveColorVariant(result.newVariant);

    const newToken = generateDynamicGatePassToken({
      passId: resolvedPassId,
      category,
      userId,
      userName,
      property,
      gate,
      validDurationSeconds: 30,
      colorVariant: result.newVariant.name,
      rotationSeq: result.nextSeq,
    });
    setCurrentToken(newToken);
    setSecondsLeft(30);
    setTimeout(() => setIsRotating(false), 600);

    toast({
      title: "Visual Identity Rotated",
      description: `Assigned color: ${result.newVariant.name} (${result.newVariant.hex}). Pass remains 100% cryptographically valid.`,
    });
  };

  // Rolling countdown timer (30 seconds anti-screenshot protection)
  useEffect(() => {
    if (status !== 'ACTIVE') return;

    const interval = setInterval(() => {
      setSecondsLeft((prev) => {
        if (prev <= 1) {
          rotateToken();
          return 30;
        }
        return prev - 1;
      });
    }, 1000);

    return () => clearInterval(interval);
  }, [rotateToken, status]);

  const handleCopyPassId = () => {
    navigator.clipboard.writeText(resolvedPassId);
    setCopied(true);
    toast({
      title: "Pass ID Copied",
      description: `${resolvedPassId} copied to clipboard.`,
    });
    setTimeout(() => setCopied(false), 2000);
  };

  const operationalHoursLabel = policy.operationalHours.is24Hours 
    ? '24/7 Access' 
    : `${policy.operationalHours.startHour?.toString().padStart(2, '0')}:00–${policy.operationalHours.endHour?.toString().padStart(2, '0')}:00`;

  return (
    <div 
      className={cn(
        "relative rounded-2xl overflow-hidden shadow-2xl border text-white transition-all duration-300",
        `bg-gradient-to-br ${config.gradient}`,
        config.badgeBorder,
        className
      )}
    >
      {/* ── Background Subtle Geometric Watermark ── */}
      <div 
        className="absolute -right-12 -top-12 opacity-10 pointer-events-none transform rotate-12"
      >
        <CategoryShapeIcon shape={config.shape} className="w-64 h-64 text-white" />
      </div>

      {/* ── Card Header: Digital Pass Banner ── */}
      <div className="p-5 pb-3 border-b border-white/10 flex items-center justify-between relative z-10">
        <div className="flex items-center gap-2">
          <div 
            className="w-7 h-7 rounded-lg flex items-center justify-center shadow-md border border-white/20"
            style={{ backgroundColor: config.themeColor }}
          >
            <CategoryShapeIcon shape={config.shape} className="w-4 h-4 text-white" />
          </div>
          <div>
            <h3 className="font-mono text-xs font-bold uppercase tracking-widest text-white/90">
              COMMUNITY DIGITAL PASS
            </h3>
            <p className="text-[10px] text-white/60 font-mono">
              {config.shapeLabel} • {config.displayName}
            </p>
          </div>
        </div>

        {/* Status Badge */}
        <Badge 
          className={cn(
            "text-[10px] font-bold px-2.5 py-0.5 border shadow-sm flex items-center gap-1",
            status === 'ACTIVE' 
              ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40' 
              : 'bg-red-500/20 text-red-300 border-red-500/40'
          )}
        >
          <span 
            className={cn(
              "w-1.5 h-1.5 rounded-full inline-block",
              status === 'ACTIVE' ? "bg-emerald-400 animate-ping" : "bg-red-400"
            )}
          />
          <span>{status === 'ACTIVE' ? 'ACTIVE' : status}</span>
        </Badge>
      </div>

      {/* ── Pass Holder Identity Bar ── */}
      <div className="px-5 pt-3 flex items-center justify-between relative z-10">
        <div>
          <h2 className="text-lg font-extrabold text-white tracking-tight leading-tight">
            {userName}
          </h2>
          <div className="flex items-center gap-2 text-xs text-white/70 mt-0.5">
            <MapPin className="w-3 h-3 text-white/50" />
            <span className="font-medium text-white/90">{property}</span>
            <span className="text-white/30">•</span>
            <span className="font-mono text-[11px] text-white/80">{gate}</span>
          </div>
        </div>

        {/* One-click Copy Pass ID */}
        <button
          onClick={handleCopyPassId}
          type="button"
          className="flex items-center gap-1.5 bg-black/30 hover:bg-black/50 border border-white/15 px-2.5 py-1 rounded-lg font-mono text-xs font-bold text-white transition-colors cursor-pointer"
          title="Click to copy Pass ID"
        >
          <span>{resolvedPassId}</span>
          {copied ? <Check className="w-3 h-3 text-emerald-400" /> : <Copy className="w-3 h-3 text-white/60" />}
        </button>
      </div>

      {/* ── Profile QR Geometry View Mode Selector ── */}
      <div className="px-5 pt-3 flex items-center justify-center gap-1.5 relative z-10">
        <div className="bg-black/40 p-0.5 rounded-lg border border-white/15 flex items-center gap-1 text-[10px] font-mono">
          <button
            type="button"
            onClick={() => setQrViewMode('standard')}
            className={cn(
              "px-2.5 py-0.5 rounded-md transition-all font-semibold flex items-center gap-1",
              qrViewMode === 'standard' ? "bg-white text-slate-900 shadow-sm" : "text-white/70 hover:text-white"
            )}
          >
            <CategoryShapeIcon shape={config.shape} className="w-3 h-3" />
            <span>Profile Bezel</span>
          </button>
          <button
            type="button"
            onClick={() => setQrViewMode('shield_enclosure')}
            className={cn(
              "px-2.5 py-0.5 rounded-md transition-all font-semibold flex items-center gap-1",
              qrViewMode === 'shield_enclosure' ? "bg-white text-slate-900 shadow-sm" : "text-white/70 hover:text-white"
            )}
          >
            <Shield className="w-3 h-3" />
            <span>Shield Enclosure</span>
          </button>
        </div>
      </div>

      {/* ── Central QR Code Framed in Profile-Specific Custom Design ── */}
      <div className="py-4 flex flex-col items-center justify-center relative z-10">
        <ProfileCustomQRCode
          value={currentToken}
          category={category}
          size={compact ? 150 : 175}
          viewMode={qrViewMode}
          themeColor={activeColorVariant.hex}
          accentColor={activeColorVariant.accentHex}
          colorVariantName={activeColorVariant.name}
          showScanGlow={status === 'ACTIVE'}
        />

        {/* Dual Visual Identity & Dynamic Token Controls */}
        <div className="mt-4 flex flex-wrap items-center justify-center gap-2">
          {/* Dynamic Rolling Token Countdown Indicator */}
          <div className="flex items-center gap-2 bg-black/40 border border-white/15 px-3 py-1 rounded-full text-xs font-mono">
            <button 
              type="button" 
              onClick={rotateToken}
              className="text-white/60 hover:text-white transition-colors"
              title="Force rotate dynamic token"
            >
              <RotateCw className={cn("w-3 h-3", isRotating && "animate-spin text-emerald-400")} />
            </button>
            <span className="text-white/70 text-[11px]">
              Rolling Window:
            </span>
            <span className="font-bold text-emerald-400 min-w-[28px] text-right">
              {secondsLeft}s
            </span>
          </div>

          {/* Controlled Random Color / Visual Regeneration Button */}
          <button
            type="button"
            onClick={handleRollVisualIdentity}
            className="flex items-center gap-1.5 bg-black/40 hover:bg-black/60 border border-white/15 hover:border-white/30 px-3 py-1 rounded-full text-xs font-mono text-white transition-all cursor-pointer group shadow-sm"
            title="Rotate visual color variant to prevent static screenshot reuse while maintaining cryptographic validity"
          >
            <Palette className="w-3 h-3 text-amber-400 group-hover:rotate-45 transition-transform" />
            <span className="text-[11px] text-white/80">Roll Color:</span>
            <span 
              className="w-2.5 h-2.5 rounded-full inline-block border border-white/40 shadow-xs" 
              style={{ backgroundColor: activeColorVariant.hex }} 
            />
            <span className="font-bold text-white text-[11px] truncate max-w-[90px]">
              {activeColorVariant.name.split(' ')[0]}
            </span>
          </button>
        </div>
      </div>

      {/* ── Category Policy & Operational Clearance Footer ── */}
      <div className="p-5 pt-3 border-t border-white/10 bg-black/25 flex flex-col gap-3 relative z-10">
        <div className="grid grid-cols-2 gap-2 text-xs">
          <div className="bg-white/5 border border-white/10 rounded-lg p-2">
            <span className="text-white/50 text-[10px] uppercase font-mono block">Category Clearance</span>
            <span className="font-bold text-white text-xs">{config.displayName}</span>
          </div>
          <div className="bg-white/5 border border-white/10 rounded-lg p-2">
            <span className="text-white/50 text-[10px] uppercase font-mono block">Operating Window</span>
            <span className="font-bold text-emerald-300 text-xs flex items-center gap-1">
              <Clock className="w-3 h-3" />
              {operationalHoursLabel}
            </span>
          </div>
        </div>

        {/* ── 6-Factor Multi-Tier Security Verification Strip ── */}
        <div className="bg-black/35 border border-white/10 rounded-lg p-2.5 flex flex-col gap-1.5">
          <div className="flex items-center justify-between text-[10px] font-mono uppercase tracking-wider text-white/60">
            <span>Visual Identity Engine (Decoupled Security)</span>
            <span className="text-emerald-400 font-bold">WCAG {activeColorVariant.contrastRatio}:1 PASS</span>
          </div>
          <div className="grid grid-cols-3 gap-1.5 text-[10px] font-mono">
            <div className="bg-white/5 rounded px-2 py-1 flex items-center gap-1">
              <span className="text-white/40">Shape:</span>
              <span className="text-white font-bold truncate">{config.shapeLabel.split(' ')[0]}</span>
            </div>
            <div className="bg-white/5 rounded px-2 py-1 flex items-center gap-1 truncate">
              <span className="text-white/40">Color:</span>
              <span 
                className="w-2 h-2 rounded-full inline-block shrink-0" 
                style={{ backgroundColor: activeColorVariant.hex }} 
              />
              <span className="text-white font-bold truncate">{activeColorVariant.name.split(' ')[0]}</span>
            </div>
            <div className="bg-white/5 rounded px-2 py-1 flex items-center gap-1">
              <span className="text-white/40">Auth:</span>
              <span className="text-emerald-300 font-bold">HMAC Signature</span>
            </div>
          </div>
          <p className="text-[9px] text-white/50 font-mono italic leading-tight pt-0.5">
            *Shape designates profile tier. Color differentiates individual pass. Digital signature authenticates ingress.
          </p>
        </div>

        {/* Quick Action Buttons */}
        <div className="flex items-center gap-2 pt-1">
          {onScanInVerifier && (
            <Button
              size="sm"
              onClick={() => onScanInVerifier(currentToken)}
              className="flex-1 h-8 text-xs font-semibold gap-1.5 bg-white text-slate-900 hover:bg-white/90 shadow-md"
            >
              <ShieldCheck className="w-3.5 h-3.5 text-emerald-600" />
              <span>Test in Gate Scanner</span>
            </Button>
          )}

          <div className="flex items-center gap-1.5 ml-auto">
            <span className="text-[10px] text-white/50 font-mono hidden sm:inline">Cryptographic v1</span>
            <div className="flex items-center gap-1 bg-white/10 px-2 py-1 rounded text-[10px] text-white/80">
              <Apple className="w-3 h-3" />
              <Smartphone className="w-3 h-3" />
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
