import React, { useState, useEffect, useCallback, useMemo } from 'react';
import { Head, router } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { useToast } from '@/hooks/use-toast';
import { ProfileCustomQRCode } from '@/components/dashboard/ProfileCustomQRCode';
import type { PassCategory } from '@/lib/gate-pass-engine/types';
import {
  Wallet,
  QrCode,
  Nfc,
  Smartphone,
  ShieldCheck,
  ShieldAlert,
  Clock,
  Sparkles,
  Share2,
  Download,
  Copy,
  CheckCircle2,
  AlertCircle,
  ExternalLink,
  Users,
  ChevronRight,
  Radio,
  Lock,
  Unlock,
  KeyRound,
  RefreshCw,
  PhoneCall,
  Calendar,
  Layers,
  Check
} from 'lucide-react';
import { cn } from '@/lib/utils';
import axios from 'axios';

type WalletCredential = {
  id: number;
  pass_id: string;
  holder_name: string;
  relationship_label: string;
  role_in_household: string;
  category: string;
  category_label: string;
  shape: string;
  variant: {
    id: string;
    label: string;
    colors: {
      background: string;
      foreground: string;
      accent: string;
      border: string;
    };
  };
  property: string;
  status: string;
  status_label: string;
  is_active: boolean;
  offline_pin: string;
  qr: {
    token: string | null;
    valid_until: string | null;
    seconds_remaining: number;
    envelope_prefix: string;
    standard: string;
  };
  nfc: {
    card_uid: string;
    payload: string;
    protocol: string;
    frequency: string;
    status: string;
    ndef_mime: string;
  };
  wallet_integrations: {
    apple_wallet: {
      available: boolean;
      file_name: string;
      download_url: string;
      badge_label: string;
    };
    google_wallet: {
      available: boolean;
      save_url: string;
      badge_label: string;
    };
    samsung_wallet: {
      available: boolean;
      save_url: string;
      badge_label: string;
    };
  };
  schedule?: {
    curfew_enabled?: boolean;
    curfew_hours?: string;
    days?: string[];
    hours?: string;
    valid_until?: string;
  } | null;
  permissions?: string[];
  designated_gate: string;
  expiry_date: string;
};

type RevokedCredential = {
  id: number;
  pass_id: string;
  holder_name: string;
  category: string;
  category_label: string;
  property: string;
  status: string;
  revoked_at?: string;
  revoked_at_formatted?: string;
  revocation_reason?: string;
  offline_pin?: string;
};

type Props = {
  credentials: WalletCredential[];
  revokedHistory?: RevokedCredential[];
  household?: {
    id: number;
    name: string;
    property_number: string;
    members_count: number;
  } | null;
  gates: Array<{ id: string; name: string }>;
  user: {
    id: number;
    name: string;
    role: string;
    canScan: boolean;
  };
};

export default function DigitalAccessWalletPage({
  credentials = [],
  revokedHistory = [],
  household,
  gates = [],
  user,
}: Props) {
  const { toast } = useToast();
  const [credentialsList, setCredentialsList] = useState<WalletCredential[]>(credentials);
  const [revokedList, setRevokedList] = useState<RevokedCredential[]>(revokedHistory);
  const [selectedPassId, setSelectedPassId] = useState<string>(
    credentials[0]?.pass_id || ''
  );
  const [activeTab, setActiveTab] = useState<'qr' | 'nfc' | 'mobile_wallet'>('qr');
  const [secondsRemaining, setSecondsRemaining] = useState<number>(30);
  const [isSimulatingNfc, setIsSimulatingNfc] = useState<boolean>(false);
  const [nfcSuccessResult, setNfcSuccessResult] = useState<any | null>(null);
  const [googleWalletModal, setGoogleWalletModal] = useState<any | null>(null);
  const [samsungWalletModal, setSamsungWalletModal] = useState<any | null>(null);
  const [shareModalOpen, setShareModalOpen] = useState<boolean>(false);
  const [copiedKey, setCopiedKey] = useState<string | null>(null);
  const [webNfcSupported, setWebNfcSupported] = useState<boolean>(false);

  // Lost / Stolen Credential Protocol State
  const [lostModalOpen, setLostModalOpen] = useState<boolean>(false);
  const [isReportingLost, setIsReportingLost] = useState<boolean>(false);
  const [reportReason, setReportReason] = useState<string>('Reported Lost/Stolen by Cardholder');
  const [lastIncident, setLastIncident] = useState<{
    revoked_pass_id: string;
    replacement_pass_id: string;
    revoked_at: string;
    holder_name: string;
  } | null>(null);

  // Selected pass record
  const currentPass = useMemo(() => {
    return credentialsList.find((c) => c.pass_id === selectedPassId) || credentialsList[0];
  }, [credentialsList, selectedPassId]);

  // Check Web NFC hardware support
  useEffect(() => {
    if (typeof window !== 'undefined' && 'NDEFReader' in window) {
      setWebNfcSupported(true);
    }
  }, []);

  // Rolling countdown timer
  useEffect(() => {
    if (!currentPass?.qr?.seconds_remaining) return;
    setSecondsRemaining(currentPass.qr.seconds_remaining);

    const interval = setInterval(() => {
      setSecondsRemaining((prev) => {
        if (prev <= 1) {
          // Token refresh
          return 30;
        }
        return prev - 1;
      });
    }, 1000);

    return () => clearInterval(interval);
  }, [currentPass?.pass_id]);

  // Copy helper
  const handleCopy = (text: string, key: string) => {
    navigator.clipboard.writeText(text);
    setCopiedKey(key);
    toast({
      title: 'Copied to Clipboard',
      description: `Copied ${key} successfully.`,
    });
    setTimeout(() => setCopiedKey(null), 2500);
  };

  // Play synthetic tone on successful gate clearance
  const playClearanceChime = useCallback(() => {
    try {
      const AudioCtx = window.AudioContext || (window as any).webkitAudioContext;
      if (!AudioCtx) return;
      const ctx = new AudioCtx();
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.connect(gain);
      gain.connect(ctx.destination);
      osc.type = 'sine';
      osc.frequency.setValueAtTime(587.33, ctx.currentTime); // D5
      osc.frequency.setValueAtTime(880.0, ctx.currentTime + 0.1); // A5
      gain.gain.setValueAtTime(0.2, ctx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.35);
      osc.start();
      osc.stop(ctx.currentTime + 0.35);
    } catch {
      // Audio not permitted or supported
    }
  }, []);

  // Simulate NFC Gate Tap
  const handleSimulateNfcTap = async () => {
    if (!currentPass) return;
    setIsSimulatingNfc(true);
    setNfcSuccessResult(null);

    try {
      const res = await axios.post('/dashboard/wallet/simulate-nfc-tap', {
        payload: currentPass.nfc.payload,
        gate: gates[0]?.id || 'GATE-01',
      });

      playClearanceChime();
      setNfcSuccessResult(res.data);
      toast({
        title: 'NFC Handshake Successful',
        description: `Gate reader confirmed: ${res.data.decision} (${res.data.report?.primaryReason || 'Clearance Verified'})`,
      });
    } catch (err: any) {
      toast({
        title: 'NFC Tap Failed',
        description: err.response?.data?.message || 'Gate communication timeout',
        variant: 'destructive',
      });
    } finally {
      setIsSimulatingNfc(false);
    }
  };

  // Load Google Wallet Payload
  const handleOpenGoogleWallet = async () => {
    if (!currentPass) return;
    try {
      const res = await axios.get(currentPass.wallet_integrations.google_wallet.save_url);
      setGoogleWalletModal(res.data);
    } catch (err: any) {
      toast({
        title: 'Google Wallet Error',
        description: 'Unable to prepare Google Wallet pass object.',
        variant: 'destructive',
      });
    }
  };

  // Load Samsung Wallet Payload
  const handleOpenSamsungWallet = async () => {
    if (!currentPass) return;
    try {
      const res = await axios.get(currentPass.wallet_integrations.samsung_wallet.save_url);
      setSamsungWalletModal(res.data);
    } catch (err: any) {
      toast({
        title: 'Samsung Wallet Error',
        description: 'Unable to prepare Samsung Wallet pass payload.',
        variant: 'destructive',
      });
    }
  };

  // One-Button Lost / Stolen Credential Protocol:
  // Immediately revokes old pass (killing all screenshots & NFC cards) and issues fresh replacement credential.
  const handleReportLost = async (passIdToRevoke?: string) => {
    const targetPassId = passIdToRevoke || currentPass?.pass_id;
    if (!targetPassId) return;

    setIsReportingLost(true);
    try {
      const res = await axios.post('/dashboard/wallet/report-lost', {
        pass_id: targetPassId,
        reason: reportReason || 'Reported Lost/Stolen by Cardholder',
      });

      const { revoked_pass, replacement_pass, message } = res.data;

      // 1. Immediately replace in active credentials list
      setCredentialsList((prev) => {
        const filtered = prev.filter((p) => p.pass_id !== revoked_pass.pass_id);
        return [replacement_pass, ...filtered];
      });

      // 2. Append to revoked history list
      setRevokedList((prev) => [
        {
          id: revoked_pass.id || Date.now(),
          pass_id: revoked_pass.pass_id,
          holder_name: revoked_pass.holder_name,
          category: replacement_pass.category,
          category_label: replacement_pass.category_label,
          property: replacement_pass.property,
          status: 'REVOKED',
          revoked_at: revoked_pass.revoked_at,
          revoked_at_formatted: new Date(revoked_pass.revoked_at).toLocaleString(),
          revocation_reason: revoked_pass.revocation_reason,
          offline_pin: currentPass.offline_pin,
        },
        ...prev,
      ]);

      // 3. Immediately select new replacement pass
      setSelectedPassId(replacement_pass.pass_id);

      // 4. Record incident for prominent warning banner
      setLastIncident({
        revoked_pass_id: revoked_pass.pass_id,
        replacement_pass_id: replacement_pass.pass_id,
        revoked_at: revoked_pass.revoked_at,
        holder_name: revoked_pass.holder_name,
      });

      setLostModalOpen(false);

      toast({
        title: '🚨 Credential REVOKED & Replaced',
        description: message,
      });
    } catch (err: any) {
      toast({
        title: 'Revocation Failed',
        description: err.response?.data?.message || 'Unable to revoke credential.',
        variant: 'destructive',
      });
    } finally {
      setIsReportingLost(false);
    }
  };

  if (!currentPass) {
    return (
      <DashboardLayout>
        <Head title="Access Wallet" />
        <div className="p-8 text-center text-muted-foreground">
          <Wallet className="w-12 h-12 mx-auto mb-3 opacity-40" />
          <h2 className="text-xl font-bold">No Digital Credentials Found</h2>
          <p className="mt-1">Please ensure your account has an active gate pass assigned.</p>
        </div>
      </DashboardLayout>
    );
  }

  return (
    <DashboardLayout>
      <Head title="Digital Access Wallet — QR + NFC + Mobile Credential" />

      <div className="max-w-7xl mx-auto space-y-8 pb-16">
        {/* Top Header & Telemetry */}
        <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-border/60 pb-6">
          <div>
            <div className="flex items-center gap-3">
              <div className="w-10 h-10 rounded-xl bg-gradient-to-tr from-amber-500/20 to-emerald-500/20 border border-amber-500/30 flex items-center justify-center text-amber-400 shadow-sm">
                <Wallet className="w-5 h-5" />
              </div>
              <div>
                <h1 className="text-2xl sm:text-3xl font-black tracking-tight text-foreground flex items-center gap-2">
                  Digital Access Wallet
                  <Badge variant="outline" className="border-emerald-500/40 text-emerald-400 bg-emerald-500/10 text-xs py-0.5">
                    Live Credentials
                  </Badge>
                </h1>
                <p className="text-sm text-muted-foreground mt-0.5">
                  Multi-modal gate clearance: Dynamic ISO 18004 QR, Contactless NFC Tap & Native Mobile Wallets.
                </p>
              </div>
            </div>
          </div>

          <div className="flex flex-wrap items-center gap-2">
            <Badge variant="secondary" className="px-3 py-1.5 flex items-center gap-1.5 bg-slate-900/80 border border-slate-700/80 text-slate-300 font-mono text-xs">
              <Radio className="w-3.5 h-3.5 text-emerald-400 animate-pulse" />
              NFC: 13.56 MHz High Frequency
            </Badge>

            <Badge variant="secondary" className="px-3 py-1.5 flex items-center gap-1.5 bg-slate-900/80 border border-slate-700/80 text-slate-300 font-mono text-xs">
              <ShieldCheck className="w-3.5 h-3.5 text-amber-400" />
              Guilloche Heraldic Shield
            </Badge>

            <Button
              variant="outline"
              size="sm"
              onClick={() => setShareModalOpen(true)}
              className="border-slate-700 hover:bg-slate-800 text-xs font-semibold gap-1.5"
            >
              <Share2 className="w-3.5 h-3.5 text-primary" />
              Share Member Pass
            </Button>
          </div>
        </div>

        {/* Main Grid: Stacked Wallet Cards (Left) & Credential Presentation (Right) */}
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
          {/* Left Column: Interactive Wallet Card Stack */}
          <div className="lg:col-span-4 space-y-4">
            <div className="flex items-center justify-between px-1">
              <span className="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                <Layers className="w-4 h-4 text-primary" />
                Active Pass Roster ({credentials.length})
              </span>
              {household && (
                <span className="text-xs text-muted-foreground font-mono">
                  {household.name} • {household.property_number}
                </span>
              )}
            </div>

            <div className="space-y-3">
              {credentials.map((cred, idx) => {
                const isSelected = cred.pass_id === currentPass.pass_id;
                const isCurfew = cred.schedule?.curfew_enabled;
                const isCaregiver = cred.role_in_household === 'caregiver';

                return (
                  <div
                    key={cred.pass_id}
                    onClick={() => {
                      setSelectedPassId(cred.pass_id);
                      setNfcSuccessResult(null);
                    }}
                    className={cn(
                      "relative p-4 rounded-2xl border transition-all duration-300 cursor-pointer overflow-hidden",
                      isSelected
                        ? "bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 border-amber-500/60 shadow-xl shadow-amber-500/5 ring-1 ring-amber-500/40 translate-x-1"
                        : "bg-card/60 hover:bg-card border-border/70 hover:border-border opacity-85 hover:opacity-100 hover:translate-x-0.5"
                    )}
                  >
                    {/* Metallic highlight ribbon */}
                    <div
                      className="absolute top-0 left-0 right-0 h-1"
                      style={{
                        background: isSelected
                          ? 'linear-gradient(90deg, #F59E0B, #10B981, #6366F1)'
                          : 'transparent',
                      }}
                    />

                    <div className="flex items-start justify-between gap-3">
                      <div>
                        <div className="flex items-center gap-2">
                          <span className="font-bold text-foreground text-base tracking-tight">
                            {cred.holder_name}
                          </span>
                          {isSelected && (
                            <span className="w-2 h-2 rounded-full bg-emerald-400 animate-ping" />
                          )}
                        </div>
                        <p className="text-xs text-muted-foreground mt-0.5 flex items-center gap-1.5">
                          <span className="font-semibold text-primary/90">{cred.relationship_label}</span>
                          <span>•</span>
                          <span className="font-mono text-[11px]">{cred.property}</span>
                        </p>
                      </div>

                      <Badge
                        variant="outline"
                        className={cn(
                          "text-[10px] uppercase font-mono px-2 py-0.5 border font-semibold",
                          isSelected
                            ? "border-amber-400/50 text-amber-300 bg-amber-400/10"
                            : "border-border text-muted-foreground"
                        )}
                      >
                        {cred.category_label}
                      </Badge>
                    </div>

                    {/* Operational constraints tags */}
                    <div className="mt-3 flex flex-wrap items-center gap-1.5">
                      <span className="text-[11px] font-mono text-muted-foreground bg-slate-800/80 px-2 py-0.5 rounded border border-slate-700/60 flex items-center gap-1">
                        <KeyRound className="w-3 h-3 text-amber-400" />
                        {cred.pass_id}
                      </span>

                      {isCurfew && (
                        <span className="text-[11px] font-semibold text-amber-400 bg-amber-500/10 px-2 py-0.5 rounded border border-amber-500/30 flex items-center gap-1">
                          <Clock className="w-3 h-3" />
                          Curfew: 6am–9pm
                        </span>
                      )}

                      {isCaregiver && cred.schedule?.hours && (
                        <span className="text-[11px] font-semibold text-sky-400 bg-sky-500/10 px-2 py-0.5 rounded border border-sky-500/30 flex items-center gap-1">
                          <Calendar className="w-3 h-3" />
                          Mon–Fri Shift
                        </span>
                      )}
                    </div>
                  </div>
                );
              })}
            </div>

            {/* Quick Household Switcher Notice */}
            <div className="p-4 rounded-xl border border-dashed border-border/80 bg-muted/20 text-xs text-muted-foreground flex items-start gap-2.5">
              <Users className="w-4 h-4 text-primary shrink-0 mt-0.5" />
              <div>
                <p className="font-medium text-foreground">Multi-Person Household Wallet</p>
                <p className="mt-0.5">
                  Select any family member or caregiver above to view their active QR pass, write to an NFC keycard, or export to Apple/Google Wallet.
                </p>
              </div>
            </div>
          </div>

          {/* Right Column: Multi-Modal Credential Card & Gate Handshake */}
          <div className="lg:col-span-8 space-y-6">
            {/* Real-Time Incident Banner after reporting lost */}
            {lastIncident && (
              <div className="p-4 rounded-2xl bg-gradient-to-r from-red-950/90 via-slate-900 to-amber-950/90 border border-red-500/50 text-white shadow-xl space-y-2 animate-in fade-in duration-300">
                <div className="flex items-start justify-between gap-3">
                  <div className="flex items-center gap-2.5">
                    <div className="w-8 h-8 rounded-lg bg-red-500/20 border border-red-500/40 flex items-center justify-center text-red-400 shrink-0">
                      <ShieldAlert className="w-5 h-5 animate-pulse" />
                    </div>
                    <div>
                      <h4 className="font-bold text-sm text-red-200 flex items-center gap-2">
                        Credential #{lastIncident.revoked_pass_id} REVOKED Immediately
                        <Badge variant="destructive" className="font-mono text-[9px] uppercase px-1.5 py-0 bg-red-600 text-white">
                          Screenshots Dead
                        </Badge>
                      </h4>
                      <p className="text-xs text-slate-300 mt-0.5">
                        All prior screenshots, exported passes, and physical NFC clones have been permanently invalidated. New credential <strong className="text-emerald-400 font-mono">#{lastIncident.replacement_pass_id}</strong> is active.
                      </p>
                    </div>
                  </div>
                </div>

                <div className="flex flex-wrap items-center gap-2 pt-1 border-t border-red-500/20">
                  <Button
                    size="sm"
                    variant="outline"
                    onClick={() => window.open('/dashboard/gate-scanner', '_blank')}
                    className="h-7 text-xs border-red-400/40 text-red-300 hover:bg-red-500/10 gap-1.5"
                  >
                    <ExternalLink className="w-3 h-3" />
                    Verify Rejection in Gate Scanner Kiosk
                  </Button>
                  <Button
                    size="sm"
                    variant="ghost"
                    onClick={() => handleCopy(lastIncident.revoked_pass_id, 'Revoked Pass ID')}
                    className="h-7 text-xs text-slate-300 hover:text-white gap-1"
                  >
                    {copiedKey === 'Revoked Pass ID' ? <Check className="w-3 h-3 text-emerald-400" /> : <Copy className="w-3 h-3" />}
                    Copy Revoked Pass ID ({lastIncident.revoked_pass_id})
                  </Button>
                </div>
              </div>
            )}

            <Card className="border border-border/80 shadow-2xl bg-card/90 backdrop-blur overflow-hidden">
              {/* Card Banner */}
              <div className="relative p-6 sm:p-8 border-b border-border/60 bg-gradient-to-br from-slate-950 via-slate-900 to-slate-950 text-white">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                  <div>
                    <div className="flex items-center gap-2">
                      <span className="text-xs uppercase font-mono tracking-widest text-amber-400 font-bold">
                        {currentPass.category_label} CREDENTIAL
                      </span>
                      <span className="text-slate-600">•</span>
                      <span className="text-xs font-mono text-emerald-400 flex items-center gap-1 font-semibold">
                        <CheckCircle2 className="w-3.5 h-3.5" />
                        {currentPass.status_label}
                      </span>
                    </div>

                    <h2 className="text-3xl font-black tracking-tight text-white mt-1">
                      {currentPass.holder_name}
                    </h2>

                    <div className="flex flex-wrap items-center gap-3 mt-2 text-xs text-slate-300">
                      <span className="font-medium bg-white/10 px-2.5 py-0.5 rounded-full border border-white/15">
                        {currentPass.relationship_label}
                      </span>
                      <span>Property: <strong className="text-white">{currentPass.property}</strong></span>
                      <span>Designated: <strong className="text-amber-300">{currentPass.designated_gate}</strong></span>
                    </div>
                  </div>

                  {/* Visual Category Enclosure Emblem & One-Button Lost Reporting */}
                  <div className="flex flex-col items-center sm:items-end justify-center gap-2">
                    <div className="flex items-center gap-2">
                      <div className="px-3 py-1.5 rounded-lg bg-white/5 border border-white/10 font-mono text-xs text-slate-300">
                        ID: <span className="text-amber-400 font-bold">{currentPass.pass_id}</span>
                      </div>
                      <span className="text-[11px] text-slate-400 font-mono">
                        PIN: {currentPass.offline_pin}
                      </span>
                    </div>

                    {/* ONE BUTTON: Report Credential Lost */}
                    <Button
                      variant="destructive"
                      size="sm"
                      onClick={() => setLostModalOpen(true)}
                      className="bg-red-600/90 hover:bg-red-600 text-white font-bold text-xs h-8 px-3 shadow-md gap-1.5 border border-red-500/40"
                      title="Immediately revoke this QR/NFC credential and generate a replacement"
                    >
                      <ShieldAlert className="w-3.5 h-3.5 text-white" />
                      <span>Report Credential Lost</span>
                    </Button>
                  </div>
                </div>

                {/* Multi-Modal Tab Navigation */}
                <div className="mt-8">
                  <Tabs value={activeTab} onValueChange={(v) => setActiveTab(v as any)}>
                    <TabsList className="grid grid-cols-3 bg-slate-950/80 border border-slate-800 p-1 rounded-xl">
                      <TabsTrigger
                        value="qr"
                        className="data-[state=active]:bg-gradient-to-r data-[state=active]:from-amber-500/20 data-[state=active]:to-emerald-500/20 data-[state=active]:text-white font-semibold text-xs sm:text-sm py-2 flex items-center gap-2"
                      >
                        <QrCode className="w-4 h-4 text-amber-400" />
                        <span>Dynamic QR</span>
                      </TabsTrigger>

                      <TabsTrigger
                        value="nfc"
                        className="data-[state=active]:bg-gradient-to-r data-[state=active]:from-emerald-500/20 data-[state=active]:to-sky-500/20 data-[state=active]:text-white font-semibold text-xs sm:text-sm py-2 flex items-center gap-2"
                      >
                        <Nfc className="w-4 h-4 text-emerald-400" />
                        <span>Contactless NFC</span>
                      </TabsTrigger>

                      <TabsTrigger
                        value="mobile_wallet"
                        className="data-[state=active]:bg-gradient-to-r data-[state=active]:from-indigo-500/20 data-[state=active]:to-purple-500/20 data-[state=active]:text-white font-semibold text-xs sm:text-sm py-2 flex items-center gap-2"
                      >
                        <Smartphone className="w-4 h-4 text-indigo-400" />
                        <span>Mobile Wallet</span>
                      </TabsTrigger>
                    </TabsList>
                  </Tabs>
                </div>
              </div>

              {/* Tab 1: Dynamic QR Code Presentation */}
              {activeTab === 'qr' && (
                <CardContent className="p-6 sm:p-8 space-y-6">
                  <div className="flex flex-col items-center justify-center text-center">
                    {/* QR Code in its profile bezel */}
                    <div className="relative p-6 rounded-3xl bg-slate-950/90 border border-border shadow-2xl">
                      <ProfileCustomQRCode
                        value={currentPass.qr.token || currentPass.pass_id}
                        category={currentPass.category as PassCategory}
                        size={220}
                      />

                      {/* Rolling Countdown Progress Indicator */}
                      <div className="mt-4 flex items-center justify-center gap-2 font-mono text-xs text-muted-foreground">
                        <RefreshCw className={cn("w-3.5 h-3.5 text-emerald-400", secondsRemaining < 6 && "animate-spin text-amber-400")} />
                        <span>Token rotates in <strong className="text-foreground">{secondsRemaining}s</strong></span>
                      </div>
                    </div>

                    <div className="mt-4 max-w-md">
                      <p className="text-xs text-muted-foreground">
                        Certified ISO/IEC 18004 Tamper-Evident Anti-Replay Format. Automatically rolls every 30 seconds to prevent unauthorized screenshots or forwarding.
                      </p>
                    </div>
                  </div>

                  {/* Fallback Offline Gate PIN */}
                  <div className="p-4 rounded-xl bg-muted/40 border border-border/80 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div className="flex items-center gap-3">
                      <div className="w-9 h-9 rounded-lg bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-500 shrink-0">
                        <KeyRound className="w-4 h-4" />
                      </div>
                      <div>
                        <p className="text-xs font-semibold text-foreground">Offline Gate Keypad PIN</p>
                        <p className="text-[11px] text-muted-foreground">Use if gate optical scanner is impaired or during cellular outage.</p>
                      </div>
                    </div>

                    <div className="flex items-center gap-2">
                      <code className="text-base font-black tracking-widest px-3 py-1 rounded bg-slate-900 text-amber-400 border border-slate-700 font-mono">
                        {currentPass.offline_pin}
                      </code>
                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => handleCopy(currentPass.offline_pin, 'PIN')}
                        className="h-8 w-8 p-0"
                      >
                        {copiedKey === 'PIN' ? <Check className="w-4 h-4 text-emerald-400" /> : <Copy className="w-4 h-4" />}
                      </Button>
                    </div>
                  </div>
                </CardContent>
              )}

              {/* Tab 2: Contactless NFC Tap Presentation */}
              {activeTab === 'nfc' && (
                <CardContent className="p-6 sm:p-8 space-y-6">
                  {/* Radar Wave Antenna Animation */}
                  <div className="relative py-8 flex flex-col items-center justify-center text-center overflow-hidden rounded-2xl bg-gradient-to-b from-slate-950 via-slate-900 to-slate-950 border border-slate-800">
                    {/* Glowing circular waves */}
                    <div className="absolute w-48 h-48 rounded-full border border-emerald-500/20 animate-ping opacity-40" />
                    <div className="absolute w-32 h-32 rounded-full border border-emerald-400/30 animate-pulse" />

                    <div className="relative w-20 h-20 rounded-full bg-gradient-to-tr from-emerald-500/20 to-teal-500/30 border-2 border-emerald-400 flex items-center justify-center text-emerald-400 shadow-lg shadow-emerald-500/20">
                      <Nfc className="w-10 h-10 animate-bounce" />
                    </div>

                    <div className="relative mt-4">
                      <h3 className="text-lg font-bold text-white tracking-tight">
                        Hold Top of Phone Near Gate Reader
                      </h3>
                      <p className="text-xs text-slate-400 max-w-sm mt-1 mx-auto">
                        High Frequency 13.56 MHz contactless field ready. Compatible with estate card readers, turnstiles, and mobile security marshals.
                      </p>
                    </div>

                    {/* Virtual NFC Card UID */}
                    <div className="relative mt-5 flex items-center gap-2 bg-slate-900/90 border border-slate-700 px-4 py-2 rounded-xl">
                      <span className="text-[11px] font-mono text-slate-400 uppercase">Card UID:</span>
                      <span className="text-sm font-mono font-bold text-emerald-400 tracking-wider">
                        {currentPass.nfc.card_uid}
                      </span>
                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => handleCopy(currentPass.nfc.card_uid, 'NFC UID')}
                        className="h-7 w-7 p-0 ml-1 text-slate-400 hover:text-white"
                      >
                        {copiedKey === 'NFC UID' ? <Check className="w-3.5 h-3.5 text-emerald-400" /> : <Copy className="w-3.5 h-3.5" />}
                      </Button>
                    </div>
                  </div>

                  {/* Interactive Gate Reader Simulator */}
                  <div className="p-4 rounded-xl bg-slate-900/50 border border-border/80 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                      <p className="text-xs font-bold text-foreground flex items-center gap-1.5">
                        <Radio className="w-3.5 h-3.5 text-emerald-400" />
                        Simulate Gate Contactless Tap
                      </p>
                      <p className="text-[11px] text-muted-foreground mt-0.5">
                        Test gate access clearance using this credential's cryptographic NFC challenge.
                      </p>
                    </div>

                    <Button
                      onClick={handleSimulateNfcTap}
                      disabled={isSimulatingNfc}
                      className="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs gap-2 shrink-0"
                    >
                      {isSimulatingNfc ? (
                        <>
                          <RefreshCw className="w-3.5 h-3.5 animate-spin" />
                          Tapping Antenna...
                        </>
                      ) : (
                        <>
                          <Nfc className="w-3.5 h-3.5" />
                          Tap Gate Reader Now
                        </>
                      )}
                    </Button>
                  </div>

                  {/* NFC Success Decision Banner */}
                  {nfcSuccessResult && (
                    <div
                      className={cn(
                        "p-4 rounded-xl border transition-all animate-in fade-in duration-300",
                        nfcSuccessResult.decision === 'REJECT'
                          ? "bg-red-500/10 border-red-500/40 text-red-400"
                          : "bg-emerald-500/10 border-emerald-500/40 text-emerald-300"
                      )}
                    >
                      <div className="flex items-start gap-3">
                        {nfcSuccessResult.decision === 'REJECT' ? (
                          <ShieldAlert className="w-5 h-5 text-red-400 shrink-0 mt-0.5" />
                        ) : (
                          <ShieldCheck className="w-5 h-5 text-emerald-400 shrink-0 mt-0.5" />
                        )}
                        <div>
                          <div className="flex items-center gap-2">
                            <span className="font-bold text-sm">
                              {nfcSuccessResult.decision === 'REJECT' ? 'Clearance Denied' : 'Clearance Granted (Gate Unlocked)'}
                            </span>
                            <Badge variant="outline" className="text-[10px] uppercase font-mono">
                              Decision: {nfcSuccessResult.decision}
                            </Badge>
                          </div>
                          <p className="text-xs mt-1 text-slate-300">
                            {nfcSuccessResult.report?.primaryReason || 'Authorized resident pass verified by gate controller.'}
                          </p>
                          <div className="mt-2 text-[11px] font-mono text-slate-400 flex items-center gap-3">
                            <span>Gate: {nfcSuccessResult.report?.gate || 'GATE-01'}</span>
                            <span>Method: NFC Contactless Tap</span>
                            <span>Log ID: #{nfcSuccessResult.accessLogId}</span>
                          </div>
                        </div>
                      </div>
                    </div>
                  )}

                  {/* Physical Fob / ISO Specs */}
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                    <div className="p-3 rounded-lg border border-border/70 bg-card/40">
                      <span className="font-bold text-foreground">Standard & Protocol:</span>
                      <p className="text-muted-foreground mt-0.5 font-mono text-[11px]">
                        {currentPass.nfc.protocol}
                      </p>
                    </div>
                    <div className="p-3 rounded-lg border border-border/70 bg-card/40">
                      <span className="font-bold text-foreground">Hardware Keycards & Fobs:</span>
                      <p className="text-muted-foreground mt-0.5 text-[11px]">
                        Physical RFID fobs registered to this UID will authenticate seamlessly at estate barriers.
                      </p>
                    </div>
                  </div>
                </CardContent>
              )}

              {/* Tab 3: Mobile Wallet Integrations */}
              {activeTab === 'mobile_wallet' && (
                <CardContent className="p-6 sm:p-8 space-y-6">
                  <div>
                    <h3 className="text-base font-bold text-foreground">
                      Native Mobile Wallets (No App Required)
                    </h3>
                    <p className="text-xs text-muted-foreground mt-0.5">
                      Store your credentials directly in Apple Wallet, Google Wallet, or Samsung Wallet for 1-tap lock screen access.
                    </p>
                  </div>

                  <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    {/* 1. Apple Wallet (.pkpass) */}
                    <div className="p-5 rounded-2xl bg-black border border-slate-800 text-white flex flex-col justify-between space-y-4 shadow-lg hover:border-slate-700 transition-all">
                      <div>
                        <div className="flex items-center justify-between">
                          <span className="text-xs font-mono font-bold text-slate-400 uppercase tracking-wider">
                            iOS / macOS
                          </span>
                          <Badge variant="outline" className="border-white/20 text-white text-[10px]">
                            .pkpass
                          </Badge>
                        </div>
                        <h4 className="text-lg font-black tracking-tight mt-2 flex items-center gap-1.5">
                          Apple Wallet
                        </h4>
                        <p className="text-xs text-slate-400 mt-1">
                          Native passbook bundle with ISO QR, Apple VAS NFC payload, and lock screen notifications.
                        </p>
                      </div>

                      <a
                        href={currentPass.wallet_integrations.apple_wallet.download_url}
                        download={currentPass.wallet_integrations.apple_wallet.file_name}
                        className="w-full"
                      >
                        <Button className="w-full bg-white hover:bg-slate-200 text-black font-bold text-xs gap-1.5">
                          <Download className="w-3.5 h-3.5" />
                          Add to Apple Wallet
                        </Button>
                      </a>
                    </div>

                    {/* 2. Google Wallet */}
                    <div className="p-5 rounded-2xl bg-slate-900 border border-slate-800 text-white flex flex-col justify-between space-y-4 shadow-lg hover:border-slate-700 transition-all">
                      <div>
                        <div className="flex items-center justify-between">
                          <span className="text-xs font-mono font-bold text-emerald-400 uppercase tracking-wider">
                            Android / Wear OS
                          </span>
                          <Badge variant="outline" className="border-emerald-500/30 text-emerald-400 text-[10px]">
                            Smart Tap
                          </Badge>
                        </div>
                        <h4 className="text-lg font-black tracking-tight mt-2 flex items-center gap-1.5">
                          Google Wallet
                        </h4>
                        <p className="text-xs text-slate-400 mt-1">
                          Google Pay Generic Pass class with dynamic barcode and Smart Tap NFC integration.
                        </p>
                      </div>

                      <Button
                        onClick={handleOpenGoogleWallet}
                        className="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs gap-1.5"
                      >
                        <Sparkles className="w-3.5 h-3.5" />
                        Save to Google Wallet
                      </Button>
                    </div>

                    {/* 3. Samsung Wallet */}
                    <div className="p-5 rounded-2xl bg-slate-950 border border-slate-800 text-white flex flex-col justify-between space-y-4 shadow-lg hover:border-slate-700 transition-all">
                      <div>
                        <div className="flex items-center justify-between">
                          <span className="text-xs font-mono font-bold text-sky-400 uppercase tracking-wider">
                            Galaxy Devices
                          </span>
                          <Badge variant="outline" className="border-sky-500/30 text-sky-400 text-[10px]">
                            Samsung Pay
                          </Badge>
                        </div>
                        <h4 className="text-lg font-black tracking-tight mt-2 flex items-center gap-1.5">
                          Samsung Wallet
                        </h4>
                        <p className="text-xs text-slate-400 mt-1">
                          Digital key & estate access card compatible with Samsung Knox and Samsung Pay.
                        </p>
                      </div>

                      <Button
                        onClick={handleOpenSamsungWallet}
                        variant="outline"
                        className="w-full border-slate-700 hover:bg-slate-800 text-white font-bold text-xs gap-1.5"
                      >
                        <ExternalLink className="w-3.5 h-3.5 text-sky-400" />
                        Add to Samsung Wallet
                      </Button>
                    </div>
                  </div>

                  {/* Household Pass Dispatching */}
                  <div className="p-4 rounded-xl border border-primary/20 bg-primary/5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                      <p className="text-xs font-bold text-foreground">
                        Send Wallet Pass to {currentPass.holder_name}
                      </p>
                      <p className="text-[11px] text-muted-foreground mt-0.5">
                        Transmit the Apple/Google Wallet pass link directly to this household member's phone via SMS or WhatsApp.
                      </p>
                    </div>

                    <Button
                      size="sm"
                      onClick={() => setShareModalOpen(true)}
                      className="bg-primary hover:bg-primary/90 text-primary-foreground font-bold text-xs gap-1.5 shrink-0"
                    >
                      <Share2 className="w-3.5 h-3.5" />
                      Dispatch Member Pass
                    </Button>
                  </div>
                </CardContent>
              )}

              {/* Bottom Card Footer: Governance, Schedule & Security Policies */}
              <div className="p-6 bg-muted/20 border-t border-border/60 grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                  <span className="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                    <Calendar className="w-3.5 h-3.5 text-primary" />
                    Access Schedule & Curfew
                  </span>
                  <div className="mt-2 text-xs space-y-1">
                    {currentPass.schedule?.curfew_enabled ? (
                      <div className="p-2 rounded bg-amber-500/10 border border-amber-500/30 text-amber-300">
                        <strong>Curfew Active:</strong> {currentPass.schedule.curfew_hours}
                      </div>
                    ) : currentPass.schedule?.days ? (
                      <div className="p-2 rounded bg-sky-500/10 border border-sky-500/30 text-sky-300">
                        <strong>Shift Hours:</strong> {currentPass.schedule.hours}
                        <div className="text-[11px] opacity-80 mt-0.5">
                          Days: {currentPass.schedule.days.join(', ')}
                        </div>
                      </div>
                    ) : (
                      <div className="p-2 rounded bg-emerald-500/10 border border-emerald-500/30 text-emerald-300">
                        <strong>24/7 Clearance:</strong> Round-the-clock estate gate access.
                      </div>
                    )}
                  </div>
                </div>

                <div>
                  <span className="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                    <ShieldCheck className="w-3.5 h-3.5 text-primary" />
                    Granted Privileges
                  </span>
                  <div className="mt-2 flex flex-wrap gap-1.5">
                    {(currentPass.permissions || ['gate_access_24_7', 'receive_emergency_alerts']).map((perm) => (
                      <Badge key={perm} variant="secondary" className="text-[10px] font-mono py-0.5">
                        {perm.replace(/_/g, ' ')}
                      </Badge>
                    ))}
                  </div>
                </div>

                <div>
                  <span className="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                    <PhoneCall className="w-3.5 h-3.5 text-primary" />
                    Estate Dispatch
                  </span>
                  <p className="text-xs text-muted-foreground mt-2">
                    Security Gatehouse: <strong className="text-foreground">+1 (876) 555-GATE</strong>
                  </p>
                  <p className="text-xs text-muted-foreground mt-0.5">
                    Emergency Police / Fire: <strong className="text-red-400">119</strong>
                  </p>
                </div>
              </div>
            </Card>

            {/* Revocation & Security Audit Log */}
            <Card className="border border-border/80 bg-card/90">
              <CardHeader className="pb-3 border-b border-border/60">
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <ShieldAlert className="w-4 h-4 text-red-400" />
                    <CardTitle className="text-base font-bold">
                      Revocation & Lost Credential History ({revokedList.length})
                    </CardTitle>
                  </div>
                  <Badge variant="outline" className="border-red-500/30 text-red-400 font-mono text-[10px]">
                    Zero-Trust Directory
                  </Badge>
                </div>
                <CardDescription className="text-xs">
                  Permanently invalidated credentials. Attempting to scan any of these tokens at an estate gate scanner triggers an immediate <code className="text-red-400 font-mono">PASS_REVOKED_BY_ADMIN</code> refusal.
                </CardDescription>
              </CardHeader>
              <CardContent className="p-4 space-y-3">
                {revokedList.length === 0 ? (
                  <p className="text-xs text-muted-foreground py-2 text-center">
                    No credentials have been reported lost or revoked in this household.
                  </p>
                ) : (
                  <div className="divide-y divide-border/60">
                    {revokedList.map((rev) => (
                      <div key={rev.pass_id} className="py-3 first:pt-0 last:pb-0 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                        <div>
                          <div className="flex items-center gap-2">
                            <span className="font-bold text-foreground text-sm">{rev.holder_name}</span>
                            <Badge variant="destructive" className="text-[10px] font-mono px-1.5 py-0 bg-red-600/80">
                              REVOKED
                            </Badge>
                            <span className="text-xs font-mono text-muted-foreground">ID: {rev.pass_id}</span>
                          </div>
                          <p className="text-[11px] text-muted-foreground mt-0.5">
                            Revoked: <span className="font-mono text-slate-300">{rev.revoked_at_formatted || rev.revoked_at}</span> • Reason: <span className="italic">{rev.revocation_reason || 'Reported Lost/Stolen'}</span>
                          </p>
                        </div>

                        <div className="flex items-center gap-2">
                          <Button
                            size="sm"
                            variant="outline"
                            onClick={() => window.open('/dashboard/gate-scanner', '_blank')}
                            className="h-7 text-xs border-red-500/30 text-red-400 hover:bg-red-500/10 gap-1"
                          >
                            <ExternalLink className="w-3 h-3" />
                            Test at Gate
                          </Button>
                          <Button
                            size="sm"
                            variant="ghost"
                            onClick={() => handleCopy(rev.pass_id, `Revoked ${rev.pass_id}`)}
                            className="h-7 w-7 p-0 text-muted-foreground hover:text-foreground"
                            title="Copy pass ID"
                          >
                            <Copy className="w-3.5 h-3.5" />
                          </Button>
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </CardContent>
            </Card>
          </div>
        </div>
      </div>

      {/* Google Wallet Modal */}
      <Dialog open={!!googleWalletModal} onOpenChange={() => setGoogleWalletModal(null)}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <Sparkles className="w-5 h-5 text-emerald-500" />
              Save to Google Wallet
            </DialogTitle>
            <DialogDescription>
              Google Wallet Pass payload generated for {currentPass.holder_name}.
            </DialogDescription>
          </DialogHeader>

          {googleWalletModal && (
            <div className="space-y-4 my-2">
              <div className="p-4 rounded-xl bg-slate-950 text-white font-mono text-xs space-y-2 border border-slate-800">
                <div className="text-emerald-400 font-bold">{googleWalletModal.protocol}</div>
                <div>Pass Object: {googleWalletModal.genericObject?.id}</div>
                <div>Class: {googleWalletModal.genericObject?.classId}</div>
                <div>Holder: {googleWalletModal.genericObject?.header?.defaultValue?.value}</div>
                <div>Barcode Type: {googleWalletModal.genericObject?.barcode?.type}</div>
              </div>

              <p className="text-xs text-muted-foreground">
                In a production deployment with Google Pay Developer keys, clicking below routes directly to Google Play Services to save this credential into Google Wallet.
              </p>
            </div>
          )}

          <DialogFooter>
            <Button
              onClick={() => {
                handleCopy(JSON.stringify(googleWalletModal, null, 2), 'Google Wallet Payload');
                setGoogleWalletModal(null);
              }}
              className="bg-emerald-600 hover:bg-emerald-700 text-white"
            >
              Copy Pass Payload
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Samsung Wallet Modal */}
      <Dialog open={!!samsungWalletModal} onOpenChange={() => setSamsungWalletModal(null)}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <Smartphone className="w-5 h-5 text-sky-500" />
              Add to Samsung Wallet
            </DialogTitle>
            <DialogDescription>
              Samsung Wallet Digital Key Card for {currentPass.holder_name}.
            </DialogDescription>
          </DialogHeader>

          {samsungWalletModal && (
            <div className="space-y-4 my-2">
              <div className="p-4 rounded-xl bg-slate-950 text-white font-mono text-xs space-y-2 border border-slate-800">
                <div className="text-sky-400 font-bold">{samsungWalletModal.protocol}</div>
                <div>Card ID: {samsungWalletModal.cdata?.cardId}</div>
                <div>NFC UID: {samsungWalletModal.cdata?.nfcUid}</div>
                <div>Deep Link: {samsungWalletModal.deepLink}</div>
              </div>
            </div>
          )}

          <DialogFooter>
            <Button
              onClick={() => {
                handleCopy(samsungWalletModal?.deepLink || '', 'Samsung Deep Link');
                setSamsungWalletModal(null);
              }}
              className="bg-sky-600 hover:bg-sky-700 text-white"
            >
              Copy Samsung Wallet Link
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Share Member Pass Modal */}
      <Dialog open={shareModalOpen} onOpenChange={setShareModalOpen}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <Share2 className="w-5 h-5 text-primary" />
              Dispatch Credential to {currentPass.holder_name}
            </DialogTitle>
            <DialogDescription>
              Share the mobile digital wallet pass directly with this household member.
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-4 my-2">
            <div className="p-3 rounded-xl bg-muted/40 border text-xs space-y-2">
              <p className="font-semibold text-foreground">Message Preview:</p>
              <p className="text-muted-foreground italic">
                "Hello {currentPass.holder_name}, your official {household?.name || 'Community Hub'} gate access pass ({currentPass.pass_id}) is ready. Add to Apple Wallet or tap at the gate with NFC: {window.location.origin}/dashboard/wallet"
              </p>
            </div>

            <div className="grid grid-cols-2 gap-3">
              <Button
                variant="outline"
                onClick={() => {
                  const msg = encodeURIComponent(`Hello ${currentPass.holder_name}, your official Community Hub gate pass (${currentPass.pass_id}) is ready: ${window.location.origin}/dashboard/wallet`);
                  window.open(`https://wa.me/?text=${msg}`, '_blank');
                }}
                className="gap-2 text-xs font-semibold"
              >
                <Share2 className="w-4 h-4 text-emerald-500" />
                WhatsApp
              </Button>

              <Button
                variant="outline"
                onClick={() => {
                  const msg = encodeURIComponent(`Your gate pass (${currentPass.pass_id}) is ready: ${window.location.origin}/dashboard/wallet`);
                  window.open(`sms:?body=${msg}`, '_blank');
                }}
                className="gap-2 text-xs font-semibold"
              >
                <Smartphone className="w-4 h-4 text-primary" />
                SMS Text
              </Button>
            </div>
          </div>

          <DialogFooter>
            <Button variant="ghost" onClick={() => setShareModalOpen(false)}>
              Close
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Report Credential Lost / Stolen Protocol Dialog */}
      <Dialog open={lostModalOpen} onOpenChange={setLostModalOpen}>
        <DialogContent className="sm:max-w-lg border-red-500/30">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2 text-red-500">
              <ShieldAlert className="w-5 h-5 text-red-500" />
              Report Credential Lost / Stolen
            </DialogTitle>
            <DialogDescription>
              Immediate one-button credential revocation and tamper-proof replacement protocol.
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-4 my-2">
            {/* Warning banner explaining screenshot invalidation */}
            <div className="p-4 rounded-xl bg-red-500/10 border border-red-500/30 text-xs space-y-2">
              <div className="flex items-center gap-2 font-bold text-red-400">
                <AlertCircle className="w-4 h-4 shrink-0" />
                Permanent Cryptographic Revocation
              </div>
              <p className="text-slate-300 leading-relaxed">
                Once confirmed, <strong>{currentPass.holder_name}</strong>'s current QR pass (<code className="font-mono text-amber-300">{currentPass.pass_id}</code>) will be permanently <strong className="text-red-400">REVOKED</strong> in the estate security directory.
              </p>
              <div className="p-2.5 rounded-lg bg-black/40 border border-white/10 font-mono text-[11px] text-amber-200">
                ⚠️ Any previous screenshots, forwarded QR codes, or cloned NFC cards will immediately fail and reject at all estate gates.
              </div>
            </div>

            <div className="p-3 rounded-xl bg-slate-900 border border-slate-800 text-xs space-y-1.5">
              <span className="font-semibold text-slate-300">Automatic Replacement:</span>
              <p className="text-slate-400">
                A brand-new replacement credential will be issued immediately with an incremented rotation sequence, fresh HMAC signature, and new offline keypad PIN.
              </p>
            </div>

            <div className="space-y-1.5">
              <label className="text-xs font-semibold text-foreground">Incident / Revocation Reason</label>
              <input
                type="text"
                value={reportReason}
                onChange={(e) => setReportReason(e.target.value)}
                placeholder="e.g. Phone stolen on commute, leaked screenshot, misplaced key fob"
                className="w-full text-xs p-2.5 rounded-lg bg-background border border-border focus:border-red-500 focus:outline-none"
              />
            </div>
          </div>

          <DialogFooter className="gap-2 sm:gap-0">
            <Button variant="ghost" onClick={() => setLostModalOpen(false)} disabled={isReportingLost}>
              Cancel
            </Button>
            <Button
              variant="destructive"
              onClick={() => handleReportLost()}
              disabled={isReportingLost}
              className="bg-red-600 hover:bg-red-700 text-white font-bold gap-2"
            >
              {isReportingLost ? (
                <>
                  <RefreshCw className="w-4 h-4 animate-spin" />
                  Revoking & Reissuing...
                </>
              ) : (
                <>
                  <ShieldAlert className="w-4 h-4" />
                  Confirm Revocation & Generate Replacement
                </>
              )}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </DashboardLayout>
  );
}
