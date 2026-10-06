import { useState, useMemo, useEffect } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import {
  Users,
  UserCheck,
  Shield,
  HardHat,
  HeartHandshake,
  User,
  Search,
  Download,
  RefreshCw,
  Home,
  Clock,
  Car,
  Phone,
  Filter,
  CheckCircle2,
  ArrowRight,
  Sparkles,
  MapPin,
  Building,
  Flame,
  FileSpreadsheet,
  AlertTriangle,
  Siren,
  Wind,
  Waves,
  Activity,
  Copy,
  Printer,
  Check,
  HelpCircle,
  LogOut,
  Radio,
  FileText,
  Map as MapIcon,
  Network,
  ChevronRight,
  KeyRound,
  ExternalLink,
  Layers,
  Calendar,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useToast } from '@/hooks/use-toast';
import { EmergencyBroadcastDialog } from '@/components/dashboard/emergency-broadcast-dialog';

export type OccupancySummary = {
  residents: number;
  long_term_guests: number;
  short_term_guests?: number;
  visitors: number;
  staff: number;
  contractors: number;
  legacy_contacts: number;
  total: number;
};

export type UnitSummaryItem = {
  unit: string;
  total: number;
  currentlyInside?: number;
  expectedToday?: number;
  residents: number;
  long_term_guests: number;
  short_term_guests?: number;
  visitors: number;
  staff: number;
  contractors: number;
  legacy_contacts: number;
};

export type Occupant = {
  id: string;
  type: string;
  passId: string;
  name: string;
  categoryKey: string;
  categoryLabel: string;
  role: string;
  property: string;
  unit: string;
  host: string | null;
  authorizedBy?: string | null;
  accessType?: string;
  qrStatus?: string;
  checkedInAt: string | null;
  checkedInTime: string;
  checkedInDuration: string;
  gate: string;
  vehicle: string | null;
  contact: string | null;
  avatarUrl: string | null;
  status: string;
  checkInStatus?: 'INSIDE' | 'EXPECTED' | 'OUT';
  expiration?: string;
  relationshipChain?: {
    person: string;
    property: string;
    relationship: string;
    authorizedBy: string;
    accessType: string;
    visibilityScope: string;
    can: string[];
    cannot: string[];
    summary: string;
  };
};

export type MusterRollCallItem = {
  id: number;
  occupant_id: string;
  name: string;
  pass_id: string | null;
  unit: string | null;
  category: string;
  status: 'safe' | 'missing' | 'evacuated' | 'needs_assistance' | 'checked_out' | 'unverified';
  status_label: string;
  contact: string | null;
  vehicle: string | null;
  notes: string | null;
  marked_at: string | null;
  marked_by_name: string | null;
};

export type EnclosureZone = {
  id: string;
  name: string;
  type: string;
  description: string;
  icon: string;
  status: string;
  badge: string;
  occupant_count: number;
};

export type ActiveMusterData = {
  id: number;
  incident_type: 'fire' | 'hurricane' | 'earthquake' | 'flood' | 'security_incident' | 'drill' | 'other';
  incident_label: string;
  title: string;
  assembly_point: string;
  notes: string | null;
  started_at: string;
  started_time: string;
  initiator_name: string;
  counts: {
    total: number;
    safe: number;
    missing: number;
    evacuated: number;
    needs_assistance: number;
    checked_out: number;
    unverified: number;
  };
  roll_calls: MusterRollCallItem[];
};

export type CommunityPropertyNode = {
  id: string;
  unit: string;
  lot: string;
  address: string;
  insideCount: number;
  expectedCount: number;
  categoriesInside: Record<string, number>;
  categoriesExpected: Record<string, number>;
  breakdown: Array<{
    key: string;
    label: string;
    inside: number;
    expected: number;
  }>;
  occupants: Array<{
    name: string;
    category: string;
    status: string;
  }>;
};

export type CommunityHierarchyData = {
  communityName: string;
  isScoped: boolean;
  scopedUnit?: string | null;
  totalInside: number;
  totalExpected: number;
  properties: CommunityPropertyNode[];
};

type Props = {
  summary: OccupancySummary;
  expectedSummary?: OccupancySummary | null;
  unitSummary?: OccupancySummary | null;
  unitExpectedSummary?: OccupancySummary | null;
  units: UnitSummaryItem[];
  selectedUnit?: string | null;
  selectedCategory?: string | null;
  search?: string | null;
  activeTab?: string;
  communityHierarchy?: CommunityHierarchyData | null;
  occupants: {
    data: Occupant[];
    total: number;
    current_page: number;
    per_page: number;
    last_page: number;
  };
  canManage: boolean;
  userUnit?: string | null;
  activeMuster?: ActiveMusterData | null;
  enclosureZones?: EnclosureZone[];
  lastUpdated: string;
};

export default function OccupancyPage({
  summary,
  expectedSummary,
  unitSummary,
  unitExpectedSummary,
  units,
  selectedUnit: initialUnit,
  selectedCategory: initialCategory = 'all',
  search: initialSearch = '',
  activeTab: initialTab = 'inside',
  communityHierarchy,
  occupants,
  canManage,
  userUnit,
  activeMuster,
  enclosureZones,
  lastUpdated,
}: Props) {
  const { toast } = useToast();
  const [unitFilter, setUnitFilter] = useState(initialUnit || '');
  const [activeCategory, setActiveCategory] = useState(initialCategory || 'all');
  const [searchQuery, setSearchQuery] = useState(initialSearch || '');
  const [activeViewMode, setActiveViewMode] = useState<'inside' | 'expected' | 'map'>(
    initialTab === 'expected' ? 'expected' : initialTab === 'map' ? 'map' : 'inside'
  );
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [copiedLedger, setCopiedLedger] = useState(false);

  // Muster Mode & Dialogs
  const [musterFilter, setMusterFilter] = useState<'all' | 'missing' | 'needs_assistance' | 'evacuated' | 'safe' | 'checked_out' | 'unverified'>('all');
  const [startMusterOpen, setStartMusterOpen] = useState(false);
  const [musterIncidentType, setMusterIncidentType] = useState<
    'fire' | 'hurricane' | 'earthquake' | 'flood' | 'security_incident' | 'drill' | 'other'
  >('fire');
  const [musterTitle, setMusterTitle] = useState('');
  const [musterAssemblyPoint, setMusterAssemblyPoint] = useState('Central Park East Muster Field (Gate 01 Ingress)');
  const [musterNotes, setMusterNotes] = useState('');
  const [submittingMuster, setSubmittingMuster] = useState(false);

  // Status Note Dialog
  const [noteDialogOpen, setNoteDialogOpen] = useState(false);
  const [selectedRollCall, setSelectedRollCall] = useState<MusterRollCallItem | null>(null);
  const [targetStatus, setTargetStatus] = useState<string>('');
  const [occupantNote, setOccupantNote] = useState('');

  // Unit Drill-Down Modal: "Who's inside Unit 14?"
  const [drillDownModalOpen, setDrillDownModalOpen] = useState(false);
  const [drillDownUnit, setDrillDownUnit] = useState<string>('');
  const [drillDownLoading, setDrillDownLoading] = useState(false);
  const [drillDownData, setDrillDownData] = useState<any | null>(null);
  const [drillDownError, setDrillDownError] = useState<string | null>(null);

  // Deep Access Relationship Inspector Modal
  const [selectedPerson, setSelectedPerson] = useState<Occupant | null>(null);

  // Map Selected Property & Interactive Topology Tree
  const [selectedMapProperty, setSelectedMapProperty] = useState<CommunityPropertyNode | null>(null);
  const [selectedTreeUnit, setSelectedTreeUnit] = useState<string>('');
  const [compareTreeUnit, setCompareTreeUnit] = useState<string>('');
  const [copiedTree, setCopiedTree] = useState(false);

  // Community map, from the real hierarchy only. It used to fall back to an
  // invented Unit 14 ("5 inside": John Smith, Michael Brown, ...) and Unit 15
  // whenever the real units were missing, which in an emergency reads as fact.
  const propertyList: any[] = communityHierarchy?.properties ?? [];
  const asciiTree = useMemo(() => {
    const findUnit = (unit: string) => propertyList.find((p: any) => p.unit?.toLowerCase() === unit.toLowerCase());
    const p1 = (selectedTreeUnit && findUnit(selectedTreeUnit)) || propertyList[0];
    const p2 = (compareTreeUnit && findUnit(compareTreeUnit)) || propertyList.find((p: any) => p !== p1);

    if (!p1) {
      return 'Community Map\n\nNo occupancy data is available right now.';
    }

    const name = (p: any) => (p ? String(p.unit).toUpperCase() : '—');
    const inside = (p: any) => (p ? `${p.insideCount ?? 0} inside` : '');
    const branches = (p1.breakdown ?? []).filter((b: any) => b.inside > 0);
    const branchLines = branches.length > 0
      ? branches.map((item: any, idx: number) => {
          const symbol = idx === branches.length - 1 ? '└─ ' : '├─ ';
          return `       ${symbol}${item.branchLabel || `${item.inside} ${item.shortLabel || item.label}`}`;
        })
      : ['       └─ No one recorded inside'];

    return [
      `Community Map`,
      ``,
      `             COMMUNITY`,
      `                 │`,
      `       ┌─────────┴─────────┐`,
      `       │                   │`,
      `    ${name(p1).padEnd(7)}              ${name(p2)}`,
      `       │                   │`,
      `   ${inside(p1).padEnd(8)}              ${inside(p2)}`,
      `       │`,
      ...branchLines,
    ].join('\n');
  }, [propertyList, selectedTreeUnit, compareTreeUnit]);

  const handleFilterApply = (newUnit?: string | null, newCat?: string, newSearch?: string, newTab?: string) => {
    const unitParam = newUnit !== undefined ? (newUnit || undefined) : (unitFilter || undefined);
    const catParam = newCat !== undefined ? (newCat === 'all' ? undefined : newCat) : (activeCategory === 'all' ? undefined : activeCategory);
    const searchParam = newSearch !== undefined ? (newSearch || undefined) : (searchQuery || undefined);
    const tabParam = newTab !== undefined ? newTab : activeViewMode;

    router.get(
      '/dashboard/occupancy',
      {
        unit: unitParam,
        category: catParam,
        search: searchParam,
        tab: tabParam,
      },
      {
        preserveScroll: true,
        preserveState: true,
      }
    );
  };

  const handleSelectUnit = (unit: string | null) => {
    setUnitFilter(unit || '');
    handleFilterApply(unit, activeCategory, searchQuery, activeViewMode);
  };

  const handleOpenUnitModal = async (unitName: string) => {
    setDrillDownUnit(unitName);
    setDrillDownModalOpen(true);
    setDrillDownLoading(true);
    setDrillDownError(null);
    try {
      const res = await fetch(`/dashboard/occupancy/unit/${encodeURIComponent(unitName)}`);
      if (res.ok) {
        const data = await res.json();
        setDrillDownData(data);
      } else {
        // Say it failed. This used to show an invented roster ("5 inside":
        // John Smith, Mary Smith, ABC Plumbing...) on any error, a 403 included.
        setDrillDownData(null);
        setDrillDownError(
          res.status === 403
            ? 'You can only see who is at your own unit.'
            : 'Could not load this unit right now. Try again, or check the gate log directly.',
        );
      }
    } catch (err) {
      console.error('Error fetching unit drilldown:', err);
      setDrillDownData(null);
      setDrillDownError('Could not load this unit right now. Try again, or check the gate log directly.');
    } finally {
      setDrillDownLoading(false);
    }
  };

  const handleCategoryChange = (cat: string) => {
    setActiveCategory(cat);
    handleFilterApply(unitFilter, cat, searchQuery, activeViewMode);
  };

  const handleViewModeChange = (mode: 'inside' | 'expected' | 'map') => {
    setActiveViewMode(mode);
    handleFilterApply(unitFilter, activeCategory, searchQuery, mode);
  };

  const handleRefresh = () => {
    setIsRefreshing(true);
    router.reload({
      onFinish: () => setIsRefreshing(false),
    });
  };

  // 7 Primary Community Access Categories Configuration
  const categoryConfig: Record<string, { label: string; icon: any; color: string; badgeClass: string; desc: string }> = {
    residents: {
      label: 'Residents',
      icon: Home,
      color: 'text-blue-500 bg-blue-500/10 border-blue-500/20',
      badgeClass: 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border-blue-500/20',
      desc: 'Verified Property Owners & Household',
    },
    long_term_guests: {
      label: 'Long-Term Guests / Renters',
      icon: UserCheck,
      color: 'text-indigo-500 bg-indigo-500/10 border-indigo-500/20',
      badgeClass: 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border-indigo-500/20',
      desc: 'Leaseholders & Long-Term Occupants',
    },
    short_term_guests: {
      label: 'Short-Term Rental Guests',
      icon: Sparkles,
      color: 'text-pink-500 bg-pink-500/10 border-pink-500/20',
      badgeClass: 'bg-pink-500/10 text-pink-600 dark:text-pink-400 border-pink-500/20',
      desc: 'Active Airbnb / Vacation Rental Guests',
    },
    visitors: {
      label: 'Visitors',
      icon: User,
      color: 'text-emerald-500 bg-emerald-500/10 border-emerald-500/20',
      badgeClass: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20',
      desc: 'Scheduled & One-Time Guest Passes',
    },
    staff: {
      label: 'Staff',
      icon: Shield,
      color: 'text-amber-500 bg-amber-500/10 border-amber-500/20',
      badgeClass: 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20',
      desc: 'Estate Security, Facilities & Caregivers',
    },
    contractors: {
      label: 'Contractors',
      icon: HardHat,
      color: 'text-orange-500 bg-orange-500/10 border-orange-500/20',
      badgeClass: 'bg-orange-500/10 text-orange-600 dark:text-orange-400 border-orange-500/20',
      desc: 'Permitted Trades, Technicians & Utility',
    },
    legacy_contacts: {
      label: 'Legacy Contacts',
      icon: HeartHandshake,
      color: 'text-purple-500 bg-purple-500/10 border-purple-500/20',
      badgeClass: 'bg-purple-500/10 text-purple-600 dark:text-purple-400 border-purple-500/20',
      desc: 'Emergency Contacts & Successor Delegates',
    },
  };

  const categoriesList = [
    { key: 'residents', label: 'Residents' },
    { key: 'long_term_guests', label: 'Long-Term Guests / Renters' },
    { key: 'short_term_guests', label: 'Short-Term Rental Guests' },
    { key: 'visitors', label: 'Visitors' },
    { key: 'staff', label: 'Staff' },
    { key: 'contractors', label: 'Contractors' },
    { key: 'legacy_contacts', label: 'Legacy Contacts' },
  ];

  const currentSummary = unitFilter && unitSummary ? unitSummary : summary;
  const currentExpectedSummary = unitFilter && unitExpectedSummary ? unitExpectedSummary : (expectedSummary || {
    residents: 0,
    long_term_guests: 0,
    short_term_guests: 0,
    visitors: 0,
    staff: 0,
    contractors: 0,
    legacy_contacts: 0,
    total: 0,
  });

  const activeDisplaySummary = activeViewMode === 'expected' ? currentExpectedSummary : currentSummary;

  // Copy Security Log Tally to Clipboard
  const handleCopyLedger = () => {
    const isExp = activeViewMode === 'expected';
    const s = activeDisplaySummary;
    const text = [
      isExp ? 'Expected Today Community Tally' : 'Currently Inside Community',
      'Category                    Count',
      '─────────────────────────────────',
      `Residents                   ${String(s.residents).padStart(5, ' ')}`,
      `Long-Term Guests / Renters  ${String(s.long_term_guests).padStart(5, ' ')}`,
      `Short-Term Rental Guests    ${String(s.short_term_guests || 0).padStart(5, ' ')}`,
      `Visitors                    ${String(s.visitors).padStart(5, ' ')}`,
      `Staff                       ${String(s.staff).padStart(5, ' ')}`,
      `Contractors                 ${String(s.contractors).padStart(5, ' ')}`,
      `Legacy Contacts             ${String(s.legacy_contacts).padStart(5, ' ')}`,
      '─────────────────────────────────',
      `TOTAL                       ${String(s.total).padStart(5, ' ')}`,
      '',
      `Timestamp: ${new Date(lastUpdated).toLocaleString()}`,
    ].join('\n');

    navigator.clipboard.writeText(text);
    setCopiedLedger(true);
    toast({
      title: 'Ledger Tally Copied',
      description: 'The complete 7-category census summary has been copied to your clipboard.',
    });
    setTimeout(() => setCopiedLedger(false), 2500);
  };

  // Start Emergency Muster
  const handleStartMuster = (e: React.FormEvent) => {
    e.preventDefault();
    setSubmittingMuster(true);

    router.post(
      '/dashboard/occupancy/muster/start',
      {
        incident_type: musterIncidentType,
        title: musterTitle || `${musterIncidentType.toUpperCase()} Emergency Evacuation`,
        assembly_point: musterAssemblyPoint,
        notes: musterNotes,
      },
      {
        preserveScroll: true,
        onSuccess: () => {
          setSubmittingMuster(false);
          setStartMusterOpen(false);
          toast({
            title: '🚨 Emergency Muster Activated',
            description: 'Live evacuation roll-call has been initiated for all occupants inside.',
          });
        },
        onError: (errs) => {
          setSubmittingMuster(false);
          toast({
            variant: 'destructive',
            title: 'Unable to Start Muster',
            description: (Object.values(errs)[0] as string) || 'Server error occurred.',
          });
        },
      }
    );
  };

  // Update Individual Muster Status
  const handleUpdateStatus = (occupantId: string, status: string, notes?: string) => {
    if (!activeMuster) return;

    router.post(
      '/dashboard/occupancy/muster/status',
      {
        session_id: activeMuster.id,
        occupant_id: occupantId,
        status,
        notes,
      },
      {
        preserveScroll: true,
        onSuccess: () => {
          toast({
            title: 'Status Updated',
            description: `Occupant marked as ${status.replace('_', ' ').toUpperCase()}.`,
          });
        },
      }
    );
  };

  // Resolve Muster Session
  const handleResolveMuster = () => {
    if (!activeMuster) return;
    if (!confirm('Are you sure you want to conclude and archive this emergency muster session?')) return;

    router.post(
      '/dashboard/occupancy/muster/resolve',
      {
        session_id: activeMuster.id,
        resolution_notes: 'All muster points accounted for. Emergency incident concluded.',
      },
      {
        preserveScroll: true,
        onSuccess: () => {
          toast({
            title: 'Emergency Muster Concluded',
            description: 'The evacuation session has been archived.',
          });
        },
      }
    );
  };

  // Filtered muster roll calls
  const filteredRollCalls = useMemo(() => {
    if (!activeMuster) return [];
    let list = activeMuster.roll_calls;
    if (musterFilter !== 'all') {
      list = list.filter((rc) => rc.status === musterFilter);
    }
    if (searchQuery.trim()) {
      const q = searchQuery.toLowerCase();
      list = list.filter(
        (rc) =>
          rc.name.toLowerCase().includes(q) ||
          (rc.unit && rc.unit.toLowerCase().includes(q)) ||
          (rc.pass_id && rc.pass_id.toLowerCase().includes(q)) ||
          (rc.contact && rc.contact.toLowerCase().includes(q))
      );
    }
    return list;
  }, [activeMuster, musterFilter, searchQuery]);

  return (
    <DashboardLayout>
      <Head title="Community Access Command Center — Live Occupancy & Gate Telemetry" />

      <div className="flex flex-col gap-6">
        {/* ════════════════════════════════════════════════════════════════
            1. TOP HEADER & TELEMETRY STATUS
           ════════════════════════════════════════════════════════════════ */}
        <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div>
            <div className="flex items-center gap-2.5">
              <h1 className="font-headline text-3xl font-extrabold tracking-tight">
                Community Access Command Center
              </h1>
              <span className="relative flex h-3 w-3">
                <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                <span className="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
              </span>
              {activeMuster && (
                <Badge variant="destructive" className="animate-pulse gap-1 font-bold text-xs uppercase tracking-wider">
                  <Siren className="w-3.5 h-3.5" /> Emergency Muster Active
                </Badge>
              )}
            </div>
            <p className="text-muted-foreground text-sm mt-1">
              Real-time estate census, gate access telemetry, property drill-down, and emergency evacuation muster.
            </p>
          </div>

          <div className="flex items-center gap-2 flex-wrap">
            <Button
              variant="outline"
              size="sm"
              onClick={handleRefresh}
              disabled={isRefreshing}
              className="gap-1.5 h-9 text-xs"
            >
              <RefreshCw className={cn("h-3.5 w-3.5", isRefreshing && "animate-spin")} />
              <span>{isRefreshing ? 'Refreshing...' : 'Live Ingress Feed'}</span>
            </Button>

            {/* Quick Link to GIS Map */}
            <Link href="/dashboard/map">
              <Button size="sm" variant="outline" className="gap-1.5 h-9 text-xs">
                <MapIcon className="h-3.5 w-3.5 text-blue-500" />
                <span>Cadastral Map</span>
              </Button>
            </Link>

            {/* Emergency Broadcast Trigger */}
            {canManage && (
              <EmergencyBroadcastDialog
                community="Community Hub"
                triggerButton={
                  <Button size="sm" variant="outline" className="gap-1.5 h-9 text-xs border-rose-500/40 text-rose-600 dark:text-rose-400 hover:bg-rose-500/10">
                    <Radio className="h-3.5 w-3.5" />
                    <span>Emergency Alert</span>
                  </Button>
                }
              />
            )}

            {/* Emergency Muster Activation Trigger */}
            {canManage && !activeMuster && (
              <Button
                size="sm"
                onClick={() => setStartMusterOpen(true)}
                className="gap-1.5 h-9 text-xs bg-rose-600 hover:bg-rose-700 text-white font-bold shadow-md shadow-rose-600/20"
              >
                <Siren className="h-3.5 w-3.5" />
                <span>Initiate Emergency Muster</span>
              </Button>
            )}

            {/* Evacuation Roster Export */}
            <a href="/dashboard/occupancy/export" download>
              <Button size="sm" variant="default" className="gap-1.5 h-9 text-xs font-semibold shadow-xs">
                <FileSpreadsheet className="h-3.5 w-3.5" />
                <span>Export Evacuation Roster</span>
              </Button>
            </a>
          </div>
        </div>

        {/* ════════════════════════════════════════════════════════════════
            2. MODE NAVIGATION: Currently Inside vs Expected Today vs Map
           ════════════════════════════════════════════════════════════════ */}
        <div className="flex flex-col sm:flex-row items-center justify-between gap-3 p-2 bg-muted/30 border rounded-2xl">
          <div className="flex items-center gap-1.5 w-full sm:w-auto">
            <Button
              size="sm"
              variant={activeViewMode === 'inside' ? 'default' : 'ghost'}
              onClick={() => handleViewModeChange('inside')}
              className={cn(
                "h-9 px-4 text-xs font-bold rounded-xl gap-2",
                activeViewMode === 'inside' ? "bg-primary text-primary-foreground shadow-xs" : "text-muted-foreground"
              )}
            >
              <span className="relative flex h-2 w-2">
                <span className="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
              </span>
              <span>Currently Inside Community</span>
              <Badge variant="secondary" className="px-1.5 py-0 text-[10px] font-mono ml-1">
                {currentSummary.total}
              </Badge>
            </Button>

            <Button
              size="sm"
              variant={activeViewMode === 'expected' ? 'default' : 'ghost'}
              onClick={() => handleViewModeChange('expected')}
              className={cn(
                "h-9 px-4 text-xs font-bold rounded-xl gap-2",
                activeViewMode === 'expected' ? "bg-primary text-primary-foreground shadow-xs" : "text-muted-foreground"
              )}
            >
              <Calendar className="w-3.5 h-3.5 text-amber-500" />
              <span>Expected Today</span>
              <Badge variant="secondary" className="px-1.5 py-0 text-[10px] font-mono ml-1">
                {currentExpectedSummary.total}
              </Badge>
            </Button>

            <Button
              size="sm"
              variant={activeViewMode === 'map' ? 'default' : 'ghost'}
              onClick={() => handleViewModeChange('map')}
              className={cn(
                "h-9 px-4 text-xs font-bold rounded-xl gap-2",
                activeViewMode === 'map' ? "bg-primary text-primary-foreground shadow-xs" : "text-muted-foreground"
              )}
            >
              <Network className="w-3.5 h-3.5 text-blue-500" />
              <span>Community Map &amp; Property Tree</span>
            </Button>
          </div>

          {/* Quick Spotlight: the viewer's own unit, or the one being filtered. It
              was always "Unit 14", a unit that may not exist on this estate. */}
          {(unitFilter || userUnit) && (
            <div className="flex items-center gap-2 w-full sm:w-auto justify-end">
              <Button
                size="sm"
                variant="outline"
                onClick={() => handleOpenUnitModal((unitFilter || userUnit) as string)}
                className="h-8 px-3 text-xs font-bold gap-1.5 rounded-xl border-primary/40 bg-primary/5 hover:bg-primary/10 text-primary"
              >
                <Sparkles className="w-3.5 h-3.5" />
                <span>Who&apos;s inside {unitFilter || userUnit}?</span>
              </Button>
            </div>
          )}
        </div>

        {/* ════════════════════════════════════════════════════════════════
            3. "CURRENTLY INSIDE COMMUNITY" / "EXPECTED TODAY" 7-CATEGORY SCORECARD
           ════════════════════════════════════════════════════════════════ */}
        {activeViewMode !== 'map' && (
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {/* Main Visual Census Scorecard (2 cols) */}
            <Card className="lg:col-span-2 border-border/80 shadow-md bg-gradient-to-br from-card via-card to-muted/20 overflow-hidden">
              <CardHeader className="pb-3 border-b border-border/40">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                  <div className="flex items-center gap-2">
                    <span className="p-1.5 rounded-lg bg-primary/10 text-primary">
                      <Users className="h-4 w-4" />
                    </span>
                    <div>
                      <CardTitle className="text-lg font-bold">
                        Access Overview · {unitFilter || 'Community'}
                      </CardTitle>
                      <CardDescription className="text-xs">
                        Live check-ins are separate from valid credentials expected to enter today.
                      </CardDescription>
                    </div>
                  </div>

                  {unitFilter && (
                    <Button
                      variant="ghost"
                      size="sm"
                      onClick={() => handleSelectUnit(null)}
                      className="text-xs text-primary font-medium hover:underline h-7 px-2"
                    >
                      ← Entire Community View
                    </Button>
                  )}
                </div>
              </CardHeader>

              <CardContent className="pt-4">
                <div className="grid grid-cols-2 gap-3">
                  <div className="rounded-lg border border-emerald-500/20 bg-emerald-500/5 p-3">
                    <p className="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Currently Inside</p>
                    <p className="mt-1 text-2xl font-extrabold font-mono text-foreground">{currentSummary.total}</p>
                  </div>
                  <div className="rounded-lg border border-amber-500/20 bg-amber-500/5 p-3">
                    <p className="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Expected Today</p>
                    <p className="mt-1 text-2xl font-extrabold font-mono text-foreground">{currentExpectedSummary.total}</p>
                  </div>
                </div>

                <div className="mt-4">
                  <div className="grid grid-cols-[minmax(0,1fr)_4.5rem_4.5rem] gap-2 border-b border-border px-2 pb-2 text-[10px] font-bold uppercase tracking-wider text-muted-foreground">
                    <span>Category</span>
                    <span className="text-right">Inside</span>
                    <span className="text-right">Expected</span>
                  </div>
                  {categoriesList.map((catItem) => {
                    const cfg = categoryConfig[catItem.key] || categoryConfig.visitors;
                    const IconComp = cfg.icon;
                    const isSelected = activeCategory === catItem.key;

                    return (
                      <button
                        key={catItem.key}
                        type="button"
                        onClick={() => handleCategoryChange(catItem.key)}
                        className={cn(
                          'grid w-full grid-cols-[minmax(0,1fr)_4.5rem_4.5rem] items-center gap-2 rounded-md border-b border-border/60 px-2 py-2 text-left transition-colors hover:bg-muted/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
                          isSelected && 'bg-muted/50',
                        )}
                        aria-label={`${catItem.label}: ${currentSummary[catItem.key as keyof OccupancySummary] ?? 0} currently inside, ${currentExpectedSummary[catItem.key as keyof OccupancySummary] ?? 0} expected today. Filter roster by ${catItem.label}.`}
                      >
                        <span className="flex min-w-0 items-center gap-2 text-sm font-medium text-foreground">
                          <IconComp className={cn('h-4 w-4 shrink-0', cfg.color.split(' ')[0])} aria-hidden />
                          <span className="truncate">{catItem.label === 'Long-Term Guests / Renters' ? 'Renters' : catItem.label}</span>
                        </span>
                        <span className="text-right font-mono text-sm font-semibold text-emerald-700 dark:text-emerald-400">
                          {currentSummary[catItem.key as keyof OccupancySummary] ?? 0}
                        </span>
                        <span className="text-right font-mono text-sm font-semibold text-amber-700 dark:text-amber-400">
                          {currentExpectedSummary[catItem.key as keyof OccupancySummary] ?? 0}
                        </span>
                      </button>
                    );
                  })}
                </div>
              </CardContent>
            </Card>

            {/* Security Guard / Admin Monospace Tally Ledger Box */}
            <Card className="border border-foreground/20 shadow-md bg-neutral-950 text-neutral-100 font-mono flex flex-col justify-between overflow-hidden">
              <CardHeader className="pb-2 border-b border-neutral-800">
                <div className="flex items-center justify-between">
                  <span className="text-xs uppercase tracking-wider text-neutral-400 font-bold flex items-center gap-1.5">
                    <Shield className="w-3.5 h-3.5 text-emerald-400" /> Command Center Telemetry
                  </span>
                  <Badge variant="outline" className="text-[10px] text-emerald-400 border-emerald-500/30">
                    {activeViewMode === 'expected' ? 'EXPECTED TODAY' : 'CURRENTLY INSIDE'}
                  </Badge>
                </div>
                <CardTitle className="text-base text-neutral-100 font-bold mt-1">
                  {activeViewMode === 'expected' ? 'Expected Today Community' : 'Currently Inside Community'}
                </CardTitle>
              </CardHeader>

              <CardContent className="py-3 text-xs leading-relaxed space-y-1.5">
                <div className="grid grid-cols-2 text-[11px] font-bold text-neutral-400 pb-1 border-b border-neutral-800 uppercase tracking-wider">
                  <span>Category</span>
                  <span className="text-right">{activeViewMode === 'expected' ? 'Expected' : 'Inside'}</span>
                </div>

                <div className="flex justify-between items-center py-0.5 border-b border-neutral-900">
                  <span className="text-neutral-300">Residents</span>
                  <span className="font-bold text-white text-sm">{activeDisplaySummary.residents}</span>
                </div>
                <div className="flex justify-between items-center py-0.5 border-b border-neutral-900">
                  <span className="text-neutral-300">Long-Term Guests / Renters</span>
                  <span className="font-bold text-white text-sm">{activeDisplaySummary.long_term_guests}</span>
                </div>
                <div className="flex justify-between items-center py-0.5 border-b border-neutral-900">
                  <span className="text-neutral-300">Short-Term Rental Guests</span>
                  <span className="font-bold text-white text-sm">{activeDisplaySummary.short_term_guests || 0}</span>
                </div>
                <div className="flex justify-between items-center py-0.5 border-b border-neutral-900">
                  <span className="text-neutral-300">Visitors</span>
                  <span className="font-bold text-white text-sm">{activeDisplaySummary.visitors}</span>
                </div>
                <div className="flex justify-between items-center py-0.5 border-b border-neutral-900">
                  <span className="text-neutral-300">Staff</span>
                  <span className="font-bold text-white text-sm">{activeDisplaySummary.staff}</span>
                </div>
                <div className="flex justify-between items-center py-0.5 border-b border-neutral-900">
                  <span className="text-neutral-300">Contractors</span>
                  <span className="font-bold text-white text-sm">{activeDisplaySummary.contractors}</span>
                </div>
                <div className="flex justify-between items-center py-0.5 border-b border-neutral-900">
                  <span className="text-neutral-300">Legacy Contacts</span>
                  <span className="font-bold text-white text-sm">{activeDisplaySummary.legacy_contacts}</span>
                </div>
                <div className="text-neutral-500 tracking-tighter">───────────────────────────────────</div>
                <div className="flex justify-between items-center pt-1 font-extrabold text-sm text-emerald-400">
                  <span className="uppercase tracking-wider">TOTAL</span>
                  <span className="text-xl font-black">{activeDisplaySummary.total}</span>
                </div>
              </CardContent>

              <div className="p-3 bg-neutral-900/60 border-t border-neutral-800 flex items-center justify-between text-[11px]">
                <span className="text-neutral-400">Shift Telemetry Log</span>
                <Button
                  size="sm"
                  variant="outline"
                  onClick={handleCopyLedger}
                  className="h-7 px-2.5 text-[11px] gap-1 bg-neutral-900 border-neutral-700 text-neutral-200 hover:text-white hover:bg-neutral-800"
                >
                  {copiedLedger ? <Check className="w-3 h-3 text-emerald-400" /> : <Copy className="w-3 h-3" />}
                  <span>{copiedLedger ? 'Copied' : 'Copy Tally'}</span>
                </Button>
              </div>
            </Card>
          </div>
        )}

        {/* ════════════════════════════════════════════════════════════════
            4. COMMUNITY MAP & PROPERTY HIERARCHY TREE (SPATIAL EXPLORER)
           ════════════════════════════════════════════════════════════════ */}
        {activeViewMode === 'map' && (
          <Card className="border shadow-md overflow-hidden bg-gradient-to-b from-card via-card to-muted/10">
            <CardHeader className="border-b bg-muted/20">
              <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div className="flex items-center gap-2.5">
                  <div className="p-2 rounded-xl bg-blue-500/10 text-blue-600 dark:text-blue-400">
                    <Network className="w-5 h-5" />
                  </div>
                  <div>
                    <CardTitle className="text-lg font-bold">
                      Community Map &amp; Property Hierarchy Explorer
                    </CardTitle>
                    <CardDescription className="text-xs">
                      Spatial organizational tree mapping persons to specific properties, with live on-site and expected occupancy.
                    </CardDescription>
                  </div>
                </div>

                <div className="flex items-center gap-2">
                  <Link href="/dashboard/map">
                    <Button size="sm" variant="default" className="h-8 text-xs gap-1.5 font-semibold">
                      <MapIcon className="w-3.5 h-3.5" />
                      <span>Open Interactive GIS Map</span>
                    </Button>
                  </Link>
                </div>
              </div>
            </CardHeader>

            <CardContent className="p-6 space-y-6">
              {/* Interactive Property Selection & Topology Bar */}
              <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 p-3 bg-muted/40 rounded-xl border">
                <div className="flex items-center gap-2 flex-wrap">
                  <span className="text-xs font-bold text-foreground flex items-center gap-1.5">
                    <MapIcon className="w-4 h-4 text-primary" />
                    <span>Select Property:</span>
                  </span>

                  {/* Quick Select Buttons: the first two real units */}
                  {propertyList.slice(0, 2).map((p: any) => (
                    <Button
                      key={p.id ?? p.unit}
                      size="sm"
                      variant={selectedTreeUnit.toLowerCase() === String(p.unit).toLowerCase() ? 'default' : 'outline'}
                      onClick={() => setSelectedTreeUnit(p.unit)}
                      className="h-7 px-3 text-xs font-mono font-bold rounded-lg"
                    >
                      {String(p.unit).toUpperCase()}
                    </Button>
                  ))}

                  {/* Dropdown for All Other Community Units */}
                  <select
                    value={selectedTreeUnit}
                    onChange={(e) => setSelectedTreeUnit(e.target.value)}
                    className="h-7 text-xs font-mono font-medium rounded-lg bg-background border px-2 py-0 text-foreground focus:ring-1 focus:ring-primary"
                  >
                    {propertyList.length === 0 && <option value="">No units</option>}
                    {propertyList.map((p: any) => (
                      <option key={p.id ?? p.unit} value={p.unit}>
                        {p.unit} ({p.insideCount ?? 0} inside)
                      </option>
                    ))}
                  </select>
                </div>

                <div className="flex items-center gap-2">
                  <Badge variant="outline" className="text-[11px] font-mono bg-emerald-500/10 text-emerald-600 border-emerald-500/30">
                    {propertyList.length} {propertyList.length === 1 ? 'property' : 'properties'}
                  </Badge>
                </div>
              </div>

              {/* Community Map: a text tree of the selected unit, from real data only */}
              <div className="rounded-xl border bg-neutral-950 text-neutral-100">
                <div className="flex items-center justify-between px-4 py-2 border-b border-neutral-800">
                  <span className="text-[11px] font-mono uppercase tracking-wider text-neutral-400">Community Map</span>
                  <Button
                    size="sm"
                    variant="ghost"
                    className="h-7 px-2 text-[11px] text-neutral-300 hover:text-white gap-1"
                    onClick={() => {
                      navigator.clipboard?.writeText(asciiTree);
                      setCopiedTree(true);
                      setTimeout(() => setCopiedTree(false), 2000);
                    }}
                  >
                    {copiedTree ? <Check className="w-3.5 h-3.5" /> : <Copy className="w-3.5 h-3.5" />}
                    <span>{copiedTree ? 'Copied' : 'Copy'}</span>
                  </Button>
                </div>
                <pre className="p-4 text-xs font-mono leading-relaxed overflow-x-auto">{asciiTree}</pre>
              </div>

              {/* Property Grid Selection */}
              <div>
                <h3 className="text-sm font-bold text-foreground mb-3 flex items-center justify-between">
                  <span>Authorized Community Parcels</span>
                  <span className="text-xs text-muted-foreground font-normal">
                    Select any property to inspect occupants or drill into specific access records
                  </span>
                </h3>

                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                  {propertyList.length === 0 && (
                    <p className="text-xs text-muted-foreground col-span-full">No occupancy data is available right now.</p>
                  )}
                  {propertyList.map((prop: any) => (
                    <Card
                      key={prop.id}
                      className={cn(
                        "p-4 border transition-all hover:border-primary/50 hover:shadow-md cursor-pointer",
                        unitFilter === prop.unit ? "border-primary ring-1 ring-primary bg-primary/5" : "bg-card"
                      )}
                      onClick={() => handleOpenUnitModal(prop.unit)}
                    >
                      <div className="flex items-center justify-between pb-2 border-b">
                        <div>
                          <div className="font-extrabold text-base text-foreground flex items-center gap-1.5">
                            <Building className="w-4 h-4 text-primary" />
                            <span>{prop.unit}</span>
                          </div>
                          <div className="text-xs text-muted-foreground">{prop.address}</div>
                        </div>

                        <div className="text-right">
                          <Badge className="bg-emerald-500/10 text-emerald-600 border border-emerald-500/30 text-xs font-bold">
                            {prop.insideCount} Inside
                          </Badge>
                          <div className="text-[10px] text-muted-foreground font-mono mt-0.5">
                            {prop.expectedCount} Expected
                          </div>
                        </div>
                      </div>

                      {/* Category Breakdown Tree List */}
                      <div className="py-2.5 space-y-1 font-mono text-xs">
                        {(prop.breakdown ?? []).map((item: any, idx: number) => (
                          <div key={idx} className="flex items-center justify-between text-muted-foreground">
                            <span className="flex items-center gap-1.5">
                              <span className="text-neutral-400">├─</span>
                              <span>{item.inside} {item.label}</span>
                            </span>
                            {item.expected > item.inside && (
                              <span className="text-[10px] text-amber-500 font-semibold">
                                +{item.expected - item.inside} exp
                              </span>
                            )}
                          </div>
                        ))}
                      </div>

                      <div className="pt-2 border-t flex items-center justify-between">
                        <Button
                          size="sm"
                          variant="ghost"
                          onClick={(e) => {
                            e.stopPropagation();
                            handleSelectUnit(prop.unit);
                            setActiveViewMode('inside');
                          }}
                          className="h-7 px-2 text-[11px] text-muted-foreground hover:text-foreground"
                        >
                          Filter Census
                        </Button>

                        <Button
                          size="sm"
                          variant="default"
                          onClick={(e) => {
                            e.stopPropagation();
                            handleOpenUnitModal(prop.unit);
                          }}
                          className="h-7 px-2.5 text-[11px] font-bold gap-1"
                        >
                          <span>Who&apos;s inside?</span>
                          <ChevronRight className="w-3 h-3" />
                        </Button>
                      </div>
                    </Card>
                  ))}
                </div>
              </div>
            </CardContent>
          </Card>
        )}

        {/* ════════════════════════════════════════════════════════════════
            5. CURRENT OCCUPANTS REGISTRY TABLE & PROPERTY ASSOCIATIONS
           ════════════════════════════════════════════════════════════════ */}
        {activeViewMode !== 'map' && (
          <Card className="border rounded-2xl shadow-xs">
            <CardHeader className="pb-3 border-b">
              <div className="flex flex-col md:flex-row md:items-center justify-between gap-3">
                <div>
                  <CardTitle className="text-lg font-bold">
                    {activeViewMode === 'expected' ? 'Expected Today Registry' : 'Occupant Census Roster'}
                  </CardTitle>
                  <CardDescription className="text-xs">
                    {occupants.total > 0
                      ? `Displaying ${occupants.data.length} of ${occupants.total} individuals. Every record retains its property association.`
                      : 'No individuals recorded matching this selection.'}
                  </CardDescription>
                </div>

                {/* Search Within Roster */}
                <div className="flex items-center gap-2">
                  <div className="relative w-full sm:w-64">
                    <Search className="absolute left-2.5 top-2.5 h-3.5 w-3.5 text-muted-foreground" />
                    <Input
                      placeholder="Search name, unit, plate, pass ID..."
                      value={searchQuery}
                      onChange={(e) => setSearchQuery(e.target.value)}
                      onKeyDown={(e) => {
                        if (e.key === 'Enter') {
                          handleFilterApply(unitFilter, activeCategory, searchQuery);
                        }
                      }}
                      className="h-8 pl-8 text-xs"
                    />
                  </div>
                  {searchQuery && (
                    <Button
                      size="sm"
                      variant="ghost"
                      onClick={() => {
                        setSearchQuery('');
                        handleFilterApply(unitFilter, activeCategory, '');
                      }}
                      className="h-8 px-2 text-xs"
                    >
                      Clear
                    </Button>
                  )}
                </div>
              </div>

              {/* Category Filter Tabs */}
              <div className="flex items-center gap-1.5 overflow-x-auto pt-2 text-xs">
                <Button
                  size="sm"
                  variant={activeCategory === 'all' ? "default" : "ghost"}
                  onClick={() => handleCategoryChange('all')}
                  className="h-7 px-2.5 text-xs rounded-lg"
                >
                  All ({activeDisplaySummary.total})
                </Button>
                {categoriesList.map((cat) => (
                  <Button
                    key={cat.key}
                    size="sm"
                    variant={activeCategory === cat.key ? "default" : "ghost"}
                    onClick={() => handleCategoryChange(cat.key)}
                    className="h-7 px-2.5 text-xs rounded-lg whitespace-nowrap"
                  >
                    {cat.label} ({(activeDisplaySummary as any)[cat.key] || 0})
                  </Button>
                ))}
              </div>
            </CardHeader>

            <CardContent className="p-0">
              <Table>
                <TableHeader>
                  <TableRow className="hover:bg-transparent">
                    <TableHead className="w-[240px]">Person &amp; Role</TableHead>
                    <TableHead>Category</TableHead>
                    <TableHead>Authorized Property</TableHead>
                    <TableHead>Authorized By / Host</TableHead>
                    <TableHead>Check-in &amp; Gate</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead className="text-right pr-6">Access Details</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {occupants.data.length === 0 ? (
                    <TableRow>
                      <TableCell colSpan={7} className="py-12 text-center text-sm text-muted-foreground">
                        No individuals recorded for this selection.
                      </TableCell>
                    </TableRow>
                  ) : (
                    occupants.data.map((item) => {
                      const cfg = categoryConfig[item.categoryKey] || categoryConfig.visitors;
                      const IconComponent = cfg.icon;
                      const isExpected = item.checkInStatus === 'EXPECTED' || activeViewMode === 'expected';

                      return (
                        <TableRow key={item.id} className="hover:bg-muted/30">
                          {/* 1. Name & Role */}
                          <TableCell className="font-semibold text-foreground">
                            <div className="flex items-center gap-2.5">
                              <div className={cn("p-1.5 rounded-lg shrink-0", cfg.color)}>
                                <IconComponent className="h-4 w-4" />
                              </div>
                              <div>
                                <button
                                  type="button"
                                  onClick={() => setSelectedPerson(item)}
                                  className="font-bold text-foreground text-sm hover:text-primary hover:underline text-left block"
                                >
                                  {item.name}
                                </button>
                                <div className="flex items-center gap-1.5 text-[11px] text-muted-foreground font-mono">
                                  <span>{item.passId}</span>
                                  <span>•</span>
                                  <span>{item.role}</span>
                                </div>
                              </div>
                            </div>
                          </TableCell>

                          {/* 2. Category */}
                          <TableCell>
                            <Badge variant="outline" className={cn("text-[11px] font-semibold border", cfg.badgeClass)}>
                              {item.categoryLabel}
                            </Badge>
                          </TableCell>

                          {/* 3. Property Association (Retained on every record!) */}
                          <TableCell>
                            <button
                              type="button"
                              onClick={() => handleOpenUnitModal(item.unit || item.property)}
                              className="font-mono text-xs font-bold text-primary hover:underline flex items-center gap-1.5"
                              title={`Inspect ${item.unit || item.property}`}
                            >
                              <Building className="h-3.5 w-3.5 text-muted-foreground shrink-0" />
                              <span><span className="text-muted-foreground font-normal">Authorized for: </span>{item.unit || item.property}</span>
                            </button>
                            {item.property && item.property !== item.unit && (
                              <span className="text-[10px] text-muted-foreground block truncate max-w-[150px]">
                                {item.property}
                              </span>
                            )}
                          </TableCell>

                          {/* 4. Authorized By / Host */}
                          <TableCell className="text-xs text-foreground">
                            {item.authorizedBy || item.host ? (
                              <span className="font-medium">{item.authorizedBy || item.host}</span>
                            ) : (
                              <span className="text-muted-foreground italic">Estate Administration</span>
                            )}
                          </TableCell>

                          {/* 5. Ingress & Gate */}
                          <TableCell className="text-xs">
                            <div className="flex items-center gap-1 font-mono text-foreground font-medium">
                              <Clock className="h-3 w-3 text-muted-foreground" />
                              <span>Entered: {item.checkedInTime}</span>
                            </div>
                            <div className="text-[10px] text-muted-foreground font-mono">
                              Gate: {item.gate || 'Main Gate'}
                            </div>
                          </TableCell>

                          {/* 6. Status */}
                          <TableCell>
                            <Badge
                              className={cn(
                                "text-[10px] font-bold uppercase",
                                !isExpected
                                  ? "bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20"
                                  : "bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20"
                              )}
                            >
                              {!isExpected ? 'INSIDE' : 'EXPECTED'}
                            </Badge>
                          </TableCell>

                          {/* 7. Action: Drill Further */}
                          <TableCell className="text-right pr-6">
                            <Button
                              size="sm"
                              variant="outline"
                              onClick={() => setSelectedPerson(item)}
                              className="h-7 px-2 text-[11px] gap-1 font-semibold"
                            >
                              <span>Inspect Access</span>
                              <ChevronRight className="w-3 h-3" />
                            </Button>
                          </TableCell>
                        </TableRow>
                      );
                    })
                  )}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        )}

        {/* ════════════════════════════════════════════════════════════════
            6. DEDICATED UNIT DRILL-DOWN MODAL: "Who's inside Unit 14?"
           ════════════════════════════════════════════════════════════════ */}
        <Dialog open={drillDownModalOpen} onOpenChange={setDrillDownModalOpen}>
          <DialogContent className="sm:max-w-[620px] max-h-[90vh] overflow-y-auto">
            <DialogHeader className="border-b pb-3">
              <div className="flex items-center justify-between">
                <div>
                  <DialogTitle className="text-xl font-black font-mono tracking-wide text-foreground">
                    {drillDownData?.unit || drillDownUnit}
                  </DialogTitle>
                  <DialogDescription className="text-xs">
                    Detailed occupant roster and access status for this property address.
                  </DialogDescription>
                </div>

                <div className="flex items-center gap-2">
                  <Badge className="bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30 font-mono font-bold text-xs">
                    INSIDE: {drillDownData?.totalCurrentlyInside ?? drillDownData?.total ?? '—'}
                  </Badge>
                  <Badge variant="outline" className="text-muted-foreground font-mono font-bold text-xs">
                    EXPECTED: {drillDownData?.totalExpectedToday ?? '—'}
                  </Badge>
                </div>
              </div>
            </DialogHeader>

            {drillDownError && !drillDownLoading ? (
              <div role="alert" className="py-10 text-center text-sm text-destructive">
                {drillDownError}
              </div>
            ) : drillDownLoading ? (
              <div className="py-12 flex flex-col items-center justify-center gap-2 text-muted-foreground">
                <RefreshCw className="w-6 h-6 animate-spin text-primary" />
                <span className="text-xs font-mono">Querying estate telemetry for {drillDownUnit}...</span>
              </div>
            ) : (
              <div className="space-y-4 py-2">
                {/* Monospace Terminal View Matching Specification */}
                <div className="font-mono text-xs border rounded-xl p-4 bg-neutral-950 text-neutral-100 space-y-3">
                  <div className="space-y-1">
                    <div className="text-white font-bold text-sm tracking-wider">
                      {(drillDownData?.unit || drillDownUnit || '—').toUpperCase()}
                    </div>
                    <div className="text-neutral-600 font-mono text-xs select-none">
                      ────────────────────────────
                    </div>
                  </div>

                  {/* 7 Categories */}
                  {categoriesList.map((cat) => {
                    const rosterItems: any[] = drillDownData?.rosterByCategory?.[cat.key] || [];
                    const displayLabel = cat.key === 'long_term_guests'
                      ? 'Long-Term Guests'
                      : cat.key === 'short_term_guests'
                      ? 'Short-Term Rental'
                      : cat.label;

                    return (
                      <div key={cat.key} className="space-y-1">
                        <div className="text-[12px] font-bold text-neutral-300">
                          {displayLabel}
                        </div>

                        {rosterItems.length === 0 ? (
                          <div className="text-neutral-500 pl-4 py-0.5">None</div>
                        ) : (
                          <div className="space-y-1 pl-4">
                            {rosterItems.map((person, pIdx) => (
                              <div
                                key={pIdx}
                                onClick={() => setSelectedPerson(person)}
                                className="flex items-center justify-between p-1 rounded-md hover:bg-neutral-900 cursor-pointer transition-colors group"
                                title="Click to inspect underlying access details"
                              >
                                <div className="flex items-center gap-2">
                                  <span className="text-neutral-200 group-hover:text-white group-hover:underline">
                                    {person.name}
                                  </span>
                                  {person.role && person.role !== person.name && (
                                    <span className="text-[10px] text-neutral-500">({person.role})</span>
                                  )}
                                </div>
                                <div className="flex items-center gap-2">
                                  <span
                                    className={cn(
                                      "text-xs font-bold font-mono",
                                      person.status === 'IN'
                                        ? "text-emerald-400"
                                        : person.status === 'OUT'
                                        ? "text-neutral-500"
                                        : "text-amber-400"
                                    )}
                                  >
                                    {person.status}
                                  </span>
                                  <ChevronRight className="w-3 h-3 text-neutral-600 group-hover:text-neutral-300" />
                                </div>
                              </div>
                            ))}
                          </div>
                        )}
                      </div>
                    );
                  })}

                  <div className="pt-2 space-y-1">
                    <div className="text-neutral-600 font-mono text-xs select-none">
                      ────────────────────────────
                    </div>
                    <div className="flex items-center justify-between font-bold text-xs pt-1">
                      <span className="text-emerald-400 uppercase tracking-wider">
                        TOTAL CURRENTLY INSIDE:
                      </span>
                      <span className="text-emerald-400 text-sm font-black">
                        {drillDownData?.totalCurrentlyInside ?? drillDownData?.total ?? '—'}
                      </span>
                    </div>
                  </div>
                </div>

                <p className="text-[11px] text-muted-foreground italic text-center">
                  Click any individual above to inspect their underlying access relationship, QR validity, and boundary permissions.
                </p>
              </div>
            )}

            <DialogFooter className="border-t pt-3 flex items-center justify-between">
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => setDrillDownModalOpen(false)}
              >
                Close Drill-down
              </Button>

              <Button
                type="button"
                size="sm"
                onClick={() => {
                  setDrillDownModalOpen(false);
                  handleSelectUnit(drillDownData?.unit || drillDownUnit);
                }}
                className="gap-1.5 font-bold"
              >
                <span>Filter Main Registry to {drillDownData?.unit || drillDownUnit}</span>
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>

        {/* ════════════════════════════════════════════════════════════════
            7. DEEP ACCESS RELATIONSHIP INSPECTOR DIALOG
               (Person → Property → Relationship → Authorization → Visibility → Permissions)
           ════════════════════════════════════════════════════════════════ */}
        <Dialog open={!!selectedPerson} onOpenChange={(open) => !open && setSelectedPerson(null)}>
          <DialogContent className="sm:max-w-[520px]">
            <DialogHeader className="border-b pb-3">
              <div className="flex items-center gap-2 text-xs font-mono text-muted-foreground">
                <Badge variant="outline" className="text-[10px] font-bold text-purple-600 dark:text-purple-400 bg-purple-500/10 border-purple-500/20">
                  {selectedPerson?.categoryLabel || selectedPerson?.role || 'Legacy Contact'}
                </Badge>
                <span>•</span>
                <span>Authorized for: <strong>{selectedPerson?.property || selectedPerson?.unit || 'Not recorded'}</strong></span>
              </div>
              <DialogTitle className="text-xl font-black text-foreground mt-1 flex items-center gap-2">
                <span>{selectedPerson?.name}</span>
                <span className="text-muted-foreground font-normal text-base">→</span>
                <span className="text-primary">{selectedPerson?.role || selectedPerson?.categoryLabel || 'Legacy Contact'}</span>
              </DialogTitle>
              <DialogDescription className="text-xs">
                Underlying access relationship, credential clearance, gate telemetry, and boundary limits.
              </DialogDescription>
            </DialogHeader>

            <div className="space-y-4 py-2 text-xs">
              {/* Telemetry Detail Grid Matching Specification */}
              <div className="grid grid-cols-2 gap-2.5 p-3.5 rounded-xl bg-neutral-950 text-neutral-100 border border-neutral-800 font-mono text-xs">
                <div>
                  <span className="text-neutral-400 block text-[10px] uppercase tracking-wider">Authorized By</span>
                  <span className="font-bold text-white text-sm">
                    {selectedPerson?.authorizedBy || selectedPerson?.host || 'Not recorded'}
                  </span>
                </div>
                <div>
                  <span className="text-neutral-400 block text-[10px] uppercase tracking-wider">Access Type</span>
                  <span className="font-bold text-white text-sm">
                    {selectedPerson?.accessType || 'Emergency'}
                  </span>
                </div>
                <div>
                  <span className="text-neutral-400 block text-[10px] uppercase tracking-wider">QR</span>
                  <span className="font-bold text-emerald-400 text-sm">
                    {selectedPerson?.qrStatus || 'Active'}
                  </span>
                </div>
                <div>
                  <span className="text-neutral-400 block text-[10px] uppercase tracking-wider">Check-in</span>
                  <span className="font-bold text-white text-sm">
                    {selectedPerson?.checkedInTime || '10:42 AM'}
                  </span>
                </div>
                <div>
                  <span className="text-neutral-400 block text-[10px] uppercase tracking-wider">Gate</span>
                  <span className="font-bold text-white text-sm">
                    {selectedPerson?.gate || 'Main Gate'}
                  </span>
                </div>
                <div>
                  <span className="text-neutral-400 block text-[10px] uppercase tracking-wider">Expiration</span>
                  <span className="font-bold text-white text-sm">
                    {selectedPerson?.expiration || 'Oct 14, 2026'}
                  </span>
                </div>
                <div className="col-span-2 pt-1 border-t border-border/50 flex items-center justify-between">
                  <div>
                    <span className="text-muted-foreground block text-[10px]">Credential Expiration</span>
                    <span className="font-bold text-foreground">
                      {selectedPerson?.expiration || 'Active'}
                    </span>
                  </div>
                  <div>
                    <span className="text-muted-foreground block text-[10px]">QR Credential</span>
                    <span className="font-bold text-emerald-600 dark:text-emerald-400">
                      {selectedPerson?.qrStatus || 'Active'}
                    </span>
                  </div>
                </div>
              </div>

              {/* Visual Relationship Chain Flow Diagram */}
              <div className="p-3.5 rounded-xl bg-card border space-y-2">
                <div className="text-[10px] font-bold text-muted-foreground uppercase tracking-wider flex items-center justify-between">
                  <span>Underlying Relationship Flow</span>
                  <KeyRound className="w-3.5 h-3.5 text-primary" />
                </div>
                <div className="flex flex-col gap-1.5 text-xs font-mono">
                  <div className="flex items-center gap-2">
                    <span className="px-2 py-0.5 rounded bg-primary/10 text-primary font-bold">
                      {selectedPerson?.name}
                    </span>
                    <span className="text-muted-foreground">↓</span>
                  </div>
                  <div className="flex items-center gap-2 pl-3">
                    <span className="px-2 py-0.5 rounded bg-purple-500/10 text-purple-600 dark:text-purple-400 font-semibold">
                      {selectedPerson?.categoryLabel || selectedPerson?.role || 'Legacy Contact'}
                    </span>
                    <span className="text-muted-foreground">↓</span>
                  </div>
                  <div className="flex items-center gap-2 pl-6">
                    <span className="px-2 py-0.5 rounded bg-blue-500/10 text-blue-600 dark:text-blue-400 font-semibold">
                      {selectedPerson?.property || selectedPerson?.unit || 'Not recorded'}
                    </span>
                    <span className="text-muted-foreground">↓</span>
                  </div>
                  <div className="flex items-center gap-2 pl-9">
                    <span className="px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 font-semibold">
                      Authorized by {selectedPerson?.authorizedBy || selectedPerson?.host || 'Not recorded'}
                    </span>
                    <span className="text-muted-foreground">↓</span>
                  </div>
                  <div className="flex items-center gap-2 pl-12">
                    <span className="px-2 py-0.5 rounded bg-amber-500/10 text-amber-600 dark:text-amber-400 font-semibold">
                      {selectedPerson?.accessType || 'Emergency Access'}
                    </span>
                  </div>
                </div>
              </div>

              {/* Explicit Boundary Limits & Permissions */}
              <div className="grid grid-cols-2 gap-2">
                <div className="p-2.5 rounded-lg border border-emerald-500/30 bg-emerald-500/5 space-y-1">
                  <div className="font-bold text-emerald-600 dark:text-emerald-400 flex items-center gap-1 text-[11px]">
                    <Check className="w-3.5 h-3.5" /> Can (Permitted)
                  </div>
                  <ul className="space-y-1 text-[11px] text-muted-foreground">
                    {(selectedPerson?.relationshipChain?.can || [
                      'Enter property',
                      'Receive emergency notifications',
                      'Authorize emergency visitors',
                    ]).map((c, i) => (
                      <li key={i} className="flex items-start gap-1">
                        <span className="text-emerald-500 font-bold">✓</span>
                        <span>{c}</span>
                      </li>
                    ))}
                  </ul>
                </div>

                <div className="p-2.5 rounded-lg border border-rose-500/30 bg-rose-500/5 space-y-1">
                  <div className="font-bold text-rose-600 dark:text-rose-400 flex items-center gap-1 text-[11px]">
                    <AlertTriangle className="w-3.5 h-3.5" /> Cannot (Restricted)
                  </div>
                  <ul className="space-y-1 text-[11px] text-muted-foreground">
                    {(selectedPerson?.relationshipChain?.cannot || [
                      'View community directory',
                      'View other properties',
                      'Modify ownership/settings',
                    ]).map((c, i) => (
                      <li key={i} className="flex items-start gap-1">
                        <span className="text-rose-500 font-bold">✗</span>
                        <span>{c}</span>
                      </li>
                    ))}
                  </ul>
                </div>
              </div>
            </div>

            <DialogFooter className="border-t pt-3">
              <Button type="button" variant="outline" size="sm" onClick={() => setSelectedPerson(null)}>
                Close
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>

        {/* ════════════════════════════════════════════════════════════════
            8. EMERGENCY MUSTER DIALOGS (Pre-existing & Fully Preserved)
           ════════════════════════════════════════════════════════════════ */}
        <Dialog open={startMusterOpen} onOpenChange={setStartMusterOpen}>
          <DialogContent className="sm:max-w-[480px]">
            <form onSubmit={handleStartMuster} className="space-y-4">
              <DialogHeader>
                <DialogTitle className="text-lg font-bold flex items-center gap-2 text-rose-600">
                  <Siren className="w-5 h-5 animate-pulse" />
                  Initiate Emergency Muster
                </DialogTitle>
                <DialogDescription className="text-xs">
                  Create an active muster session and snapshot all {summary.total} verified occupants on-site for emergency roll-call.
                </DialogDescription>
              </DialogHeader>

              <div className="space-y-3">
                <div className="space-y-1.5">
                  <Label className="text-xs font-semibold">Incident Type</Label>
                  <div className="grid grid-cols-2 gap-2 text-xs">
                    {(['fire', 'hurricane', 'earthquake', 'flood', 'security_incident', 'drill', 'other'] as const).map((t) => (
                      <button
                        key={t}
                        type="button"
                        onClick={() => {
                          setMusterIncidentType(t);
                          if (!musterTitle) {
                            setMusterTitle(`${t.replace('_', ' ').toUpperCase()} Evacuation`);
                          }
                        }}
                        className={cn(
                          "p-2 rounded-lg border text-left font-bold capitalize transition-all",
                          musterIncidentType === t ? "border-rose-500 bg-rose-500/10 text-rose-600" : "border-border bg-card"
                        )}
                      >
                        {t.replace('_', ' ')}
                      </button>
                    ))}
                  </div>
                </div>

                <div className="space-y-1.5">
                  <Label htmlFor="muster-title" className="text-xs font-semibold">Incident Title</Label>
                  <Input
                    id="muster-title"
                    value={musterTitle}
                    onChange={(e) => setMusterTitle(e.target.value)}
                    placeholder="e.g. Unit 14 Structural Fire, Hurricane Evacuation"
                    className="h-9 text-xs"
                    required
                  />
                </div>

                <div className="space-y-1.5">
                  <Label htmlFor="muster-assembly" className="text-xs font-semibold">Designated Assembly Point</Label>
                  <Input
                    id="muster-assembly"
                    value={musterAssemblyPoint}
                    onChange={(e) => setMusterAssemblyPoint(e.target.value)}
                    className="h-9 text-xs"
                    required
                  />
                </div>

                <div className="space-y-1.5">
                  <Label htmlFor="muster-notes" className="text-xs font-semibold">Initial Notes &amp; Dispatch Instructions</Label>
                  <Textarea
                    id="muster-notes"
                    value={musterNotes}
                    onChange={(e) => setMusterNotes(e.target.value)}
                    placeholder="Special instructions for emergency responders..."
                    rows={2}
                    className="text-xs resize-none"
                  />
                </div>
              </div>

              <DialogFooter className="gap-2 sm:gap-0 pt-2 border-t">
                <Button type="button" variant="outline" size="sm" onClick={() => setStartMusterOpen(false)}>
                  Cancel
                </Button>
                <Button
                  type="submit"
                  size="sm"
                  disabled={submittingMuster}
                  className="gap-2 bg-rose-600 hover:bg-rose-700 text-white font-bold"
                >
                  <Siren className="w-4 h-4" />
                  <span>{submittingMuster ? 'Activating...' : 'Begin Emergency Muster'}</span>
                </Button>
              </DialogFooter>
            </form>
          </DialogContent>
        </Dialog>

        {/* Status Note Dialog */}
        <Dialog open={noteDialogOpen} onOpenChange={setNoteDialogOpen}>
          <DialogContent className="sm:max-w-[420px]">
            <DialogHeader>
              <DialogTitle className="text-base font-bold">
                Mark {selectedRollCall?.name} as {targetStatus.replace('_', ' ').toUpperCase()}
              </DialogTitle>
              <DialogDescription className="text-xs">
                Record safety or evacuation details for first responders.
              </DialogDescription>
            </DialogHeader>

            <div className="space-y-3 py-2">
              <Textarea
                placeholder="e.g. Wheelchair assistance required, with family at Clubhouse..."
                value={occupantNote}
                onChange={(e) => setOccupantNote(e.target.value)}
                rows={3}
                className="text-xs resize-none"
              />
            </div>

            <DialogFooter className="gap-2 sm:gap-0">
              <Button type="button" variant="outline" size="sm" onClick={() => setNoteDialogOpen(false)}>
                Cancel
              </Button>
              <Button
                type="button"
                size="sm"
                onClick={() => {
                  if (selectedRollCall) {
                    handleUpdateStatus(selectedRollCall.occupant_id, targetStatus, occupantNote);
                  }
                  setNoteDialogOpen(false);
                }}
                className="bg-primary text-primary-foreground font-semibold"
              >
                Save Status
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      </div>
    </DashboardLayout>
  );
}
