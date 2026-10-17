import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { ClientFormattedDate } from '@/components/client-formatted-date';
import { useToast } from '@/hooks/use-toast';
import {
  AlertOctagon,
  AlertTriangle,
  Building2,
  CheckCircle2,
  Clock,
  EyeOff,
  Flame,
  HeartPulse,
  Info,
  Lock,
  MapPin,
  MessageSquare,
  Plus,
  Radio,
  Send,
  Shield,
  ShieldAlert,
  ShieldCheck,
  Siren,
  Sparkles,
  Users,
  Wind,
  Zap,
} from 'lucide-react';
import { cn } from '@/lib/utils';

export interface IncidentUpdate {
  id: number;
  message: string;
  statusAfter: string;
  isInternal: boolean;
  broadcastSent: boolean;
  createdAt: string;
  author: {
    name: string;
    role?: string | null;
  };
}

export interface Incident {
  id: number;
  incidentNumber: string;
  title: string;
  category: string;
  severity: 'low' | 'medium' | 'high' | 'critical';
  status: 'reported' | 'investigating' | 'active' | 'contained' | 'all_clear' | 'closed';
  location: string;
  summary: string;
  sensitiveDetails?: string | null;
  broadcastSent: boolean;
  createdAt: string;
  updatedAt: string;
  allClearAt?: string | null;
  allClearMessage?: string | null;
  allClearBy?: string | null;
  reporter: {
    name: string;
    role?: string | null;
  };
  updates: IncidentUpdate[];
}

interface Props {
  incidents: Incident[];
  stats: {
    total: number;
    active: number;
    critical: number;
    allClear: number;
  };
  can: {
    manage: boolean;
    report: boolean;
    viewSensitiveDetails: boolean;
  };
  categories: { value: string; label: string }[];
  severities: { value: string; label: string }[];
}

const CATEGORY_ICONS: Record<string, React.ElementType> = {
  security: ShieldAlert,
  fire: Flame,
  medical: HeartPulse,
  utility_outage: Zap,
  severe_weather: Wind,
  infrastructure: Building2,
  general: Info,
};

export default function IncidentsPage({ incidents, stats, can, categories, severities }: Props) {
  const { toast } = useToast();
  const [filterCategory, setFilterCategory] = useState<string>('all');
  const [filterStatus, setFilterStatus] = useState<'all' | 'active' | 'all_clear' | 'closed'>('all');
  const [searchQuery, setSearchQuery] = useState('');

  // Report Incident Dialog state
  const [reportOpen, setReportOpen] = useState(false);
  const [reporting, setReporting] = useState(false);
  const [newTitle, setNewTitle] = useState('');
  const [newCategory, setNewCategory] = useState('security');
  const [newSeverity, setNewSeverity] = useState('medium');
  const [newLocation, setNewLocation] = useState('');
  const [newSummary, setNewSummary] = useState('');
  const [newSensitiveDetails, setNewSensitiveDetails] = useState('');
  const [newBroadcastNotice, setNewBroadcastNotice] = useState(false);

  // Add Update Dialog state
  const [updateOpen, setUpdateOpen] = useState(false);
  const [updating, setUpdating] = useState(false);
  const [selectedIncidentForUpdate, setSelectedIncidentForUpdate] = useState<Incident | null>(null);
  const [updateMessage, setUpdateMessage] = useState('');
  const [updateStatus, setUpdateStatus] = useState<string>('');
  const [updateIsInternal, setUpdateIsInternal] = useState(false);
  const [updateBroadcast, setUpdateBroadcast] = useState(false);

  // All-Clear Dialog state
  const [allClearOpen, setAllClearOpen] = useState(false);
  const [clearing, setClearing] = useState(false);
  const [selectedIncidentForClear, setSelectedIncidentForClear] = useState<Incident | null>(null);
  const [allClearMessage, setAllClearMessage] = useState('');
  const [allClearBroadcast, setAllClearBroadcast] = useState(true);

  // Filtered incidents
  const filteredIncidents = incidents.filter((incident) => {
    if (filterCategory !== 'all' && incident.category !== filterCategory) return false;
    if (filterStatus === 'active' && (incident.status === 'all_clear' || incident.status === 'closed')) return false;
    if (filterStatus === 'all_clear' && incident.status !== 'all_clear') return false;
    if (filterStatus === 'closed' && incident.status !== 'closed') return false;
    if (searchQuery.trim()) {
      const q = searchQuery.toLowerCase();
      const matchNum = incident.incidentNumber.toLowerCase().includes(q);
      const matchTitle = incident.title.toLowerCase().includes(q);
      const matchLoc = incident.location.toLowerCase().includes(q);
      const matchSumm = incident.summary.toLowerCase().includes(q);
      if (!matchNum && !matchTitle && !matchLoc && !matchSumm) return false;
    }
    return true;
  });

  const handleReportSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!newTitle.trim() || !newLocation.trim() || !newSummary.trim()) {
      toast({ variant: 'destructive', title: 'Missing fields', description: 'Please fill in Title, Location, and Public Summary.' });
      return;
    }

    setReporting(true);
    router.post(
      '/dashboard/incidents',
      {
        title: newTitle,
        category: newCategory,
        severity: newSeverity,
        location: newLocation,
        summary: newSummary,
        sensitive_details: newSensitiveDetails.trim() || null,
        broadcast_urgent_notice: newBroadcastNotice,
      },
      {
        preserveScroll: true,
        onSuccess: () => {
          setReporting(false);
          setReportOpen(false);
          setNewTitle('');
          setNewLocation('');
          setNewSummary('');
          setNewSensitiveDetails('');
          setNewBroadcastNotice(false);
          toast({ title: 'Incident logged', description: 'Official incident reported successfully.' });
        },
        onError: (errs) => {
          setReporting(false);
          toast({ variant: 'destructive', title: 'Error logging incident', description: Object.values(errs)[0] as string });
        },
      }
    );
  };

  const handleUpdateSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedIncidentForUpdate || !updateMessage.trim()) return;

    setUpdating(true);
    router.post(
      `/dashboard/incidents/${selectedIncidentForUpdate.id}/updates`,
      {
        message: updateMessage,
        status: updateStatus || selectedIncidentForUpdate.status,
        is_internal: updateIsInternal,
        broadcast_update: updateBroadcast,
      },
      {
        preserveScroll: true,
        onSuccess: () => {
          setUpdating(false);
          setUpdateOpen(false);
          setUpdateMessage('');
          setSelectedIncidentForUpdate(null);
          toast({ title: 'Update recorded', description: 'Incident timeline update posted.' });
        },
        onError: (errs) => {
          setUpdating(false);
          toast({ variant: 'destructive', title: 'Error posting update', description: Object.values(errs)[0] as string });
        },
      }
    );
  };

  const handleAllClearSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedIncidentForClear || !allClearMessage.trim()) return;

    setClearing(true);
    router.post(
      `/dashboard/incidents/${selectedIncidentForClear.id}/all-clear`,
      {
        message: allClearMessage,
        broadcast_all_clear: allClearBroadcast,
      },
      {
        preserveScroll: true,
        onSuccess: () => {
          setClearing(false);
          setAllClearOpen(false);
          setAllClearMessage('');
          setSelectedIncidentForClear(null);
          toast({ title: 'All-Clear Issued', description: 'Incident status updated to All-Clear and community broadcast sent.' });
        },
        onError: (errs) => {
          setClearing(false);
          toast({ variant: 'destructive', title: 'Error issuing all-clear', description: Object.values(errs)[0] as string });
        },
      }
    );
  };

  const handleCloseIncident = (incidentId: number) => {
    if (!confirm('Are you sure you want to officially close this incident record?')) return;
    router.post(`/dashboard/incidents/${incidentId}/close`, {}, {
      preserveScroll: true,
      onSuccess: () => toast({ title: 'Incident closed', description: 'Incident record marked closed.' }),
    });
  };

  const getSeverityBadge = (severity: string) => {
    switch (severity) {
      case 'critical':
        return <Badge variant="destructive" className="font-bold uppercase tracking-wider animate-pulse">Critical Emergency</Badge>;
      case 'high':
        return <Badge className="bg-amber-500 hover:bg-amber-600 text-white font-semibold uppercase tracking-wider">High Priority</Badge>;
      case 'medium':
        return <Badge className="bg-blue-600 hover:bg-blue-700 text-white font-medium uppercase tracking-wider">Medium Advisory</Badge>;
      default:
        return <Badge variant="outline" className="text-muted-foreground uppercase tracking-wider">Low Advisory</Badge>;
    }
  };

  const getStatusBadge = (status: string) => {
    switch (status) {
      case 'all_clear':
        return (
          <Badge className="bg-emerald-600 hover:bg-emerald-700 text-white gap-1 font-bold">
            <CheckCircle2 className="w-3.5 h-3.5" /> All-Clear
          </Badge>
        );
      case 'active':
        return (
          <Badge variant="destructive" className="gap-1 font-semibold">
            <AlertOctagon className="w-3.5 h-3.5" /> Active Operation
          </Badge>
        );
      case 'investigating':
        return (
          <Badge className="bg-amber-600 hover:bg-amber-700 text-white gap-1 font-semibold">
            <Clock className="w-3.5 h-3.5" /> Investigating
          </Badge>
        );
      case 'contained':
        return (
          <Badge className="bg-indigo-600 hover:bg-indigo-700 text-white gap-1 font-semibold">
            <ShieldCheck className="w-3.5 h-3.5" /> Contained
          </Badge>
        );
      case 'closed':
        return <Badge variant="outline" className="text-muted-foreground">Closed</Badge>;
      default:
        return <Badge variant="secondary">{status}</Badge>;
    }
  };

  return (
    <DashboardLayout>
      <Head title="Incidents & Emergency Communication" />

      <div className="space-y-6">
        {/* Header & Command Bar */}
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div>
            <div className="flex items-center gap-2.5">
              <span className="p-2.5 rounded-xl bg-rose-500/10 text-rose-600 dark:text-rose-400">
                <ShieldAlert className="h-6 w-6" />
              </span>
              <div>
                <h1 className="text-2xl sm:text-3xl font-bold tracking-tight text-foreground flex items-center gap-2">
                  <span>Incident Command &amp; Emergency Hub</span>
                  {can.manage && (
                    <Badge variant="outline" className="border-rose-500/30 bg-rose-500/10 text-rose-600 text-xs font-semibold">
                      Security &amp; Command
                    </Badge>
                  )}
                </h1>
                <p className="text-sm text-muted-foreground">
                  Official community incident reporting, urgent notifications, timeline updates, and all-clear broadcasts.
                </p>
              </div>
            </div>
          </div>

          <div className="flex items-center gap-2">
            {can.report && (
              <Dialog open={reportOpen} onOpenChange={setReportOpen}>
                <DialogTrigger asChild>
                  <Button className="gap-2 bg-rose-600 hover:bg-rose-700 text-white font-bold shadow-md shadow-rose-600/20">
                    <Plus className="h-4 w-4" />
                    <span>Report Incident</span>
                  </Button>
                </DialogTrigger>
                <DialogContent className="sm:max-w-[620px] max-h-[90vh] overflow-y-auto">
                  <form onSubmit={handleReportSubmit}>
                    <DialogHeader>
                      <div className="flex items-center gap-2 mb-1">
                        <span className="p-2 rounded-xl bg-rose-500/10 text-rose-600">
                          <ShieldAlert className="h-5 w-5" />
                        </span>
                        <div>
                          <DialogTitle className="text-lg font-bold">Report Community Incident</DialogTitle>
                          <DialogDescription className="text-xs">
                            Log an official incident with public instructions and confidential security notes.
                          </DialogDescription>
                        </div>
                      </div>
                    </DialogHeader>

                    <div className="space-y-4 py-3 text-xs">
                      {/* Title */}
                      <div className="space-y-1.5">
                        <Label htmlFor="inc-title" className="font-semibold">
                          Incident Title <span className="text-destructive">*</span>
                        </Label>
                        <Input
                          id="inc-title"
                          placeholder="e.g. Water Main Rupture on Hibiscus Way, Perimeter Sensor Breach Gate 2"
                          value={newTitle}
                          onChange={(e) => setNewTitle(e.target.value)}
                          required
                          className="h-9 text-xs"
                        />
                      </div>

                      {/* Category & Severity Grid */}
                      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div className="space-y-1.5">
                          <Label className="font-semibold">Category</Label>
                          <select
                            value={newCategory}
                            onChange={(e) => setNewCategory(e.target.value)}
                            className="w-full h-9 rounded-lg border border-input bg-background px-3 text-xs font-medium"
                          >
                            {categories.map((c) => (
                              <option key={c.value} value={c.value}>{c.label}</option>
                            ))}
                          </select>
                        </div>

                        <div className="space-y-1.5">
                          <Label className="font-semibold">Severity</Label>
                          <select
                            value={newSeverity}
                            onChange={(e) => setNewSeverity(e.target.value)}
                            className="w-full h-9 rounded-lg border border-input bg-background px-3 text-xs font-medium"
                          >
                            {severities.map((s) => (
                              <option key={s.value} value={s.value}>{s.label}</option>
                            ))}
                          </select>
                        </div>
                      </div>

                      {/* Location */}
                      <div className="space-y-1.5">
                        <Label htmlFor="inc-loc" className="font-semibold">
                          Location / Sector <span className="text-destructive">*</span>
                        </Label>
                        <Input
                          id="inc-loc"
                          placeholder="e.g. North Gatehouse, Sector 4 Boundary, Clubhouse Main Pool"
                          value={newLocation}
                          onChange={(e) => setNewLocation(e.target.value)}
                          required
                          className="h-9 text-xs"
                        />
                      </div>

                      {/* Public Summary */}
                      <div className="space-y-1.5">
                        <div className="flex items-center justify-between">
                          <Label htmlFor="inc-summary" className="font-semibold">
                            Public Summary &amp; Resident Guidance <span className="text-destructive">*</span>
                          </Label>
                          <span className="text-[10px] text-muted-foreground">Visible to all residents</span>
                        </div>
                        <Textarea
                          id="inc-summary"
                          placeholder="State the observed situation and immediate safety guidance for residents (e.g. Please avoid North Gate access lane; use East Gate while emergency repairs proceed)..."
                          value={newSummary}
                          onChange={(e) => setNewSummary(e.target.value)}
                          required
                          rows={3}
                          className="text-xs resize-none"
                        />
                      </div>

                      {/* SENSITIVE DETAILS (Confidential) */}
                      <div className="p-3 rounded-xl border border-amber-500/30 bg-amber-500/5 space-y-2">
                        <div className="flex items-center justify-between">
                          <Label htmlFor="inc-sensitive" className="font-bold flex items-center gap-1.5 text-amber-700 dark:text-amber-400">
                            <Lock className="w-3.5 h-3.5" />
                            <span>Confidential Security Details (Authorized Roles Only)</span>
                          </Label>
                          <Badge variant="outline" className="text-[10px] border-amber-500/40 text-amber-600 bg-amber-500/10">
                            Hidden From Residents
                          </Badge>
                        </div>
                        <p className="text-[11px] text-muted-foreground">
                          Enter caller identity, license plate numbers, dispatch logs, or confidential tactical notes. Residents will never receive or see this content.
                        </p>
                        <Textarea
                          id="inc-sensitive"
                          placeholder="Confidential dispatch notes, responding officer badge numbers, witness statements..."
                          value={newSensitiveDetails}
                          onChange={(e) => setNewSensitiveDetails(e.target.value)}
                          rows={2}
                          className="text-xs font-mono resize-none bg-background"
                        />
                      </div>

                      {/* Urgent Broadcast Toggle */}
                      <div className="p-3 rounded-xl border bg-muted/30 space-y-2">
                        <div className="flex items-center space-x-2">
                          <Checkbox
                            id="broadcast-check"
                            checked={newBroadcastNotice}
                            onCheckedChange={(c) => setNewBroadcastNotice(Boolean(c))}
                          />
                          <label htmlFor="broadcast-check" className="font-bold leading-none cursor-pointer flex items-center gap-1.5">
                            <Radio className="w-3.5 h-3.5 text-rose-500" />
                            <span>Dispatch Urgent Broadcast Notice to Community</span>
                          </label>
                        </div>
                        <p className="text-[11px] text-muted-foreground pl-6">
                          Instantly send an urgent notice across In-App notification centre, SMS, Email, and Push alerts to all residents.
                        </p>
                      </div>
                    </div>

                    <DialogFooter className="gap-2 sm:gap-0 pt-2 border-t">
                      <Button type="button" variant="outline" size="sm" onClick={() => setReportOpen(false)}>
                        Cancel
                      </Button>
                      <Button type="submit" size="sm" disabled={reporting} className="gap-2 bg-rose-600 hover:bg-rose-700 text-white font-bold">
                        <Send className="h-4 w-4" />
                        <span>{reporting ? 'Logging Incident...' : 'Log & Dispatch Incident'}</span>
                      </Button>
                    </DialogFooter>
                  </form>
                </DialogContent>
              </Dialog>
            )}
          </div>
        </div>

        {/* 4 Scorecards */}
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          <Card className="border shadow-xs">
            <CardHeader className="p-4 pb-2">
              <CardDescription className="text-xs font-medium">Total Incidents</CardDescription>
              <CardTitle className="text-2xl font-bold font-mono">{stats.total}</CardTitle>
            </CardHeader>
            <CardContent className="p-4 pt-0 text-[11px] text-muted-foreground flex items-center gap-1">
              <Info className="w-3 h-3 text-primary" /> Lifetime logged events
            </CardContent>
          </Card>

          <Card className="border shadow-xs border-rose-500/30 bg-rose-500/5">
            <CardHeader className="p-4 pb-2">
              <CardDescription className="text-xs font-medium text-rose-700 dark:text-rose-400">Active Incidents</CardDescription>
              <CardTitle className="text-2xl font-bold font-mono text-rose-700 dark:text-rose-300">{stats.active}</CardTitle>
            </CardHeader>
            <CardContent className="p-4 pt-0 text-[11px] text-rose-600 dark:text-rose-400 flex items-center gap-1">
              <AlertOctagon className="w-3 h-3 animate-pulse" /> Requiring monitoring
            </CardContent>
          </Card>

          <Card className="border shadow-xs border-amber-500/30 bg-amber-500/5">
            <CardHeader className="p-4 pb-2">
              <CardDescription className="text-xs font-medium text-amber-700 dark:text-amber-400">Critical / High Alerts</CardDescription>
              <CardTitle className="text-2xl font-bold font-mono text-amber-700 dark:text-amber-300">{stats.critical}</CardTitle>
            </CardHeader>
            <CardContent className="p-4 pt-0 text-[11px] text-amber-600 dark:text-amber-400 flex items-center gap-1">
              <AlertTriangle className="w-3 h-3" /> Elevated response
            </CardContent>
          </Card>

          <Card className="border shadow-xs border-emerald-500/30 bg-emerald-500/5">
            <CardHeader className="p-4 pb-2">
              <CardDescription className="text-xs font-medium text-emerald-700 dark:text-emerald-400">All-Clear Issued</CardDescription>
              <CardTitle className="text-2xl font-bold font-mono text-emerald-700 dark:text-emerald-300">{stats.allClear}</CardTitle>
            </CardHeader>
            <CardContent className="p-4 pt-0 text-[11px] text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
              <CheckCircle2 className="w-3 h-3" /> Successfully resolved
            </CardContent>
          </Card>
        </div>

        {/* Filters Bar */}
        <div className="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 p-3 rounded-xl border bg-card shadow-xs">
          <div className="flex items-center gap-2 flex-1">
            <Input
              placeholder="Search by incident number, title, sector, or keyword..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="h-9 text-xs max-w-sm"
            />
          </div>

          <div className="flex items-center gap-2 overflow-x-auto">
            <div className="flex items-center gap-1 bg-muted/60 p-1 rounded-lg">
              <Button
                type="button"
                variant={filterStatus === 'all' ? 'default' : 'ghost'}
                size="sm"
                onClick={() => setFilterStatus('all')}
                className="h-7 text-xs font-medium px-2.5"
              >
                All
              </Button>
              <Button
                type="button"
                variant={filterStatus === 'active' ? 'default' : 'ghost'}
                size="sm"
                onClick={() => setFilterStatus('active')}
                className="h-7 text-xs font-medium px-2.5 gap-1 text-rose-600 dark:text-rose-400"
              >
                <span className="w-1.5 h-1.5 rounded-full bg-rose-500 animate-pulse" />
                Active ({stats.active})
              </Button>
              <Button
                type="button"
                variant={filterStatus === 'all_clear' ? 'default' : 'ghost'}
                size="sm"
                onClick={() => setFilterStatus('all_clear')}
                className="h-7 text-xs font-medium px-2.5 gap-1 text-emerald-600 dark:text-emerald-400"
              >
                All-Clear
              </Button>
              <Button
                type="button"
                variant={filterStatus === 'closed' ? 'default' : 'ghost'}
                size="sm"
                onClick={() => setFilterStatus('closed')}
                className="h-7 text-xs font-medium px-2.5"
              >
                Closed
              </Button>
            </div>

            <select
              value={filterCategory}
              onChange={(e) => setFilterCategory(e.target.value)}
              className="h-8 rounded-lg border border-input bg-background px-2.5 text-xs font-medium"
            >
              <option value="all">All Categories</option>
              {categories.map((c) => (
                <option key={c.value} value={c.value}>{c.label}</option>
              ))}
            </select>
          </div>
        </div>

        {/* Incidents Feed / List */}
        {filteredIncidents.length === 0 ? (
          <Card className="border shadow-xs text-center p-8">
            <div className="mx-auto w-12 h-12 rounded-full bg-muted flex items-center justify-center text-muted-foreground mb-3">
              <ShieldCheck className="w-6 h-6 text-emerald-500" />
            </div>
            <h3 className="text-base font-bold text-foreground">No Incidents Found</h3>
            <p className="text-xs text-muted-foreground max-w-md mx-auto mt-1">
              There are no matching incidents in this view. The community operations and perimeter are currently operating under normal status.
            </p>
          </Card>
        ) : (
          <div className="space-y-4">
            {filteredIncidents.map((incident) => {
              const CategoryIcon = CATEGORY_ICONS[incident.category] || Info;
              const isResolved = incident.status === 'all_clear' || incident.status === 'closed';

              return (
                <Card
                  key={incident.id}
                  className={cn(
                    'border shadow-xs transition-all overflow-hidden',
                    incident.severity === 'critical' && !isResolved && 'border-rose-500/50 ring-1 ring-rose-500/20',
                    incident.status === 'all_clear' && 'border-emerald-500/40 bg-emerald-500/[0.02]'
                  )}
                >
                  <CardHeader className="p-4 sm:p-5 border-b bg-muted/10">
                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                      <div className="space-y-1">
                        <div className="flex flex-wrap items-center gap-2">
                          <span className="font-mono text-xs font-bold px-2 py-0.5 rounded bg-muted text-foreground">
                            {incident.incidentNumber}
                          </span>
                          {getSeverityBadge(incident.severity)}
                          {getStatusBadge(incident.status)}
                          <Badge variant="outline" className="gap-1 text-[11px] capitalize font-medium">
                            <CategoryIcon className="w-3 h-3 text-primary" />
                            <span>{incident.category.replace('_', ' ')}</span>
                          </Badge>
                          {incident.broadcastSent && (
                            <Badge variant="secondary" className="gap-1 text-[10px] text-rose-600 bg-rose-500/10">
                              <Radio className="w-3 h-3" /> Notice Dispatched
                            </Badge>
                          )}
                        </div>
                        <h2 className="text-lg font-bold text-foreground tracking-tight pt-1">
                          {incident.title}
                        </h2>
                      </div>

                      <div className="flex items-center gap-2 self-start sm:self-center shrink-0">
                        {can.manage && !isResolved && (
                          <>
                            <Button
                              type="button"
                              size="sm"
                              variant="outline"
                              onClick={() => {
                                setSelectedIncidentForUpdate(incident);
                                setUpdateStatus(incident.status);
                                setUpdateOpen(true);
                              }}
                              className="h-8 text-xs gap-1.5"
                            >
                              <MessageSquare className="w-3.5 h-3.5" />
                              <span>Add Update</span>
                            </Button>

                            <Button
                              type="button"
                              size="sm"
                              onClick={() => {
                                setSelectedIncidentForClear(incident);
                                setAllClearMessage(`The incident at ${incident.location} has been addressed and normal access is safely restored.`);
                                setAllClearOpen(true);
                              }}
                              className="h-8 text-xs gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold"
                            >
                              <CheckCircle2 className="w-3.5 h-3.5" />
                              <span>Issue All-Clear</span>
                            </Button>
                          </>
                        )}

                        {can.manage && incident.status === 'all_clear' && (
                          <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            onClick={() => handleCloseIncident(incident.id)}
                            className="h-8 text-xs text-muted-foreground hover:text-foreground"
                          >
                            Close Record
                          </Button>
                        )}
                      </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground pt-2">
                      <span className="flex items-center gap-1 font-medium text-foreground">
                        <MapPin className="w-3.5 h-3.5 text-rose-500" />
                        <span>{incident.location}</span>
                      </span>
                      <span className="flex items-center gap-1">
                        <Clock className="w-3.5 h-3.5" />
                        <span>Reported <ClientFormattedDate date={incident.createdAt} formatString="MMM d, yyyy h:mm a" /></span>
                      </span>
                      <span>by {incident.reporter.name}</span>
                    </div>
                  </CardHeader>

                  <CardContent className="p-4 sm:p-5 space-y-4">
                    {/* Public Summary */}
                    <div className="text-xs sm:text-sm text-foreground/90 leading-relaxed bg-card p-3 rounded-xl border">
                      <div className="font-semibold text-xs text-muted-foreground uppercase tracking-wider mb-1 flex items-center gap-1">
                        <Info className="w-3.5 h-3.5 text-primary" /> Public Status &amp; Guidance
                      </div>
                      <p>{incident.summary}</p>
                    </div>

                    {/* All-Clear Message Banner (if issued) */}
                    {incident.allClearAt && (
                      <div className="p-3.5 rounded-xl border border-emerald-500/40 bg-emerald-500/10 text-emerald-900 dark:text-emerald-200 space-y-1">
                        <div className="flex items-center justify-between text-xs font-bold">
                          <span className="flex items-center gap-1.5 text-emerald-700 dark:text-emerald-300">
                            <CheckCircle2 className="w-4 h-4 text-emerald-600" />
                            <span>OFFICIAL ALL-CLEAR DIRECTIVE</span>
                          </span>
                          <span className="text-[11px] font-normal opacity-80">
                            Issued <ClientFormattedDate date={incident.allClearAt} formatString="MMM d, yyyy h:mm a" /> {incident.allClearBy && `by ${incident.allClearBy}`}
                          </span>
                        </div>
                        <p className="text-xs font-medium leading-relaxed pl-5">
                          {incident.allClearMessage || 'Situation resolved. All operations and perimeter protocols have returned to standard state.'}
                        </p>
                      </div>
                    )}

                    {/* SENSITIVE DETAILS (Confidential, Authorized Roles Only) */}
                    {can.viewSensitiveDetails && incident.sensitiveDetails && (
                      <div className="p-3.5 rounded-xl border border-amber-500/40 bg-amber-500/10 text-amber-900 dark:text-amber-200 space-y-1.5">
                        <div className="flex items-center justify-between text-xs font-bold">
                          <span className="flex items-center gap-1.5 text-amber-800 dark:text-amber-300">
                            <Lock className="w-3.5 h-3.5" />
                            <span>CONFIDENTIAL INCIDENT LOG (AUTHORIZED SECURITY &amp; ADMINS ONLY)</span>
                          </span>
                          <Badge variant="outline" className="text-[10px] border-amber-600/40 text-amber-700 dark:text-amber-300">
                            Restricted Briefing
                          </Badge>
                        </div>
                        <p className="text-xs font-mono whitespace-pre-wrap leading-relaxed pl-5 bg-background/50 p-2.5 rounded-lg border border-amber-500/20">
                          {incident.sensitiveDetails}
                        </p>
                      </div>
                    )}

                    {/* Timeline of Updates */}
                    {incident.updates && incident.updates.length > 0 && (
                      <div className="space-y-2 pt-1">
                        <div className="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center justify-between">
                          <span>Incident Timeline Updates ({incident.updates.length})</span>
                          <span className="text-[10px] font-normal">Chronological record</span>
                        </div>

                        <div className="space-y-2 pl-2 border-l-2 border-muted">
                          {incident.updates.map((update) => (
                            <div
                              key={update.id}
                              className={cn(
                                'text-xs p-2.5 rounded-lg border space-y-1',
                                update.isInternal
                                  ? 'border-amber-500/30 bg-amber-500/5'
                                  : 'border-border bg-card'
                              )}
                            >
                              <div className="flex items-center justify-between text-[11px] text-muted-foreground">
                                <div className="flex items-center gap-1.5">
                                  <span className="font-semibold text-foreground">{update.author.name}</span>
                                  {update.isInternal ? (
                                    <Badge variant="outline" className="text-[10px] py-0 border-amber-500/40 text-amber-600 bg-amber-500/10 gap-1">
                                      <Lock className="w-2.5 h-2.5" /> Internal Note
                                    </Badge>
                                  ) : (
                                    <Badge variant="secondary" className="text-[10px] py-0">Public Notice</Badge>
                                  )}
                                  {update.broadcastSent && (
                                    <Badge variant="outline" className="text-[10px] py-0 text-rose-600 border-rose-500/30">
                                      Broadcasted
                                    </Badge>
                                  )}
                                </div>
                                <span className="text-[10px] font-mono">
                                  <ClientFormattedDate date={update.createdAt} formatString="MMM d, yyyy h:mm a" />
                                </span>
                              </div>
                              <p className="text-xs leading-relaxed text-foreground">{update.message}</p>
                            </div>
                          ))}
                        </div>
                      </div>
                    )}
                  </CardContent>
                </Card>
              );
            })}
          </div>
        )}
      </div>

      {/* Add Update Modal */}
      {selectedIncidentForUpdate && (
        <Dialog open={updateOpen} onOpenChange={setUpdateOpen}>
          <DialogContent className="sm:max-w-[500px]">
            <form onSubmit={handleUpdateSubmit}>
              <DialogHeader>
                <DialogTitle className="text-base font-bold flex items-center gap-2">
                  <MessageSquare className="w-4 h-4 text-primary" />
                  <span>Add Timeline Update: {selectedIncidentForUpdate.incidentNumber}</span>
                </DialogTitle>
                <DialogDescription className="text-xs">
                  Record response updates, status changes, or broadcast new advisories to residents.
                </DialogDescription>
              </DialogHeader>

              <div className="space-y-3 py-3 text-xs">
                <div className="space-y-1">
                  <Label className="font-semibold">Update Status</Label>
                  <select
                    value={updateStatus}
                    onChange={(e) => setUpdateStatus(e.target.value)}
                    className="w-full h-8 rounded-lg border border-input bg-background px-3 text-xs font-medium"
                  >
                    <option value="investigating">Investigating</option>
                    <option value="active">Active Operation</option>
                    <option value="contained">Contained</option>
                    <option value="all_clear">All-Clear</option>
                    <option value="closed">Closed</option>
                  </select>
                </div>

                <div className="space-y-1">
                  <Label htmlFor="update-msg" className="font-semibold">Update Message Content *</Label>
                  <Textarea
                    id="update-msg"
                    placeholder="Enter latest developments or instructions..."
                    value={updateMessage}
                    onChange={(e) => setUpdateMessage(e.target.value)}
                    required
                    rows={3}
                    className="text-xs resize-none"
                  />
                </div>

                <div className="p-2.5 rounded-lg border bg-muted/20 space-y-2">
                  <div className="flex items-center space-x-2">
                    <Checkbox
                      id="internal-check"
                      checked={updateIsInternal}
                      onCheckedChange={(c) => {
                        setUpdateIsInternal(Boolean(c));
                        if (c) setUpdateBroadcast(false);
                      }}
                    />
                    <label htmlFor="internal-check" className="font-semibold cursor-pointer flex items-center gap-1 text-amber-700 dark:text-amber-400">
                      <Lock className="w-3 h-3" />
                      <span>Internal Security Note (Confidential, Hidden from Residents)</span>
                    </label>
                  </div>

                  {!updateIsInternal && (
                    <div className="flex items-center space-x-2 pt-1 border-t">
                      <Checkbox
                        id="update-broadcast-check"
                        checked={updateBroadcast}
                        onCheckedChange={(c) => setUpdateBroadcast(Boolean(c))}
                      />
                      <label htmlFor="update-broadcast-check" className="font-semibold cursor-pointer flex items-center gap-1 text-rose-600 dark:text-rose-400">
                        <Radio className="w-3 h-3" />
                        <span>Broadcast This Update as an Urgent Notice</span>
                      </label>
                    </div>
                  )}
                </div>
              </div>

              <DialogFooter className="gap-2 sm:gap-0 pt-2 border-t">
                <Button type="button" variant="outline" size="sm" onClick={() => setUpdateOpen(false)}>
                  Cancel
                </Button>
                <Button type="submit" size="sm" disabled={updating} className="font-bold">
                  {updating ? 'Posting...' : 'Post Update'}
                </Button>
              </DialogFooter>
            </form>
          </DialogContent>
        </Dialog>
      )}

      {/* Issue All-Clear Modal */}
      {selectedIncidentForClear && (
        <Dialog open={allClearOpen} onOpenChange={setAllClearOpen}>
          <DialogContent className="sm:max-w-[500px]">
            <form onSubmit={handleAllClearSubmit}>
              <DialogHeader>
                <DialogTitle className="text-base font-bold flex items-center gap-2 text-emerald-600 dark:text-emerald-400">
                  <CheckCircle2 className="w-5 h-5 text-emerald-600" />
                  <span>Issue Official All-Clear: {selectedIncidentForClear.incidentNumber}</span>
                </DialogTitle>
                <DialogDescription className="text-xs">
                  Confirm that the hazard has been eliminated or perimeter secured, and broadcast the all-clear notice.
                </DialogDescription>
              </DialogHeader>

              <div className="space-y-3 py-3 text-xs">
                <div className="space-y-1">
                  <Label htmlFor="allclear-msg" className="font-semibold">
                    All-Clear Message to Residents *
                  </Label>
                  <Textarea
                    id="allclear-msg"
                    placeholder="e.g. Area safe and verified. Water main repair complete. Normal gate operations resumed."
                    value={allClearMessage}
                    onChange={(e) => setAllClearMessage(e.target.value)}
                    required
                    rows={3}
                    className="text-xs resize-none"
                  />
                </div>

                <div className="p-2.5 rounded-lg border bg-emerald-500/10 border-emerald-500/30">
                  <div className="flex items-center space-x-2">
                    <Checkbox
                      id="allclear-broadcast"
                      checked={allClearBroadcast}
                      onCheckedChange={(c) => setAllClearBroadcast(Boolean(c))}
                    />
                    <label htmlFor="allclear-broadcast" className="font-bold text-emerald-800 dark:text-emerald-300 cursor-pointer flex items-center gap-1.5">
                      <Radio className="w-3.5 h-3.5 text-emerald-600" />
                      <span>Broadcast All-Clear Across All Communication Channels</span>
                    </label>
                  </div>
                </div>
              </div>

              <DialogFooter className="gap-2 sm:gap-0 pt-2 border-t">
                <Button type="button" variant="outline" size="sm" onClick={() => setAllClearOpen(false)}>
                  Cancel
                </Button>
                <Button type="submit" size="sm" disabled={clearing} className="bg-emerald-600 hover:bg-emerald-700 text-white font-bold">
                  {clearing ? 'Issuing All-Clear...' : 'Confirm & Issue All-Clear'}
                </Button>
              </DialogFooter>
            </form>
          </DialogContent>
        </Dialog>
      )}
    </DashboardLayout>
  );
}
