import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import {
  ShieldAlert,
  ShieldCheck,
  AlertTriangle,
  Lock,
  Unlock,
  Clock,
  Calendar,
  FileText,
  FileCheck,
  CheckCircle2,
  XCircle,
  Search,
  RefreshCw,
  UserCheck,
  Building,
  User,
  ArrowRight,
  ChevronRight,
  ExternalLink,
  Shield,
  Layers,
  History,
  Activity,
  Filter,
  Eye,
  PlusCircle,
  FileClock,
  Sparkles,
  Info
} from 'lucide-react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle, CardFooter } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Input } from '@/components/ui/input';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

interface RiskIncident {
  id: number;
  pass_id: string | null;
  user_id: number | null;
  gate: string;
  flag_type: string;
  risk_level: 'CRITICAL' | 'HIGH' | 'MEDIUM' | 'LOW';
  title: string;
  description: string;
  evidence: Record<string, any> | null;
  status: 'NEW' | 'REVIEWED' | 'RESOLVED' | 'LOCKED_DOWN';
  resolution_notes: string | null;
  occurred_at: string;
  created_at: string;
  user?: { name: string; email: string };
  resolver?: { name: string };
}

interface ApprovalRequest {
  id: number;
  request_number: string;
  category: string;
  applicant_name: string;
  applicant_email: string | null;
  applicant_phone: string | null;
  property_number: string;
  workflow_type: string;
  current_stage: string;
  status: 'pending' | 'approved' | 'rejected';
  approval_chain: Array<{
    stage: string;
    action: string;
    actor_name: string;
    timestamp: string;
    note?: string;
  }>;
  valid_from: string | null;
  valid_until: string | null;
  notes: string | null;
  pass_id: number | null;
  created_at: string;
  homeowner?: { name: string };
  pass?: { pass_id: string; status: string };
}

interface ComplianceDocument {
  id: number;
  title: string;
  document_type: string;
  holder_name: string;
  file_name: string;
  status: 'valid' | 'expiring_soon' | 'expired';
  issued_at: string | null;
  expires_at: string;
  verification_status: string;
  gate_pass_id: number | null;
  gate_pass?: { pass_id: string; status: string; holder_name: string };
}

interface AuditTimelineEvent {
  id: number;
  pass_id: string;
  holder_name: string;
  gate: string;
  event_type: string;
  severity: 'INFO' | 'NOTICE' | 'WARNING' | 'CRITICAL';
  headline: string;
  description: string | null;
  actor_type: string;
  telemetry: Record<string, any> | null;
  occurred_at: string;
}

interface VisibilityRule {
  role: string;
  own_property: string | boolean;
  other_properties: boolean;
  entire_community: string | boolean;
  can_see: string[];
  can_act: string[];
  cannot_act: string[];
}

interface UserScope {
  role: string;
  associatedProperties: string[];
}

interface Props {
  incidents: RiskIncident[];
  approvalRequests: ApprovalRequest[];
  documents: ComplianceDocument[];
  recentTimeline: AuditTimelineEvent[];
  visibilityMatrix?: VisibilityRule[];
  userScope?: UserScope | null;
  metrics: {
    activeIncidents: number;
    criticalIncidents: number;
    pendingApprovals: number;
    expiringDocuments: number;
    expiredPassesToday: number;
  };
}

export default function AccessGovernance({
  incidents = [],
  approvalRequests = [],
  documents = [],
  recentTimeline = [],
  visibilityMatrix = [],
  userScope = null,
  metrics,
}: Props) {
  const [activeTab, setActiveTab] = useState('risk');
  const [simulatorRole, setSimulatorRole] = useState('Homeowner');
  const [searchTimelinePassId, setSearchTimelinePassId] = useState('');
  const [filteredTimelineEvents, setFilteredTimelineEvents] = useState<AuditTimelineEvent[]>(recentTimeline);
  const [selectedIncident, setSelectedIncident] = useState<RiskIncident | null>(null);
  const [isLockdownDialogOpen, setIsLockdownDialogOpen] = useState(false);
  const [lockdownReason, setLockdownReason] = useState('');
  const [isAttachingDoc, setIsAttachingDoc] = useState(false);

  // New Document Form State
  const [newDocTitle, setNewDocTitle] = useState('');
  const [newDocType, setNewDocType] = useState('lease');
  const [newDocHolder, setNewDocHolder] = useState('');
  const [newDocExpires, setNewDocExpires] = useState('');

  // Handle Forensic Search
  const handleTimelineSearch = (term: string) => {
    setSearchTimelinePassId(term);
    if (!term.trim()) {
      setFilteredTimelineEvents(recentTimeline);
      return;
    }
    const cleanTerm = term.toLowerCase().trim();
    setFilteredTimelineEvents(
      recentTimeline.filter(
        e =>
          e.pass_id.toLowerCase().includes(cleanTerm) ||
          e.holder_name.toLowerCase().includes(cleanTerm) ||
          e.headline.toLowerCase().includes(cleanTerm) ||
          e.gate.toLowerCase().includes(cleanTerm)
      )
    );
  };

  const handleRunSweep = () => {
    router.post('/dashboard/access-governance/passes/expire-sweep', {}, {
      preserveScroll: true,
    });
  };

  const handleAuditDocuments = () => {
    router.post('/dashboard/access-governance/documents/audit', {}, {
      preserveScroll: true,
    });
  };

  const handleLockdown = () => {
    if (!selectedIncident) return;
    router.post(`/dashboard/access-governance/incidents/${selectedIncident.id}/lockdown`, {
      reason: lockdownReason || 'Immediate security quarantine from Access Risk Engine',
    }, {
      preserveScroll: true,
      onSuccess: () => {
        setIsLockdownDialogOpen(false);
        setLockdownReason('');
        setSelectedIncident(null);
      }
    });
  };

  const handleApproveRequest = (id: number) => {
    router.post(`/dashboard/access-governance/approvals/${id}/approve`, {
      notes: 'Approved via Governance Dashboard',
    }, {
      preserveScroll: true,
    });
  };

  const handleRejectRequest = (id: number) => {
    const reason = prompt('Please specify rejection reason:');
    if (!reason) return;
    router.post(`/dashboard/access-governance/approvals/${id}/reject`, {
      reason,
    }, {
      preserveScroll: true,
    });
  };

  const handleCreateDocument = (e: React.FormEvent) => {
    e.preventDefault();
    router.post('/dashboard/access-governance/documents/attach', {
      title: newDocTitle,
      document_type: newDocType,
      holder_name: newDocHolder,
      expires_at: newDocExpires,
    }, {
      preserveScroll: true,
      onSuccess: () => {
        setIsAttachingDoc(false);
        setNewDocTitle('');
        setNewDocHolder('');
        setNewDocExpires('');
      }
    });
  };

  return (
    <DashboardLayout>
      <Head title="Access Governance & Risk Engine" />

      <div className="space-y-6 pb-12">
        {/* Header Section */}
        <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-border/40 pb-5">
          <div>
            <div className="flex items-center gap-2 mb-1">
              <span className="p-1.5 rounded-lg bg-red-500/10 text-red-600 dark:text-red-400">
                <ShieldAlert className="w-5 h-5" />
              </span>
              <span className="text-xs uppercase tracking-wider font-bold text-muted-foreground">
                Zero-Trust Perimeter & Continuous Policy Enforcement
              </span>
            </div>
            <h1 className="text-3xl font-extrabold tracking-tight">Access Risk & Governance Engine</h1>
            <p className="text-sm text-muted-foreground mt-1">
              Proactive anomaly detection, multi-tier approvals, mandatory expiration compliance, digital lease vault, and granular forensic timelines.
            </p>
          </div>

          <div className="flex items-center gap-2 flex-wrap">
            <Button
              variant="outline"
              size="sm"
              onClick={handleRunSweep}
              className="gap-1.5 text-xs font-semibold shadow-xs"
            >
              <RefreshCw className="w-3.5 h-3.5" /> Run Expiration Sweep
            </Button>
            <Button
              variant="outline"
              size="sm"
              onClick={handleAuditDocuments}
              className="gap-1.5 text-xs font-semibold shadow-xs"
            >
              <FileClock className="w-3.5 h-3.5" /> Audit Leases & Docs
            </Button>
            <Button
              size="sm"
              onClick={() => setIsAttachingDoc(true)}
              className="gap-1.5 text-xs font-semibold shadow-xs bg-primary text-primary-foreground hover:bg-primary/90"
            >
              <PlusCircle className="w-3.5 h-3.5" /> Attach Compliance Doc
            </Button>
          </div>
        </div>

        {/* 5-Pillar Scorecard Summary Banner */}
        <div className="grid grid-cols-2 md:grid-cols-5 gap-3.5">
          <Card className="border border-red-500/20 bg-linear-to-br from-red-500/5 to-transparent shadow-xs">
            <CardContent className="p-4">
              <div className="flex items-center justify-between">
                <p className="text-xs font-medium text-muted-foreground">Suspicious Alerts</p>
                <AlertTriangle className="w-4 h-4 text-red-500" />
              </div>
              <div className="mt-2 flex items-baseline gap-2">
                <span className="text-2xl font-black text-red-600 dark:text-red-400">
                  {metrics.activeIncidents}
                </span>
                {metrics.criticalIncidents > 0 && (
                  <Badge variant="destructive" className="text-[10px] uppercase font-bold animate-pulse px-1.5 py-0">
                    {metrics.criticalIncidents} Critical
                  </Badge>
                )}
              </div>
              <p className="text-[11px] text-muted-foreground mt-1">Active risk anomalies</p>
            </CardContent>
          </Card>

          <Card className="border border-amber-500/20 bg-linear-to-br from-amber-500/5 to-transparent shadow-xs">
            <CardContent className="p-4">
              <div className="flex items-center justify-between">
                <p className="text-xs font-medium text-muted-foreground">Pending Approvals</p>
                <Layers className="w-4 h-4 text-amber-500" />
              </div>
              <div className="mt-2 flex items-baseline gap-2">
                <span className="text-2xl font-black text-amber-600 dark:text-amber-400">
                  {metrics.pendingApprovals}
                </span>
                <span className="text-xs text-muted-foreground">in queue</span>
              </div>
              <p className="text-[11px] text-muted-foreground mt-1">Tiered governance routing</p>
            </CardContent>
          </Card>

          <Card className="border border-blue-500/20 bg-linear-to-br from-blue-500/5 to-transparent shadow-xs">
            <CardContent className="p-4">
              <div className="flex items-center justify-between">
                <p className="text-xs font-medium text-muted-foreground">Mandatory Expirations</p>
                <Clock className="w-4 h-4 text-blue-500" />
              </div>
              <div className="mt-2 flex items-baseline gap-2">
                <span className="text-2xl font-black text-blue-600 dark:text-blue-400">100%</span>
                <span className="text-xs text-muted-foreground">enforced</span>
              </div>
              <p className="text-[11px] text-muted-foreground mt-1">Zero forgotten permissions</p>
            </CardContent>
          </Card>

          <Card className="border border-emerald-500/20 bg-linear-to-br from-emerald-500/5 to-transparent shadow-xs">
            <CardContent className="p-4">
              <div className="flex items-center justify-between">
                <p className="text-xs font-medium text-muted-foreground">Doc Compliance</p>
                <FileCheck className="w-4 h-4 text-emerald-500" />
              </div>
              <div className="mt-2 flex items-baseline gap-2">
                <span className="text-2xl font-black text-foreground">
                  {documents.length}
                </span>
                {metrics.expiringDocuments > 0 && (
                  <Badge variant="outline" className="text-[10px] text-amber-600 border-amber-300">
                    {metrics.expiringDocuments} Expirations
                  </Badge>
                )}
              </div>
              <p className="text-[11px] text-muted-foreground mt-1">Leases, certs, IDs verified</p>
            </CardContent>
          </Card>

          <Card className="border border-purple-500/20 bg-linear-to-br from-purple-500/5 to-transparent shadow-xs col-span-2 md:col-span-1">
            <CardContent className="p-4">
              <div className="flex items-center justify-between">
                <p className="text-xs font-medium text-muted-foreground">Forensic Timeline</p>
                <History className="w-4 h-4 text-purple-500" />
              </div>
              <div className="mt-2 flex items-baseline gap-2">
                <span className="text-2xl font-black text-purple-600 dark:text-purple-400">
                  {recentTimeline.length}
                </span>
                <span className="text-xs text-muted-foreground">events</span>
              </div>
              <p className="text-[11px] text-muted-foreground mt-1">Full chronological audit</p>
            </CardContent>
          </Card>
        </div>

        {/* Tab Navigation */}
        <Tabs value={activeTab} onValueChange={setActiveTab} className="space-y-4">
          <TabsList className="grid grid-cols-2 md:grid-cols-6 p-1 bg-muted/60 rounded-xl h-auto">
            <TabsTrigger value="risk" className="flex items-center gap-2 py-2.5 text-xs font-semibold">
              <AlertTriangle className="w-4 h-4 text-red-500" />
              <span>Suspicious Risk Engine</span>
              {metrics.activeIncidents > 0 && (
                <span className="ml-1 px-1.5 py-0.2 bg-red-600 text-white rounded-full text-[10px] font-bold">
                  {metrics.activeIncidents}
                </span>
              )}
            </TabsTrigger>

            <TabsTrigger value="approvals" className="flex items-center gap-2 py-2.5 text-xs font-semibold">
              <Layers className="w-4 h-4 text-amber-500" />
              <span>Approval Workflows</span>
              {metrics.pendingApprovals > 0 && (
                <span className="ml-1 px-1.5 py-0.2 bg-amber-600 text-white rounded-full text-[10px] font-bold">
                  {metrics.pendingApprovals}
                </span>
              )}
            </TabsTrigger>

            <TabsTrigger value="expiration" className="flex items-center gap-2 py-2.5 text-xs font-semibold">
              <Clock className="w-4 h-4 text-blue-500" />
              <span>Mandatory Expiration</span>
            </TabsTrigger>

            <TabsTrigger value="visibility" className="flex items-center gap-2 py-2.5 text-xs font-semibold">
              <Eye className="w-4 h-4 text-cyan-500" />
              <span>Visibility Model</span>
            </TabsTrigger>

            <TabsTrigger value="documents" className="flex items-center gap-2 py-2.5 text-xs font-semibold">
              <FileText className="w-4 h-4 text-emerald-500" />
              <span>Digital Lease & Docs</span>
            </TabsTrigger>

            <TabsTrigger value="timeline" className="flex items-center gap-2 py-2.5 text-xs font-semibold">
              <History className="w-4 h-4 text-purple-500" />
              <span>Complete Audit Timeline</span>
            </TabsTrigger>
          </TabsList>

          {/* TAB 1: SUSPICIOUS ACCESS DETECTION (ACCESS RISK ENGINE) */}
          <TabsContent value="risk" className="space-y-4">
            <div className="bg-red-500/10 border border-red-500/20 rounded-xl p-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
              <div className="flex items-center gap-3">
                <div className="p-2.5 rounded-full bg-red-600 text-white shadow-xs">
                  <ShieldAlert className="w-5 h-5" />
                </div>
                <div>
                  <h3 className="font-bold text-red-950 dark:text-red-200 text-sm">
                    ⚠️ Suspicious Credential Activity Engine Active
                  </h3>
                  <p className="text-xs text-red-800 dark:text-red-300">
                    Real-time detection across 8 threat vectors: excessive failed scans, expired QR attempts, revoked QR replay, simultaneous multi-gate presentations (cloned passes), unusual hours, and keyfob cycling.
                  </p>
                </div>
              </div>
              <Badge variant="destructive" className="uppercase font-bold tracking-wider text-xs">
                Zero-Trust Guard Mode
              </Badge>
            </div>

            {incidents.length === 0 ? (
              <Card className="text-center py-12">
                <CardContent className="space-y-2">
                  <CheckCircle2 className="w-10 h-10 text-emerald-500 mx-auto" />
                  <h3 className="text-base font-semibold">No Suspicious Incidents Detected</h3>
                  <p className="text-xs text-muted-foreground max-w-md mx-auto">
                    All gate presentation events are operating within nominal security parameters. When anomalous presentation patterns occur, immediate quarantine actions appear here.
                  </p>
                </CardContent>
              </Card>
            ) : (
              <div className="space-y-3">
                {incidents.map(incident => (
                  <Card
                    key={incident.id}
                    className={`border transition-all ${
                      incident.status === 'NEW'
                        ? incident.risk_level === 'CRITICAL'
                          ? 'border-red-500/50 bg-red-500/5 shadow-md ring-1 ring-red-500/20'
                          : 'border-amber-500/40 bg-amber-500/5'
                        : 'border-border/60 opacity-80'
                    }`}
                  >
                    <CardContent className="p-4 sm:p-5">
                      <div className="flex flex-col md:flex-row md:items-start justify-between gap-4">
                        <div className="space-y-2 flex-1">
                          <div className="flex items-center gap-2 flex-wrap">
                            <Badge
                              className={`text-[11px] font-bold uppercase tracking-wider ${
                                incident.risk_level === 'CRITICAL'
                                  ? 'bg-red-600 text-white'
                                  : incident.risk_level === 'HIGH'
                                  ? 'bg-amber-600 text-white'
                                  : 'bg-yellow-600 text-white'
                              }`}
                            >
                              {incident.risk_level} RISK
                            </Badge>

                            <Badge variant="outline" className="text-xs font-mono">
                              {incident.flag_type}
                            </Badge>

                            <span className="text-xs font-semibold text-muted-foreground flex items-center gap-1">
                              <Building className="w-3.5 h-3.5" /> Gate: {incident.gate}
                            </span>

                            <span className="text-xs text-muted-foreground flex items-center gap-1">
                              <Clock className="w-3.5 h-3.5" /> {new Date(incident.occurred_at).toLocaleString()}
                            </span>

                            <Badge
                              variant="secondary"
                              className={`text-[10px] font-semibold uppercase ${
                                incident.status === 'NEW'
                                  ? 'bg-red-500/20 text-red-700 dark:text-red-300'
                                  : incident.status === 'LOCKED_DOWN'
                                  ? 'bg-zinc-800 text-white dark:bg-zinc-200 dark:text-zinc-900'
                                  : 'bg-emerald-500/20 text-emerald-700'
                              }`}
                            >
                              {incident.status.replace('_', ' ')}
                            </Badge>
                          </div>

                          <h4 className="text-base font-bold text-foreground flex items-center gap-2">
                            {incident.title}
                          </h4>

                          <p className="text-xs text-muted-foreground leading-relaxed">
                            {incident.description}
                          </p>

                          {incident.evidence && (
                            <div className="mt-2 p-2.5 rounded-lg bg-muted/60 border border-border/40 text-[11px] font-mono overflow-x-auto">
                              <span className="text-[10px] uppercase font-bold text-muted-foreground tracking-wider block mb-1">
                                Forensic Telemetry Evidence:
                              </span>
                              <pre className="whitespace-pre-wrap text-foreground/90">
                                {JSON.stringify(incident.evidence, null, 2)}
                              </pre>
                            </div>
                          )}

                          {incident.resolution_notes && (
                            <div className="mt-2 text-xs bg-emerald-500/10 border border-emerald-500/20 p-2.5 rounded-lg text-emerald-900 dark:text-emerald-300">
                              <span className="font-semibold">Resolution Notes: </span>
                              {incident.resolution_notes}
                            </div>
                          )}
                        </div>

                        {/* Actions */}
                        <div className="flex md:flex-col gap-2 shrink-0 self-end md:self-start">
                          {incident.status === 'NEW' && incident.pass_id && (
                            <Button
                              variant="destructive"
                              size="sm"
                              className="gap-1.5 text-xs font-bold shadow-xs hover:bg-red-700"
                              onClick={() => {
                                setSelectedIncident(incident);
                                setIsLockdownDialogOpen(true);
                              }}
                            >
                              <Lock className="w-3.5 h-3.5" /> Enforce Lockdown
                            </Button>
                          )}

                          {incident.status === 'NEW' && (
                            <Button
                              variant="outline"
                              size="sm"
                              className="gap-1.5 text-xs font-semibold"
                              onClick={() => {
                                const notes = prompt('Enter investigation / resolution notes:');
                                if (!notes) return;
                                router.post(`/dashboard/access-governance/incidents/${incident.id}/resolve`, {
                                  resolution_notes: notes,
                                }, { preserveScroll: true });
                              }}
                            >
                              <CheckCircle2 className="w-3.5 h-3.5 text-emerald-600" /> Resolve Alert
                            </Button>
                          )}

                          {incident.pass_id && (
                            <Button
                              variant="ghost"
                              size="sm"
                              className="gap-1 text-xs text-muted-foreground"
                              onClick={() => {
                                setActiveTab('timeline');
                                handleTimelineSearch(incident.pass_id!);
                              }}
                            >
                              <History className="w-3.5 h-3.5" /> Trace Pass
                            </Button>
                          )}
                        </div>
                      </div>
                    </CardContent>
                  </Card>
                ))}
              </div>
            )}
          </TabsContent>

          {/* TAB 2: APPROVAL WORKFLOWS */}
          <TabsContent value="approvals" className="space-y-4">
            {/* Visual Workflow Explainer */}
            <Card className="border border-border/60 bg-muted/20">
              <CardHeader className="p-4 pb-2">
                <CardTitle className="text-sm font-bold flex items-center gap-2">
                  <Layers className="w-4 h-4 text-primary" />
                  Tiered Access Governance Matrix
                </CardTitle>
                <CardDescription className="text-xs">
                  Automated routing guarantees that each persona receives the appropriate legal and administrative approval prior to gate pass generation.
                </CardDescription>
              </CardHeader>
              <CardContent className="p-4 pt-2">
                <div className="grid grid-cols-1 md:grid-cols-4 gap-3 text-xs">
                  <div className="p-3 rounded-lg border bg-card/60">
                    <span className="font-bold text-foreground block mb-1">Visitor</span>
                    <p className="text-muted-foreground mb-2">Pre-registered guest for resident unit</p>
                    <div className="flex items-center gap-1.5 text-[11px] font-semibold text-emerald-600 dark:text-emerald-400">
                      <span>Homeowner</span>
                      <ArrowRight className="w-3 h-3" />
                      <span>Approved (Instant)</span>
                    </div>
                  </div>

                  <div className="p-3 rounded-lg border bg-card/60">
                    <span className="font-bold text-foreground block mb-1">Contractor</span>
                    <p className="text-muted-foreground mb-2">Vendors, maintenance, solar installers</p>
                    <div className="flex items-center gap-1.5 text-[11px] font-semibold text-blue-600 dark:text-blue-400">
                      <span>Homeowner</span>
                      <ArrowRight className="w-3 h-3" />
                      <span>Property Admin</span>
                      <ArrowRight className="w-3 h-3" />
                      <span>Approved</span>
                    </div>
                  </div>

                  <div className="p-3 rounded-lg border bg-card/60">
                    <span className="font-bold text-foreground block mb-1">Long-Term Occupant</span>
                    <p className="text-muted-foreground mb-2">Renters & extended family members</p>
                    <div className="flex items-center gap-1.5 text-[11px] font-semibold text-purple-600 dark:text-purple-400">
                      <span>Homeowner</span>
                      <ArrowRight className="w-3 h-3" />
                      <span>Community Admin</span>
                      <ArrowRight className="w-3 h-3" />
                      <span>Approved</span>
                    </div>
                  </div>

                  <div className="p-3 rounded-lg border bg-card/60">
                    <span className="font-bold text-foreground block mb-1">Legacy Contact</span>
                    <p className="text-muted-foreground mb-2">Succession, estate delegate, caregivers</p>
                    <div className="flex items-center gap-1.5 text-[11px] font-semibold text-amber-600 dark:text-amber-400">
                      <span>Homeowner</span>
                      <ArrowRight className="w-3 h-3" />
                      <span>ID Verification</span>
                      <ArrowRight className="w-3 h-3" />
                      <span>Approved</span>
                    </div>
                  </div>
                </div>
              </CardContent>
            </Card>

            {/* Live Request Queue */}
            <div className="space-y-3">
              <h3 className="text-sm font-bold tracking-tight text-foreground flex items-center justify-between">
                <span>Active Authorization Requests</span>
                <span className="text-xs font-normal text-muted-foreground">
                  {approvalRequests.filter(r => r.status === 'pending').length} requiring review
                </span>
              </h3>

              {approvalRequests.length === 0 && (
                <Card className="border-dashed text-center py-8">
                  <CardContent className="space-y-1">
                    <CheckCircle2 className="w-8 h-8 text-emerald-500 mx-auto" />
                    <h4 className="text-sm font-semibold">No authorization requests</h4>
                    <p className="text-xs text-muted-foreground">Requests from homeowners for occupants and contractors appear here for review.</p>
                  </CardContent>
                </Card>
              )}

              {approvalRequests.map(req => (
                <Card key={req.id} className="border border-border/60 hover:border-border transition-all">
                  <CardContent className="p-4 sm:p-5">
                    <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                      <div className="space-y-2 flex-1">
                        <div className="flex items-center gap-2 flex-wrap">
                          <Badge variant="outline" className="font-mono text-xs">
                            {req.request_number}
                          </Badge>

                          <Badge
                            className={`text-[10px] uppercase font-bold ${
                              req.category === 'long_term_occupant'
                                ? 'bg-purple-600 text-white'
                                : req.category === 'contractor'
                                ? 'bg-blue-600 text-white'
                                : req.category === 'visitor'
                                ? 'bg-emerald-600 text-white'
                                : 'bg-amber-600 text-white'
                            }`}
                          >
                            {req.category.replace('_', ' ')}
                          </Badge>

                          <span className="text-xs font-semibold text-muted-foreground flex items-center gap-1">
                            <Building className="w-3.5 h-3.5" /> {req.property_number}
                          </span>

                          <Badge
                            variant={req.status === 'approved' ? 'default' : req.status === 'rejected' ? 'destructive' : 'secondary'}
                            className="text-[10px] uppercase font-bold"
                          >
                            {req.status}
                          </Badge>
                        </div>

                        <div>
                          <h4 className="text-base font-bold text-foreground">
                            {req.applicant_name}
                          </h4>
                          <p className="text-xs text-muted-foreground mt-0.5">
                            {req.notes || 'No extra notes provided by applicant.'}
                          </p>
                        </div>

                        {/* Multi-step progression breadcrumbs */}
                        <div className="flex items-center gap-2 pt-1 overflow-x-auto text-[11px]">
                          <span className="font-semibold text-muted-foreground">Progress:</span>
                          {req.approval_chain.map((step, idx) => (
                            <React.Fragment key={idx}>
                              <span className="px-2 py-0.5 rounded-md bg-muted font-medium flex items-center gap-1">
                                <CheckCircle2 className="w-3 h-3 text-emerald-500" />
                                {step.action.replace('_', ' ')} ({step.actor_name})
                              </span>
                              {idx < req.approval_chain.length - 1 && (
                                <ChevronRight className="w-3 h-3 text-muted-foreground" />
                              )}
                            </React.Fragment>
                          ))}
                          {req.status === 'pending' && (
                            <span className="px-2 py-0.5 rounded-md bg-amber-500/20 text-amber-700 dark:text-amber-300 font-bold animate-pulse">
                              Pending {req.current_stage.replace('_', ' ')}
                            </span>
                          )}
                        </div>
                      </div>

                      {/* Approval Actions */}
                      <div className="flex md:flex-col gap-2 shrink-0 self-end md:self-center">
                        {req.status === 'pending' ? (
                          <>
                            <Button
                              size="sm"
                              className="gap-1.5 text-xs font-semibold bg-emerald-600 hover:bg-emerald-700 text-white shadow-xs"
                              onClick={() => handleApproveRequest(req.id)}
                            >
                              <CheckCircle2 className="w-3.5 h-3.5" /> Approve Stage
                            </Button>
                            <Button
                              variant="outline"
                              size="sm"
                              className="gap-1.5 text-xs text-red-600 hover:bg-red-50 hover:text-red-700"
                              onClick={() => handleRejectRequest(req.id)}
                            >
                              <XCircle className="w-3.5 h-3.5" /> Reject
                            </Button>
                          </>
                        ) : req.pass ? (
                          <div className="text-right">
                            <span className="text-[10px] text-muted-foreground block">Active Gate Pass</span>
                            <Badge variant="outline" className="font-mono text-xs text-emerald-600">
                              {req.pass.pass_id}
                            </Badge>
                          </div>
                        ) : null}
                      </div>
                    </div>
                  </CardContent>
                </Card>
              ))}
            </div>
          </TabsContent>

          {/* TAB 3: MANDATORY AUTOMATIC EXPIRATION */}
          <TabsContent value="expiration" className="space-y-4">
            <Card className="border border-blue-500/20 bg-linear-to-br from-blue-500/5 to-transparent">
              <CardHeader className="p-4 pb-2">
                <CardTitle className="text-sm font-bold flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <Clock className="w-4 h-4 text-blue-500" />
                    Mandatory Expiration Policy Engine
                  </div>
                  <Badge variant="outline" className="text-xs text-blue-600 border-blue-300">
                    Continuous Sweeping Active
                  </Badge>
                </CardTitle>
                <CardDescription className="text-xs">
                  Zero forgotten permissions: every non-permanent authorization is bound to a hard mathematical cutoff timestamp. Once the validity window closes, access tokens are cryptographically rejected at all gates.
                </CardDescription>
              </CardHeader>
              <CardContent className="p-4 pt-2">
                <div className="grid grid-cols-1 md:grid-cols-5 gap-3 mt-2">
                  <div className="p-3.5 rounded-xl border bg-card/80 space-y-1">
                    <span className="text-xs font-bold text-foreground block">Visitor Passes</span>
                    <p className="text-[11px] text-muted-foreground">Pre-registered guests and social visits</p>
                    <div className="pt-2">
                      <Badge className="bg-blue-600 text-white text-[10px] uppercase font-bold">
                        Expires Tonight (23:59)
                      </Badge>
                    </div>
                  </div>

                  <div className="p-3.5 rounded-xl border bg-card/80 space-y-1">
                    <span className="text-xs font-bold text-foreground block">Contractor Passes</span>
                    <p className="text-[11px] text-muted-foreground">Trades, technicians, deliveries</p>
                    <div className="pt-2">
                      <Badge className="bg-indigo-600 text-white text-[10px] uppercase font-bold">
                        Expires Friday (18:00)
                      </Badge>
                    </div>
                  </div>

                  <div className="p-3.5 rounded-xl border bg-card/80 space-y-1">
                    <span className="text-xs font-bold text-foreground block">Long-Term Occupant</span>
                    <p className="text-[11px] text-muted-foreground">Renters & subleases with contracts</p>
                    <div className="pt-2">
                      <Badge className="bg-purple-600 text-white text-[10px] uppercase font-bold">
                        Expires Lease Date
                      </Badge>
                    </div>
                  </div>

                  <div className="p-3.5 rounded-xl border bg-card/80 space-y-1">
                    <span className="text-xs font-bold text-foreground block">Caregivers & Staff</span>
                    <p className="text-[11px] text-muted-foreground">Domestic nurses, household staff</p>
                    <div className="pt-2">
                      <Badge className="bg-emerald-600 text-white text-[10px] uppercase font-bold">
                        Expires Dec 31
                      </Badge>
                    </div>
                  </div>

                  <div className="p-3.5 rounded-xl border bg-card/80 space-y-1">
                    <span className="text-xs font-bold text-foreground block">Legacy Contacts</span>
                    <p className="text-[11px] text-muted-foreground">Delegated access & legal nominees</p>
                    <div className="pt-2">
                      <Badge className="bg-amber-600 text-white text-[10px] uppercase font-bold">
                        Max 90-Day Window
                      </Badge>
                    </div>
                  </div>
                </div>

                <div className="mt-5 p-4 rounded-xl bg-muted/40 border flex flex-col sm:flex-row items-center justify-between gap-3">
                  <div>
                    <h4 className="text-xs font-bold text-foreground">Enforce Immediate Policy Sweep</h4>
                    <p className="text-[11px] text-muted-foreground">
                      Scans the global gate pass directory and immediately transitions any pass past its deadline to EXPIRED status, logging events in the audit trail.
                    </p>
                  </div>
                  <Button
                    onClick={handleRunSweep}
                    className="gap-2 text-xs font-semibold shrink-0 bg-blue-600 hover:bg-blue-700 text-white"
                  >
                    <RefreshCw className="w-3.5 h-3.5" /> Sweep Stale Passes
                  </Button>
                </div>
              </CardContent>
            </Card>
          </TabsContent>

          {/* TAB: VISIBILITY MODEL & AUTHORIZATION MATRIX */}
          <TabsContent value="visibility" className="space-y-4">
            <Card className="border border-cyan-500/20 bg-linear-to-br from-cyan-500/5 to-transparent">
              <CardHeader className="p-4 pb-2">
                <CardTitle className="text-sm font-bold flex items-center justify-between flex-wrap gap-2">
                  <div className="flex items-center gap-2">
                    <Eye className="w-4 h-4 text-cyan-500" />
                    Property-Scoped Visibility & Authorization Model
                  </div>
                  <Badge variant="outline" className="text-xs text-cyan-600 border-cyan-300">
                    Default Scope: Property / Address
                  </Badge>
                </CardTitle>
                <CardDescription className="text-xs space-y-1">
                  <p>
                    <strong>Core Rule:</strong> A person can only see people and access information that their role is authorized to see, and the default scope is the property/address they are associated with.
                  </p>
                  <p className="text-cyan-700 dark:text-cyan-300 font-medium">
                    ⚡ <strong>Crucial Separation of Duties:</strong> &quot;Can See&quot; (visibility/inspection) and &quot;Can Act&quot; (creation/mutation/approval) are strictly separate permissions across all 10 roles.
                  </p>
                </CardDescription>
              </CardHeader>

              <CardContent className="p-4 pt-2 space-y-6">
                {/* Governance Role Matrix Table */}
                <div className="rounded-xl border border-border/60 overflow-hidden bg-card/60 shadow-xs">
                  <div className="overflow-x-auto">
                    <table className="w-full text-left text-xs border-collapse">
                      <thead>
                        <tr className="bg-muted/70 border-b border-border/60 text-muted-foreground font-semibold">
                          <th className="py-2.5 px-3">Role</th>
                          <th className="py-2.5 px-3">Own Property</th>
                          <th className="py-2.5 px-3 text-center">Other Properties</th>
                          <th className="py-2.5 px-3 text-center">Entire Community</th>
                          <th className="py-2.5 px-3">Can See vs Can Act Summary</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-border/40">
                        {visibilityMatrix.map((rule) => {
                          const isSelected = simulatorRole === rule.role;
                          return (
                            <tr
                              key={rule.role}
                              onClick={() => setSimulatorRole(rule.role)}
                              className={`cursor-pointer transition-colors ${
                                isSelected ? 'bg-cyan-500/10 font-medium' : 'hover:bg-muted/40'
                              }`}
                            >
                              <td className="py-2.5 px-3">
                                <div className="flex items-center gap-1.5">
                                  <Badge
                                    variant={isSelected ? 'default' : 'outline'}
                                    className="text-[11px] font-semibold"
                                  >
                                    {rule.role}
                                  </Badge>
                                </div>
                              </td>

                              <td className="py-2.5 px-3">
                                {rule.own_property === true ? (
                                  <Badge className="bg-emerald-600 text-white text-[10px]">✅ Full Scope</Badge>
                                ) : (
                                  <span className="text-[11px] font-medium text-foreground">
                                    {String(rule.own_property)}
                                  </span>
                                )}
                              </td>

                              <td className="py-2.5 px-3 text-center">
                                {rule.other_properties ? (
                                  <Badge className="bg-emerald-600 text-white text-[10px]">✅</Badge>
                                ) : (
                                  <Badge variant="outline" className="text-red-600 border-red-300 text-[10px]">❌</Badge>
                                )}
                              </td>

                              <td className="py-2.5 px-3 text-center">
                                {rule.entire_community === 'All' || rule.entire_community === true ? (
                                  <Badge className="bg-purple-600 text-white text-[10px]">All ✅</Badge>
                                ) : (
                                  <Badge variant="outline" className="text-red-600 border-red-300 text-[10px]">❌</Badge>
                                )}
                              </td>

                              <td className="py-2.5 px-3">
                                <div className="text-[11px] space-y-0.5">
                                  <span className="text-muted-foreground block truncate max-w-xs">
                                    <strong className="text-emerald-600">See:</strong> {rule.can_see[0]}
                                  </span>
                                  <span className="text-muted-foreground block truncate max-w-xs">
                                    <strong className="text-blue-600">Act:</strong> {rule.can_act[0]}
                                  </span>
                                </div>
                              </td>
                            </tr>
                          );
                        })}
                      </tbody>
                    </table>
                  </div>
                </div>

                {/* Interactive Role Scope Inspector & Permission Simulator */}
                {(() => {
                  const currentRule = visibilityMatrix.find(r => r.role === simulatorRole) || visibilityMatrix[0];
                  if (!currentRule) return null;

                  return (
                    <div className="p-4 rounded-xl border border-border/80 bg-muted/20 space-y-4">
                      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-border/60 pb-3">
                        <div>
                          <h4 className="text-xs font-bold text-foreground flex items-center gap-2">
                            <span>Deep Dive Scope Inspector:</span>
                            <Badge className="bg-cyan-600 text-white text-xs">{currentRule.role}</Badge>
                          </h4>
                          <p className="text-[11px] text-muted-foreground mt-0.5">
                            Demonstrating exact architectural distinction between viewing rights and mutation capabilities.
                          </p>
                        </div>
                        <div className="flex items-center gap-1.5 flex-wrap">
                          <span className="text-[11px] text-muted-foreground mr-1">Switch Persona:</span>
                          {visibilityMatrix.slice(0, 5).map(r => (
                            <Button
                              key={r.role}
                              variant={simulatorRole === r.role ? 'default' : 'outline'}
                              size="sm"
                              className="h-6 text-[10px] px-2"
                              onClick={() => setSimulatorRole(r.role)}
                            >
                              {r.role}
                            </Button>
                          ))}
                        </div>
                      </div>

                      <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                        {/* What Role Can See */}
                        <div className="p-3.5 rounded-lg border border-emerald-500/30 bg-emerald-500/5 space-y-2">
                          <div className="flex items-center gap-2 text-emerald-700 dark:text-emerald-400 font-bold text-xs">
                            <CheckCircle2 className="w-4 h-4 shrink-0" />
                            <span>Authorized Visibility (&quot;Can See&quot;)</span>
                          </div>
                          <ul className="space-y-1.5 text-[11px] text-muted-foreground">
                            {currentRule.can_see.map((item, idx) => (
                              <li key={idx} className="flex items-start gap-1.5">
                                <span className="text-emerald-500 mt-0.5 font-bold">✓</span>
                                <span>{item}</span>
                              </li>
                            ))}
                          </ul>
                        </div>

                        {/* What Role Can Act On */}
                        <div className="p-3.5 rounded-lg border border-blue-500/30 bg-blue-500/5 space-y-2">
                          <div className="flex items-center gap-2 text-blue-700 dark:text-blue-400 font-bold text-xs">
                            <Sparkles className="w-4 h-4 shrink-0" />
                            <span>Action Capabilities (&quot;Can Act&quot;)</span>
                          </div>
                          <ul className="space-y-1.5 text-[11px] text-muted-foreground">
                            {currentRule.can_act.map((item, idx) => (
                              <li key={idx} className="flex items-start gap-1.5">
                                <span className="text-blue-500 mt-0.5 font-bold">⚡</span>
                                <span>{item}</span>
                              </li>
                            ))}
                          </ul>
                        </div>

                        {/* Guardrails: Why Can See != Can Act */}
                        <div className="p-3.5 rounded-lg border border-amber-500/30 bg-amber-500/5 space-y-2">
                          <div className="flex items-center gap-2 text-amber-700 dark:text-amber-400 font-bold text-xs">
                            <Lock className="w-4 h-4 shrink-0" />
                            <span>Strict Security Guardrails</span>
                          </div>
                          <ul className="space-y-1.5 text-[11px] text-muted-foreground">
                            {currentRule.cannot_act.map((item, idx) => (
                              <li key={idx} className="flex items-start gap-1.5">
                                <span className="text-amber-600 mt-0.5 font-bold">🚫</span>
                                <span>{item}</span>
                              </li>
                            ))}
                          </ul>
                        </div>
                      </div>
                    </div>
                  );
                })()}
              </CardContent>
            </Card>
          </TabsContent>

          {/* TAB 4: DIGITAL LEASE & AUTHORIZATION DOCUMENTS */}
          <TabsContent value="documents" className="space-y-4">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
              <div>
                <h3 className="text-sm font-bold tracking-tight">Compliance Document Vault</h3>
                <p className="text-xs text-muted-foreground">
                  Attach and verify digital leases, authorization letters, photo IDs, insurance policies, and contractor certifications.
                </p>
              </div>

              <Button
                size="sm"
                onClick={() => setIsAttachingDoc(true)}
                className="gap-1.5 text-xs font-semibold"
              >
                <PlusCircle className="w-3.5 h-3.5" /> Upload Document
              </Button>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-3 gap-3.5">
              {documents.length === 0 && (
                <Card className="md:col-span-3 border-dashed text-center py-8">
                  <CardContent className="space-y-1">
                    <FileText className="w-8 h-8 text-muted-foreground mx-auto" />
                    <h4 className="text-sm font-semibold">No compliance documents</h4>
                    <p className="text-xs text-muted-foreground">Upload leases, insurance and contractor certificates to track their expiry.</p>
                  </CardContent>
                </Card>
              )}

              {documents.map(doc => {
                const daysRemaining = Math.ceil(
                  (new Date(doc.expires_at).getTime() - new Date().getTime()) / (1000 * 60 * 60 * 24)
                );

                return (
                  <Card key={doc.id} className="border border-border/60 hover:shadow-xs transition-all">
                    <CardHeader className="p-4 pb-2">
                      <div className="flex items-center justify-between gap-2">
                        <Badge variant="outline" className="text-[10px] uppercase font-bold">
                          {doc.document_type.replace('_', ' ')}
                        </Badge>
                        <Badge
                          className={`text-[10px] uppercase font-bold ${
                            doc.status === 'valid'
                              ? 'bg-emerald-600 text-white'
                              : doc.status === 'expiring_soon'
                              ? 'bg-amber-600 text-white animate-pulse'
                              : 'bg-red-600 text-white'
                          }`}
                        >
                          {doc.status.replace('_', ' ')}
                        </Badge>
                      </div>
                      <CardTitle className="text-sm font-bold mt-2 truncate" title={doc.title}>
                        {doc.title}
                      </CardTitle>
                      <CardDescription className="text-xs truncate">
                        Holder: <span className="font-semibold text-foreground">{doc.holder_name}</span>
                      </CardDescription>
                    </CardHeader>
                    <CardContent className="p-4 pt-1 space-y-2 text-xs">
                      <div className="flex items-center justify-between text-muted-foreground">
                        <span>Expiration:</span>
                        <span className="font-medium text-foreground">
                          {new Date(doc.expires_at).toLocaleDateString()}
                        </span>
                      </div>

                      <div className="flex items-center justify-between text-muted-foreground">
                        <span>Validity Status:</span>
                        <span className={`font-semibold ${daysRemaining <= 0 ? 'text-red-500' : daysRemaining <= 14 ? 'text-amber-500' : 'text-emerald-500'}`}>
                          {daysRemaining <= 0 ? 'Expired' : `${daysRemaining} days remaining`}
                        </span>
                      </div>

                      {doc.gate_pass && (
                        <div className="mt-2 pt-2 border-t text-[11px] text-muted-foreground">
                          <span>Linked Pass: </span>
                          <span className="font-mono font-bold text-foreground">
                            {doc.gate_pass.pass_id}
                          </span>
                          <span className="ml-1 text-[10px] uppercase">({doc.gate_pass.status})</span>
                        </div>
                      )}
                    </CardContent>
                    <CardFooter className="p-4 pt-0 flex justify-between items-center text-xs">
                      <span className="text-[11px] text-muted-foreground font-mono">
                        {doc.file_name}
                      </span>
                      <Button variant="ghost" size="sm" className="h-7 text-xs gap-1">
                        <FileText className="w-3 h-3" /> View
                      </Button>
                    </CardFooter>
                  </Card>
                );
              })}
            </div>
          </TabsContent>

          {/* TAB 5: COMPLETE FORENSIC AUDIT TIMELINE */}
          <TabsContent value="timeline" className="space-y-4">
            <Card className="border border-purple-500/20 bg-muted/20">
              <CardContent className="p-4">
                <div className="flex flex-col sm:flex-row items-center justify-between gap-3">
                  <div>
                    <h3 className="text-sm font-bold flex items-center gap-2">
                      <History className="w-4 h-4 text-purple-500" />
                      Millisecond Chronological Audit Trail
                    </h3>
                    <p className="text-xs text-muted-foreground">
                      Forensic trace of credential presentation, verification checks, security guard approval, hardware gate actuation, and exits.
                    </p>
                  </div>

                  <div className="w-full sm:w-72 relative">
                    <Search className="w-4 h-4 absolute left-3 top-2.5 text-muted-foreground" />
                    <Input
                      placeholder="Search pass ID, holder or gate..."
                      value={searchTimelinePassId}
                      onChange={e => handleTimelineSearch(e.target.value)}
                      className="pl-9 h-9 text-xs"
                    />
                  </div>
                </div>
              </CardContent>
            </Card>

            {filteredTimelineEvents.length === 0 && (
              <Card className="border-dashed text-center py-8">
                <CardContent className="space-y-1">
                  <History className="w-8 h-8 text-muted-foreground mx-auto" />
                  <h4 className="text-sm font-semibold">
                    {searchTimelinePassId.trim() ? 'No matching events' : 'No access events yet'}
                  </h4>
                  <p className="text-xs text-muted-foreground">Gate scans, approvals and revocations are recorded here as they happen.</p>
                </CardContent>
              </Card>
            )}

            {/* Timeline Stream */}
            <div className="relative pl-6 space-y-4 before:absolute before:left-2.5 before:top-3 before:bottom-3 before:w-0.5 before:bg-border/60">
              {filteredTimelineEvents.map((evt, idx) => {
                const isMarySmith = evt.pass_id.includes('MARY');
                return (
                  <div key={evt.id || idx} className="relative flex items-start gap-4 group">
                    {/* Timeline Node Icon */}
                    <div
                      className={`absolute -left-6 mt-1 w-5 h-5 rounded-full border-2 bg-background flex items-center justify-center transition-transform group-hover:scale-110 ${
                        evt.severity === 'CRITICAL'
                          ? 'border-red-500 text-red-500'
                          : evt.severity === 'WARNING'
                          ? 'border-amber-500 text-amber-500'
                          : isMarySmith
                          ? 'border-purple-500 text-purple-500'
                          : 'border-primary text-primary'
                      }`}
                    >
                      <div className="w-1.5 h-1.5 rounded-full bg-current" />
                    </div>

                    {/* Timeline Card */}
                    <Card
                      className={`flex-1 border transition-all ${
                        isMarySmith
                          ? 'border-purple-500/40 bg-purple-500/5 shadow-xs'
                          : 'border-border/60 hover:border-border'
                      }`}
                    >
                      <CardContent className="p-3.5 sm:p-4">
                        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-1 mb-1">
                          <div className="flex items-center gap-2 flex-wrap">
                            <Badge variant="outline" className="font-mono text-[10px]">
                              {evt.pass_id}
                            </Badge>

                            <Badge
                              className={`text-[9px] uppercase font-bold ${
                                evt.event_type === 'GATE_OPENED'
                                  ? 'bg-emerald-600 text-white'
                                  : evt.event_type === 'IDENTITY_VERIFIED'
                                  ? 'bg-blue-600 text-white'
                                  : evt.event_type === 'SECURITY_APPROVED'
                                  ? 'bg-purple-600 text-white'
                                  : evt.event_type === 'CREDENTIAL_DENIED'
                                  ? 'bg-red-600 text-white'
                                  : 'bg-zinc-700 text-white'
                              }`}
                            >
                              {evt.event_type.replace('_', ' ')}
                            </Badge>

                            <span className="text-xs font-bold text-foreground">
                              {evt.holder_name}
                            </span>
                          </div>

                          <div className="text-xs text-muted-foreground flex items-center gap-1 font-mono">
                            <Clock className="w-3 h-3" />
                            {new Date(evt.occurred_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' })}
                            <span className="text-[10px] text-muted-foreground">
                              ({new Date(evt.occurred_at).toLocaleDateString()})
                            </span>
                          </div>
                        </div>

                        <h4 className="text-sm font-bold text-foreground mt-1">
                          {evt.headline}
                        </h4>

                        {evt.description && (
                          <p className="text-xs text-muted-foreground mt-0.5 leading-relaxed">
                            {evt.description}
                          </p>
                        )}

                        <div className="mt-2 flex items-center gap-3 text-[11px] text-muted-foreground flex-wrap">
                          <span className="flex items-center gap-1">
                            <Building className="w-3 h-3" /> Gate: <span className="font-semibold text-foreground">{evt.gate}</span>
                          </span>
                          <span className="flex items-center gap-1">
                            <User className="w-3 h-3" /> Actor: <span className="font-semibold text-foreground">{evt.actor_type}</span>
                          </span>
                          {evt.telemetry && (
                            <span className="font-mono text-[10px] bg-muted/60 px-1.5 py-0.5 rounded">
                              telemetry: {Object.keys(evt.telemetry).length} params
                            </span>
                          )}
                        </div>
                      </CardContent>
                    </Card>
                  </div>
                );
              })}
            </div>
          </TabsContent>
        </Tabs>

        {/* Modal: Enforce Lockdown */}
        <Dialog open={isLockdownDialogOpen} onOpenChange={setIsLockdownDialogOpen}>
          <DialogContent className="sm:max-w-md">
            <DialogHeader>
              <DialogTitle className="flex items-center gap-2 text-red-600">
                <ShieldAlert className="w-5 h-5" /> Enforce Immediate Credential Lockdown
              </DialogTitle>
              <DialogDescription className="text-xs">
                This will immediately suspend pass #{selectedIncident?.pass_id} across all gates, preventing all barrier access and alerting on-duty security guards.
              </DialogDescription>
            </DialogHeader>

            <div className="space-y-3 py-2">
              <div className="p-2.5 rounded-lg bg-red-500/10 border border-red-500/20 text-xs text-red-900 dark:text-red-300">
                <span className="font-bold">Incident Flag: </span>
                {selectedIncident?.title}
              </div>

              <div className="space-y-1">
                <Label htmlFor="lockdown-reason" className="text-xs font-semibold">
                  Quarantine / Lockdown Reason
                </Label>
                <Input
                  id="lockdown-reason"
                  placeholder="e.g. Credential cloning detected between Main and Service Gate"
                  value={lockdownReason}
                  onChange={e => setLockdownReason(e.target.value)}
                  className="text-xs"
                />
              </div>
            </div>

            <DialogFooter className="flex gap-2">
              <Button variant="outline" size="sm" onClick={() => setIsLockdownDialogOpen(false)}>
                Cancel
              </Button>
              <Button variant="destructive" size="sm" onClick={handleLockdown} className="gap-1.5 font-bold">
                <Lock className="w-3.5 h-3.5" /> Execute Lockdown
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>

        {/* Modal: Attach Compliance Document */}
        <Dialog open={isAttachingDoc} onOpenChange={setIsAttachingDoc}>
          <DialogContent className="sm:max-w-md">
            <DialogHeader>
              <DialogTitle className="flex items-center gap-2">
                <FileText className="w-5 h-5 text-primary" /> Attach Compliance Document
              </DialogTitle>
              <DialogDescription className="text-xs">
                Upload verified digital documents (leases, letters of authorization, ID verifications, insurance policies).
              </DialogDescription>
            </DialogHeader>

            <form onSubmit={handleCreateDocument} className="space-y-3 py-2">
              <div className="space-y-1">
                <Label htmlFor="doc-title" className="text-xs font-semibold">
                  Document Title
                </Label>
                <Input
                  id="doc-title"
                  required
                  placeholder="e.g. Residential Lease Agreement 2026-2027"
                  value={newDocTitle}
                  onChange={e => setNewDocTitle(e.target.value)}
                  className="text-xs"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div className="space-y-1">
                  <Label htmlFor="doc-type" className="text-xs font-semibold">
                    Document Type
                  </Label>
                  <Select value={newDocType} onValueChange={setNewDocType}>
                    <SelectTrigger className="text-xs">
                      <SelectValue placeholder="Select type" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="lease">Residential Lease</SelectItem>
                      <SelectItem value="authorization_letter">Authorization Letter</SelectItem>
                      <SelectItem value="id_verification">Photo ID Verification</SelectItem>
                      <SelectItem value="insurance">Liability Insurance</SelectItem>
                      <SelectItem value="contractor_certificate">Contractor Certificate</SelectItem>
                      <SelectItem value="other">Other Compliance</SelectItem>
                    </SelectContent>
                  </Select>
                </div>

                <div className="space-y-1">
                  <Label htmlFor="doc-holder" className="text-xs font-semibold">
                    Holder Name
                  </Label>
                  <Input
                    id="doc-holder"
                    required
                    placeholder="e.g. Marcus Vance"
                    value={newDocHolder}
                    onChange={e => setNewDocHolder(e.target.value)}
                    className="text-xs"
                  />
                </div>
              </div>

              <div className="space-y-1">
                <Label htmlFor="doc-expires" className="text-xs font-semibold">
                  Expiration Date
                </Label>
                <Input
                  id="doc-expires"
                  type="date"
                  required
                  value={newDocExpires}
                  onChange={e => setNewDocExpires(e.target.value)}
                  className="text-xs"
                />
              </div>

              <DialogFooter className="pt-2">
                <Button type="button" variant="outline" size="sm" onClick={() => setIsAttachingDoc(false)}>
                  Cancel
                </Button>
                <Button type="submit" size="sm" className="font-semibold">
                  Save & Verify Document
                </Button>
              </DialogFooter>
            </form>
          </DialogContent>
        </Dialog>
      </div>
    </DashboardLayout>
  );
}
