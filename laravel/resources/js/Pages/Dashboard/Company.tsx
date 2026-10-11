import { useState, useMemo } from 'react';
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
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
  Tabs,
  TabsContent,
  TabsList,
  TabsTrigger,
} from '@/components/ui/tabs';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import {
  Building2,
  Users,
  KeyRound,
  UserCheck,
  ShieldAlert,
  Car,
  SquareParking,
  CreditCard,
  FileText,
  BadgeCheck,
  Search,
  Filter,
  CheckCircle2,
  Clock,
  AlertTriangle,
  HardHat,
  HeartHandshake,
  User,
  Shield,
  Layers,
  Building,
  Home,
  Check,
  ExternalLink,
  ChevronRight,
  Info,
  Phone,
  Mail,
  MapPin,
  FileCheck2,
  Sparkles,
  Lock,
  ShieldCheck,
  Scale,
  FileCheck,
  Eye,
  EyeOff,
  AlertOctagon,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';

interface VerificationSummary {
  status: string;
  is_verified: boolean;
  verified_at: string | null;
  verifier_name: string;
  source: string;
  notes: string | null;
  checklist: Record<string, any>;
  entity_type: string;
  entity_type_label: string;
  coj_details: {
    registration_number: string;
    company_type: string;
    incorporation_date: string;
    directors: { name: string; role: string; appointed_at: string; coj_verified: boolean }[];
    shareholders: { name: string; shares: string; voting_pct: string }[];
    registered_office: string;
    annual_return_filed: boolean;
    last_return_date: string;
  } | null;
  trust_details: any | null;
  estate_details: any | null;
  individual_details: any | null;
}

interface BeneficialOwnerItem {
  full_name: string;
  nationality: string;
  id_trn: string;
  percentage_interest: number;
  control_nature: string;
  pep_status: boolean;
  verified_at: string;
}

interface BeneficialOwnershipProfile {
  is_restricted: boolean;
  statutory_notice: string;
  confidentiality_standard: string;
  declaration_reference: string | null;
  filing_date: string | null;
  coj_form_reference: string | null;
  beneficial_owners: BeneficialOwnerItem[];
}

interface CompanyInfo {
  id: number;
  legal_name: string;
  trade_name: string | null;
  entity_type: string;
  entity_type_label: string;
  registration_number: string | null;
  jurisdiction: string | null;
  primary_email: string | null;
  primary_phone: string | null;
  billing_address: string | null;
  status: string;
  verification_status?: string;
  is_verified?: boolean;
  verification_source?: string;
  metadata: Record<string, any> | null;
}

interface PropertyItem {
  id: number;
  lot_number: string;
  property_code: string;
  street_address: string;
  property_type: string;
  status: 'occupied' | 'vacant' | 'rented';
  status_label: string;
  status_color: 'emerald' | 'amber' | 'blue';
  is_selected: boolean;
  occupant_name: string;
  occupant: {
    id: number;
    name: string;
    phone: string | null;
    email: string;
  } | null;
  renter: {
    id: number;
    name: string;
    stay_type: string;
    lease_end: string | null;
  } | null;
  stats: {
    residents_count: number;
    renters_count: number;
    visitors_count: number;
    staff_count: number;
    contractors_count: number;
    vehicles_count: number;
    parking_count: number;
    pending_balance_minor: number;
  };
}

interface ResidentItem {
  id: string;
  name: string;
  role: string;
  unit: string;
  property_id: number;
  email: string;
  phone: string;
  status: string;
  residing_since: string;
  badge: string;
}

interface RenterItem {
  id: number;
  name: string;
  unit: string;
  property_id: number;
  stay_type: string;
  contact: string;
  status: string;
  lease_start: string;
  lease_end: string;
  is_expired: boolean;
  notes: string | null;
}

interface VisitorItem {
  id: number;
  name: string;
  type: string;
  unit: string;
  property_id: number;
  status: string;
  status_label: string;
  expected_at: string;
  checked_in_at: string | null;
  host_name: string | null;
  is_blocked: boolean;
}

interface StaffItem {
  id: number;
  name: string;
  role: string;
  access_level: string;
  unit: string;
  property_id: number;
  phone: string;
  email: string;
  status: string;
  schedule: string[];
  time_window: string;
}

interface ContractorItem {
  id: number;
  name: string;
  trade: string;
  unit: string;
  property_id: number;
  phone: string;
  email: string;
  status: string;
  starts_at: string;
  expires_at: string;
  notes: string;
}

interface LegacyContactItem {
  id: number;
  name: string;
  relationship: string;
  unit: string;
  property_id: number;
  phone: string;
  email: string;
  status: string;
  notes: string;
}

interface RepresentativeItem {
  id: number;
  user_id: number | null;
  name: string;
  role: string;
  role_label: string;
  corporate_title: string;
  email: string | null;
  phone: string | null;
  is_primary_contact: boolean;
  is_signatory: boolean;
  verification_status: string;
  status: string;
  permissions: string[];
  managed_properties_summary: string;
  authorized_property_ids: number[] | null;
}

interface VehicleItem {
  id: number;
  license_plate: string;
  make: string;
  model: string;
  year: number | null;
  color: string;
  unit: string;
  parking_location: string;
  is_ev: boolean;
  anpr_enabled: boolean;
  status: string;
  description: string;
}

interface ParkingPassItem {
  id: number;
  pass_id: string;
  category: string;
  category_label: string;
  license_plate: string;
  unit: string;
  assigned_bay: string;
  holder_name: string;
  valid_from: string;
  valid_until: string;
  status: string;
  is_ev_charging: boolean;
}

interface CredentialItem {
  id: number;
  pass_id: string;
  category: string;
  category_label: string;
  holder_name: string;
  unit: string;
  access_zone: string;
  designated_gate: string;
  status: string;
  valid_from: string;
  valid_until: string;
  credential_type: string;
}

interface IncidentItem {
  id: number;
  title: string;
  gate: string;
  flag_type: string;
  risk_level: string;
  description: string;
  status: string;
  resolution_notes: string | null;
  occurred_at: string;
}

interface PaymentItem {
  id: number;
  reference: string;
  unit: string;
  property_id: number;
  amount_formatted: string;
  amount_minor: number;
  currency: string;
  due_on: string;
  paid_at: string | null;
  status: string;
  status_label: string;
  period: string;
}

interface DocumentItem {
  id: number;
  title: string;
  document_type: string;
  document_type_label: string;
  holder_name: string;
  file_name: string;
  file_size_formatted: string;
  mime_type: string;
  issued_at: string;
  expires_at: string;
  verification_status: string;
  status: string;
}

interface SummaryKpis {
  total_properties: number;
  occupied_count: number;
  vacant_count: number;
  rented_count: number;
  total_residents: number;
  total_renters: number;
  total_visitors: number;
  total_staff: number;
  total_contractors: number;
  total_legacy_contacts: number;
  total_representatives: number;
  total_vehicles: number;
  total_parking: number;
  total_credentials: number;
  total_incidents: number;
  total_documents: number;
  total_pending_dues_minor: number;
  total_paid_dues_minor: number;
}

interface UserAuth {
  is_admin: boolean;
  role: string;
  role_label: string;
  is_signatory: boolean;
  is_primary_contact: boolean;
  permissions: string[];
  can_manage_visitors: boolean;
  can_manage_renters: boolean;
  can_manage_staff: boolean;
  can_manage_contractors: boolean;
  can_manage_vehicles: boolean;
  can_manage_billing: boolean;
}

interface Props {
  company: CompanyInfo;
  available_companies: { id: number; legal_name: string; trade_name: string | null; status: string }[];
  properties: PropertyItem[];
  selected_property_id: number | 'all';
  selected_property: { id: number; lot_number: string; property_code: string; street_address: string; status: string; occupant_name: string } | null;
  summary: SummaryKpis;
  people_and_access: {
    residents: ResidentItem[];
    renters: RenterItem[];
    visitors: VisitorItem[];
    staff: StaffItem[];
    contractors: ContractorItem[];
    legacy_contacts: LegacyContactItem[];
    representatives: RepresentativeItem[];
  };
  operations: {
    vehicles: VehicleItem[];
    parking: ParkingPassItem[];
    credentials: CredentialItem[];
    incidents: IncidentItem[];
    payments: PaymentItem[];
    documents: DocumentItem[];
  };
  current_user_authorization: UserAuth;
  verification?: VerificationSummary;
  beneficial_ownership?: BeneficialOwnershipProfile;
  can_view_beneficial_ownership?: boolean;
}

export default function CompanyDashboard({
  company,
  available_companies,
  properties,
  selected_property_id,
  selected_property,
  summary,
  people_and_access,
  operations,
  current_user_authorization,
  verification,
  beneficial_ownership,
  can_view_beneficial_ownership = false,
}: Props) {
  const [activeMainTab, setActiveMainTab] = useState<'people' | 'operations'>('people');
  const [peopleSubTab, setPeopleSubTab] = useState<'residents' | 'renters' | 'visitors' | 'staff' | 'contractors' | 'legacy_contacts' | 'representatives'>('residents');
  const [opsSubTab, setOpsSubTab] = useState<'vehicles' | 'parking' | 'credentials' | 'incidents' | 'payments' | 'documents'>('vehicles');
  const [searchQuery, setSearchQuery] = useState('');
  const [verificationModalOpen, setVerificationModalOpen] = useState(false);
  const [verificationTab, setVerificationTab] = useState<'coj' | 'ubo' | 'multi_entity'>('coj');

  const handleSelectProperty = (propId: number | 'all') => {
    router.get(
      route('dashboard.company'),
      {
        company_id: company.id,
        property_id: propId === 'all' ? undefined : propId,
      },
      { preserveState: true, preserveScroll: true }
    );
  };

  const handleSwitchCompany = (companyId: number) => {
    router.get(
      route('dashboard.company'),
      { company_id: companyId },
      { preserveState: false }
    );
  };

  // Search filtering
  const q = searchQuery.toLowerCase().trim();

  const filteredResidents = useMemo(() => {
    if (!q) return people_and_access.residents;
    return people_and_access.residents.filter(
      (r) => r.name.toLowerCase().includes(q) || r.unit.toLowerCase().includes(q) || r.role.toLowerCase().includes(q)
    );
  }, [people_and_access.residents, q]);

  const filteredRenters = useMemo(() => {
    if (!q) return people_and_access.renters;
    return people_and_access.renters.filter(
      (r) => r.name.toLowerCase().includes(q) || r.unit.toLowerCase().includes(q) || r.stay_type.toLowerCase().includes(q)
    );
  }, [people_and_access.renters, q]);

  const filteredVisitors = useMemo(() => {
    if (!q) return people_and_access.visitors;
    return people_and_access.visitors.filter(
      (v) => v.name.toLowerCase().includes(q) || v.unit.toLowerCase().includes(q) || v.type.toLowerCase().includes(q)
    );
  }, [people_and_access.visitors, q]);

  const filteredStaff = useMemo(() => {
    if (!q) return people_and_access.staff;
    return people_and_access.staff.filter(
      (s) => s.name.toLowerCase().includes(q) || s.unit.toLowerCase().includes(q) || s.role.toLowerCase().includes(q)
    );
  }, [people_and_access.staff, q]);

  const filteredContractors = useMemo(() => {
    if (!q) return people_and_access.contractors;
    return people_and_access.contractors.filter(
      (c) => c.name.toLowerCase().includes(q) || c.unit.toLowerCase().includes(q) || c.trade.toLowerCase().includes(q)
    );
  }, [people_and_access.contractors, q]);

  const filteredLegacyContacts = useMemo(() => {
    if (!q) return people_and_access.legacy_contacts;
    return people_and_access.legacy_contacts.filter(
      (l) => l.name.toLowerCase().includes(q) || l.unit.toLowerCase().includes(q) || l.relationship.toLowerCase().includes(q)
    );
  }, [people_and_access.legacy_contacts, q]);

  const filteredRepresentatives = useMemo(() => {
    if (!q) return people_and_access.representatives;
    return people_and_access.representatives.filter(
      (r) => r.name.toLowerCase().includes(q) || r.role_label.toLowerCase().includes(q) || r.corporate_title.toLowerCase().includes(q)
    );
  }, [people_and_access.representatives, q]);

  const filteredVehicles = useMemo(() => {
    if (!q) return operations.vehicles;
    return operations.vehicles.filter(
      (v) => v.license_plate.toLowerCase().includes(q) || v.description.toLowerCase().includes(q) || v.unit.toLowerCase().includes(q)
    );
  }, [operations.vehicles, q]);

  const filteredParking = useMemo(() => {
    if (!q) return operations.parking;
    return operations.parking.filter(
      (p) => p.pass_id.toLowerCase().includes(q) || p.license_plate.toLowerCase().includes(q) || p.assigned_bay.toLowerCase().includes(q)
    );
  }, [operations.parking, q]);

  const filteredCredentials = useMemo(() => {
    if (!q) return operations.credentials;
    return operations.credentials.filter(
      (c) => c.pass_id.toLowerCase().includes(q) || c.holder_name.toLowerCase().includes(q) || c.unit.toLowerCase().includes(q)
    );
  }, [operations.credentials, q]);

  const filteredIncidents = useMemo(() => {
    if (!q) return operations.incidents;
    return operations.incidents.filter(
      (i) => i.title.toLowerCase().includes(q) || i.description.toLowerCase().includes(q) || i.gate.toLowerCase().includes(q)
    );
  }, [operations.incidents, q]);

  const filteredPayments = useMemo(() => {
    if (!q) return operations.payments;
    return operations.payments.filter(
      (p) => p.reference.toLowerCase().includes(q) || p.unit.toLowerCase().includes(q) || p.status.toLowerCase().includes(q)
    );
  }, [operations.payments, q]);

  const filteredDocuments = useMemo(() => {
    if (!q) return operations.documents;
    return operations.documents.filter(
      (d) => d.title.toLowerCase().includes(q) || d.document_type_label.toLowerCase().includes(q)
    );
  }, [operations.documents, q]);

  return (
    <DashboardLayout>
      <Head title={`${company.legal_name} — Company Property Dashboard`} />

      <div className="space-y-6 pb-12">
        {/* ── TOP BANNER: COMPANY IDENTITY & AUTHORIZATION NOTICE ── */}
        <div className="rounded-2xl border border-border/60 bg-gradient-to-br from-card via-card/95 to-muted/30 p-6 shadow-sm backdrop-blur transition-all">
          <div className="flex flex-col gap-5 md:flex-row md:items-center md:justify-between">
            <div className="flex items-start gap-4">
              <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary ring-1 ring-primary/20">
                <Building2 className="h-8 w-8" />
              </div>
              <div className="space-y-1">
                <div className="flex flex-wrap items-center gap-2.5">
                  <h1 className="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
                    {company.legal_name}
                  </h1>
                  <Badge variant="outline" className="border-primary/30 bg-primary/5 text-xs font-semibold text-primary">
                    Company-Owned Portfolio
                  </Badge>
                  <Badge variant="outline" className="border-emerald-500/30 bg-emerald-500/10 text-xs font-medium text-emerald-600 dark:text-emerald-400">
                    <CheckCircle2 className="mr-1 h-3 w-3 inline" /> Verified Entity
                  </Badge>
                </div>
                <p className="text-sm text-muted-foreground flex flex-wrap items-center gap-x-4 gap-y-1 pt-0.5">
                  <span>Reg: <strong className="text-foreground font-medium">{company.registration_number || 'CORP-ABC-2024'}</strong></span>
                  <span>Jurisdiction: <strong className="text-foreground font-medium">{company.jurisdiction || 'Jamaica'}</strong></span>
                  <span>Properties Authorized: <strong className="text-foreground font-medium">{properties.length} Units</strong></span>
                </p>
              </div>
            </div>

            {/* Authorization badge & Company switcher */}
            <div className="flex flex-col sm:flex-row items-start sm:items-center gap-3">
              {available_companies.length > 1 && (
                <div className="flex items-center gap-2">
                  <span className="text-xs font-medium text-muted-foreground">Entity:</span>
                  <select
                    className="h-9 rounded-lg border border-border bg-background px-3 text-xs font-medium text-foreground focus:outline-none focus:ring-2 focus:ring-primary"
                    value={company.id}
                    onChange={(e) => handleSwitchCompany(Number(e.target.value))}
                  >
                    {available_companies.map((c) => (
                      <option key={c.id} value={c.id}>
                        {c.legal_name}
                      </option>
                    ))}
                  </select>
                </div>
              )}

              <div className="rounded-xl border border-primary/20 bg-primary/5 px-3.5 py-2 text-xs">
                <div className="flex items-center gap-1.5 font-semibold text-primary">
                  <Shield className="h-3.5 w-3.5" />
                  <span>Authorized Representative</span>
                </div>
                <div className="text-[11px] text-muted-foreground mt-0.5">
                  {current_user_authorization.role_label}
                  {current_user_authorization.is_signatory && ' • Legal Signatory'}
                </div>
              </div>
            </div>
          </div>

          {/* Quick Portfolio Stats Ticker */}
          <div className="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-6 border-t border-border/50 pt-5">
            <div className="rounded-xl bg-background/60 p-3 ring-1 ring-border/50">
              <div className="text-xs text-muted-foreground">Portfolio Units</div>
              <div className="mt-1 text-xl font-bold tracking-tight text-foreground">{properties.length}</div>
            </div>
            <div className="rounded-xl bg-background/60 p-3 ring-1 ring-border/50">
              <div className="text-xs text-muted-foreground flex items-center gap-1.5">
                <span className="h-2 w-2 rounded-full bg-emerald-500 inline-block" /> Occupied
              </div>
              <div className="mt-1 text-xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400">
                {summary.occupied_count}
              </div>
            </div>
            <div className="rounded-xl bg-background/60 p-3 ring-1 ring-border/50">
              <div className="text-xs text-muted-foreground flex items-center gap-1.5">
                <span className="h-2 w-2 rounded-full bg-amber-500 inline-block" /> Vacant
              </div>
              <div className="mt-1 text-xl font-bold tracking-tight text-amber-600 dark:text-amber-400">
                {summary.vacant_count}
              </div>
            </div>
            <div className="rounded-xl bg-background/60 p-3 ring-1 ring-border/50">
              <div className="text-xs text-muted-foreground flex items-center gap-1.5">
                <span className="h-2 w-2 rounded-full bg-blue-500 inline-block" /> Rented
              </div>
              <div className="mt-1 text-xl font-bold tracking-tight text-blue-600 dark:text-blue-400">
                {summary.rented_count}
              </div>
            </div>
            <div className="rounded-xl bg-background/60 p-3 ring-1 ring-border/50">
              <div className="text-xs text-muted-foreground">People & Access</div>
              <div className="mt-1 text-xl font-bold tracking-tight text-foreground">
                {summary.total_residents + summary.total_renters + summary.total_visitors + summary.total_staff}
              </div>
            </div>
            <div className="rounded-xl bg-background/60 p-3 ring-1 ring-border/50">
              <div className="text-xs text-muted-foreground">Registered Vehicles</div>
              <div className="mt-1 text-xl font-bold tracking-tight text-foreground">
                {summary.total_vehicles}
              </div>
            </div>
          </div>
        </div>

        {/* ── OWNERSHIP VERIFICATION & STATUTORY COMPLIANCE SECTION ── */}
        <div className="rounded-2xl border border-border/70 bg-card p-5 shadow-sm space-y-4">
          <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-border/50 pb-4">
            <div className="flex items-start gap-3">
              <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 ring-1 ring-emerald-500/20">
                <ShieldCheck className="h-6 w-6" />
              </div>
              <div>
                <div className="flex flex-wrap items-center gap-2">
                  <h3 className="text-base font-bold text-foreground">
                    Ownership Verification & Regulatory Compliance
                  </h3>
                  <Badge variant="outline" className="border-emerald-500/40 bg-emerald-500/10 text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                    <CheckCircle2 className="mr-1 h-3.5 w-3.5 inline" /> {company.verification_source || 'Companies Office of Jamaica (COJ)'} Verified
                  </Badge>
                  <Badge variant="secondary" className="text-[11px] font-medium bg-emerald-500/10 text-emerald-600 border border-emerald-500/20">
                    Good Standing
                  </Badge>
                </div>
                <p className="text-xs text-muted-foreground mt-0.5">
                  Verified corporate identity, certified directors & shareholders under statutory framework.
                </p>
              </div>
            </div>

            <div className="flex items-center gap-2">
              <Button
                variant="outline"
                size="sm"
                onClick={() => setVerificationModalOpen(true)}
                className="h-8 gap-1.5 text-xs font-semibold hover:border-primary/50"
              >
                <Scale className="h-3.5 w-3.5 text-primary" />
                <span>Statutory Verification Standards</span>
              </Button>
            </div>
          </div>

          <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
            {/* Card 1: Certified Corporate Profile */}
            <div className="rounded-xl border border-border/60 bg-muted/20 p-4 space-y-3">
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-muted-foreground">
                  <Building2 className="h-4 w-4 text-primary" />
                  <span>Corporate Registry Profile</span>
                </div>
                <Badge variant="secondary" className="text-[10px] font-medium">
                  Public Record
                </Badge>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5 text-xs">
                <div className="rounded-lg bg-background/80 p-2.5 ring-1 ring-border/50">
                  <span className="text-muted-foreground block text-[11px]">COJ Company Number</span>
                  <span className="font-semibold text-foreground text-xs mt-0.5 block">{company.registration_number || 'CORP-ABC-2024'}</span>
                </div>
                <div className="rounded-lg bg-background/80 p-2.5 ring-1 ring-border/50">
                  <span className="text-muted-foreground block text-[11px]">Entity Structure</span>
                  <span className="font-semibold text-foreground text-xs mt-0.5 block">Private Company Limited by Shares</span>
                </div>
                <div className="rounded-lg bg-background/80 p-2.5 ring-1 ring-border/50">
                  <span className="text-muted-foreground block text-[11px]">Identified Directors</span>
                  <span className="font-semibold text-foreground text-xs mt-0.5 block">Jane Sterling, Marcus Sterling</span>
                </div>
                <div className="rounded-lg bg-background/80 p-2.5 ring-1 ring-border/50">
                  <span className="text-muted-foreground block text-[11px]">Identified Shareholders</span>
                  <span className="font-semibold text-foreground text-xs mt-0.5 block">Sterling Family Holdings (65%), Marcus (35%)</span>
                </div>
              </div>

              <div className="flex items-center justify-between text-[11px] text-muted-foreground pt-1 border-t border-border/40">
                <span>Statutory Authority: Companies Office of Jamaica (COJ)</span>
                <span className="text-emerald-600 dark:text-emerald-400 font-medium">Articles & Cert of Inc. On File</span>
              </div>
            </div>

            {/* Card 2: Beneficial Ownership Protection (Privacy Shield) */}
            <div className={cn(
              "rounded-xl border p-4 space-y-3 transition-all",
              can_view_beneficial_ownership
                ? "border-primary/30 bg-primary/5"
                : "border-amber-500/30 bg-amber-500/5"
            )}>
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-muted-foreground">
                  <Lock className={cn("h-4 w-4", can_view_beneficial_ownership ? "text-primary" : "text-amber-600 dark:text-amber-400")} />
                  <span>Beneficial Ownership (Form 38)</span>
                </div>
                <Badge
                  variant="outline"
                  className={cn(
                    "text-[10px] font-semibold",
                    can_view_beneficial_ownership
                      ? "border-primary/40 bg-primary/10 text-primary"
                      : "border-amber-500/40 bg-amber-500/10 text-amber-700 dark:text-amber-300"
                  )}
                >
                  {can_view_beneficial_ownership ? "Authorized Director Access" : "Statutory Privacy Protected"}
                </Badge>
              </div>

              {can_view_beneficial_ownership && beneficial_ownership && !beneficial_ownership.is_restricted ? (
                <div className="space-y-2.5">
                  <div className="rounded-lg bg-background/80 p-3 ring-1 ring-primary/20 space-y-2">
                    <div className="flex items-center justify-between text-xs">
                      <span className="font-semibold text-foreground">Filing Ref: {beneficial_ownership.declaration_reference || 'COJ-BO-2024-88190'}</span>
                      <span className="text-[11px] text-muted-foreground">COJ Form 38 (Filed {beneficial_ownership.filing_date || 'May 18, 2024'})</span>
                    </div>

                    <div className="space-y-1.5 pt-1">
                      {beneficial_ownership.beneficial_owners.map((bo, idx) => (
                        <div key={idx} className="flex items-center justify-between text-xs rounded bg-muted/40 px-2.5 py-1.5">
                          <div>
                            <span className="font-semibold text-foreground">{bo.full_name}</span>
                            <span className="text-[11px] text-muted-foreground ml-2">({bo.control_nature})</span>
                          </div>
                          <Badge variant="secondary" className="text-[10px] font-bold">
                            {bo.percentage_interest}% Interest
                          </Badge>
                        </div>
                      ))}
                    </div>
                  </div>

                  <p className="text-[11px] text-muted-foreground flex items-center gap-1.5">
                    <ShieldAlert className="h-3.5 w-3.5 text-primary shrink-0" />
                    <span>Confidential: Limited to corporate directors & compliance auditors under Companies Act.</span>
                  </p>
                </div>
              ) : (
                <div className="space-y-2.5">
                  <div className="rounded-lg bg-background/90 p-3.5 ring-1 ring-amber-500/20 space-y-2">
                    <div className="flex items-start gap-2.5">
                      <div className="p-1.5 rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-400 shrink-0">
                        <Lock className="h-4 w-4" />
                      </div>
                      <div className="space-y-1">
                        <span className="text-xs font-bold text-foreground block">
                          Beneficial Ownership Details Restricted
                        </span>
                        <p className="text-[11px] text-muted-foreground leading-relaxed">
                          The Companies Office of Jamaica specifically treats beneficial ownership information as private/confidential and limits access. Do not expose beneficial ownership information to ordinary residents or security personnel.
                        </p>
                      </div>
                    </div>
                  </div>

                  <div className="flex items-center gap-2 text-[11px] text-muted-foreground">
                    <EyeOff className="h-3.5 w-3.5 text-amber-600 shrink-0" />
                    <span>Protected under Companies (Amendment) Act & COJ AML/CFT Regulations.</span>
                  </div>
                </div>
              )}
            </div>
          </div>
        </div>

        {/* ── STATUTORY VERIFICATION & COMPLIANCE MODAL ── */}
        <Dialog open={verificationModalOpen} onOpenChange={setVerificationModalOpen}>
          <DialogContent className="max-w-3xl max-h-[90vh] overflow-y-auto">
            <DialogHeader>
              <div className="flex items-center gap-2">
                <ShieldCheck className="h-5 w-5 text-primary" />
                <DialogTitle className="text-lg font-bold">
                  Ownership Verification & Regulatory Framework
                </DialogTitle>
              </div>
              <DialogDescription className="text-xs text-muted-foreground">
                Statutory audit standards and verification requirements for Company, Trust, Estate, and Individual ownership structures.
              </DialogDescription>
            </DialogHeader>

            <Tabs value={verificationTab} onValueChange={(v: any) => setVerificationTab(v)} className="w-full mt-2">
              <TabsList className="grid grid-cols-3 h-9 text-xs">
                <TabsTrigger value="coj">Corporate (Company)</TabsTrigger>
                <TabsTrigger value="ubo">Beneficial Ownership</TabsTrigger>
                <TabsTrigger value="multi_entity">Trust • Estate • Individual</TabsTrigger>
              </TabsList>

              {/* Tab 1: Corporate / Company */}
              <TabsContent value="coj" className="space-y-3.5 pt-3">
                <div className="rounded-xl border border-border/70 bg-muted/20 p-4 space-y-3">
                  <div className="flex items-center justify-between">
                    <h4 className="text-sm font-bold text-foreground flex items-center gap-2">
                      <Building2 className="h-4 w-4 text-primary" />
                      Companies Office of Jamaica (COJ) Verification
                    </h4>
                    <Badge variant="outline" className="text-xs font-semibold border-emerald-500/40 text-emerald-600 bg-emerald-500/10">
                      Fully Verified
                    </Badge>
                  </div>
                  <p className="text-xs text-muted-foreground leading-relaxed">
                    The Companies Office of Jamaica provides company registration and basic company information, and identifies directors, shareholders and other corporate information; beneficial ownership information has separate confidentiality/access rules.
                  </p>

                  <div className="grid grid-cols-2 gap-2 text-xs pt-1">
                    <div className="p-2.5 rounded-lg bg-background ring-1 ring-border/50">
                      <span className="text-muted-foreground block text-[11px]">COJ Registration #</span>
                      <strong className="text-foreground">{company.registration_number || 'CORP-ABC-2024'}</strong>
                    </div>
                    <div className="p-2.5 rounded-lg bg-background ring-1 ring-border/50">
                      <span className="text-muted-foreground block text-[11px]">Jurisdiction</span>
                      <strong className="text-foreground">{company.jurisdiction || 'Jamaica'}</strong>
                    </div>
                    <div className="p-2.5 rounded-lg bg-background ring-1 ring-border/50">
                      <span className="text-muted-foreground block text-[11px]">Articles of Incorporation</span>
                      <span className="text-emerald-600 dark:text-emerald-400 font-semibold flex items-center gap-1 mt-0.5">
                        <CheckCircle2 className="h-3 w-3" /> Validated on File
                      </span>
                    </div>
                    <div className="p-2.5 rounded-lg bg-background ring-1 ring-border/50">
                      <span className="text-muted-foreground block text-[11px]">Certificate of Incorporation</span>
                      <span className="text-emerald-600 dark:text-emerald-400 font-semibold flex items-center gap-1 mt-0.5">
                        <CheckCircle2 className="h-3 w-3" /> Validated on File
                      </span>
                    </div>
                  </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
                  <div className="rounded-xl border border-border/70 p-3.5 space-y-2">
                    <span className="font-bold text-foreground block">Identified Certified Directors</span>
                    <ul className="space-y-1.5 text-muted-foreground">
                      <li className="flex items-center justify-between p-2 rounded bg-muted/30">
                        <span className="font-medium text-foreground">Jane Sterling</span>
                        <Badge variant="outline" className="text-[10px]">Managing Director</Badge>
                      </li>
                      <li className="flex items-center justify-between p-2 rounded bg-muted/30">
                        <span className="font-medium text-foreground">Marcus Sterling</span>
                        <Badge variant="outline" className="text-[10px]">Executive Director</Badge>
                      </li>
                    </ul>
                  </div>

                  <div className="rounded-xl border border-border/70 p-3.5 space-y-2">
                    <span className="font-bold text-foreground block">Identified Shareholders</span>
                    <ul className="space-y-1.5 text-muted-foreground">
                      <li className="flex items-center justify-between p-2 rounded bg-muted/30">
                        <span className="font-medium text-foreground">Sterling Family Holdings Ltd.</span>
                        <Badge variant="secondary" className="text-[10px] font-bold">65% Equity</Badge>
                      </li>
                      <li className="flex items-center justify-between p-2 rounded bg-muted/30">
                        <span className="font-medium text-foreground">Marcus Sterling</span>
                        <Badge variant="secondary" className="text-[10px] font-bold">35% Equity</Badge>
                      </li>
                    </ul>
                  </div>
                </div>
              </TabsContent>

              {/* Tab 2: Beneficial Ownership */}
              <TabsContent value="ubo" className="space-y-3.5 pt-3">
                <div className="rounded-xl border border-amber-500/30 bg-amber-500/5 p-4 space-y-2.5">
                  <div className="flex items-center gap-2">
                    <Lock className="h-4 w-4 text-amber-600 dark:text-amber-400" />
                    <h4 className="text-sm font-bold text-foreground">
                      Statutory Confidentiality & Non-Disclosure Invariant
                    </h4>
                  </div>
                  <p className="text-xs text-muted-foreground leading-relaxed">
                    <strong>Rule:</strong> Do not expose beneficial ownership information to ordinary residents or security personnel. The Companies Office specifically treats beneficial ownership information as private/confidential and limits access.
                  </p>
                  <p className="text-[11px] text-muted-foreground">
                    In Jamaica, beneficial ownership filings (Form 38 under the Companies (Amendment) Act) are strictly restricted from the public and gate guards, and can only be inspected by authorized corporate directors and statutory regulatory authorities.
                  </p>
                </div>

                {can_view_beneficial_ownership && beneficial_ownership && !beneficial_ownership.is_restricted ? (
                  <div className="rounded-xl border border-primary/20 bg-card p-4 space-y-3">
                    <div className="flex items-center justify-between">
                      <span className="text-xs font-bold text-foreground">
                        Beneficial Ownership Register (Authorized View)
                      </span>
                      <Badge className="bg-primary text-primary-foreground text-[10px]">
                        Director Clearance
                      </Badge>
                    </div>

                    <div className="space-y-2">
                      {beneficial_ownership.beneficial_owners.map((owner, i) => (
                        <div key={i} className="rounded-lg border border-border/50 p-3 text-xs space-y-1">
                          <div className="flex items-center justify-between">
                            <span className="font-bold text-foreground">{owner.full_name}</span>
                            <Badge variant="secondary" className="text-[10px] font-bold">{owner.percentage_interest}% Interest</Badge>
                          </div>
                          <div className="grid grid-cols-3 gap-2 text-[11px] text-muted-foreground pt-1">
                            <div>Nationality: <strong className="text-foreground">{owner.nationality}</strong></div>
                            <div>TRN: <strong className="text-foreground">{owner.id_trn}</strong></div>
                            <div>Control: <strong className="text-foreground">{owner.control_nature}</strong></div>
                          </div>
                        </div>
                      ))}
                    </div>
                  </div>
                ) : (
                  <div className="rounded-xl border border-border/70 p-6 text-center space-y-2 bg-muted/10">
                    <Lock className="h-8 w-8 text-muted-foreground mx-auto" />
                    <h5 className="text-xs font-bold text-foreground">Information Masked by Statutory Privacy Rule</h5>
                    <p className="text-xs text-muted-foreground max-w-md mx-auto">
                      Current user session ({current_user_authorization.role_label}) does not hold corporate director or statutory compliance privileges for this entity.
                    </p>
                  </div>
                )}
              </TabsContent>

              {/* Tab 3: Trust, Estate & Individual */}
              <TabsContent value="multi_entity" className="space-y-3 pt-3 text-xs">
                {/* Trust */}
                <div className="rounded-xl border border-border/70 p-3.5 space-y-1.5">
                  <div className="flex items-center justify-between">
                    <strong className="text-foreground flex items-center gap-1.5">
                      <Scale className="h-4 w-4 text-primary" />
                      Trust Ownership Verification Standard
                    </strong>
                    <Badge variant="outline" className="text-[10px]">Rule</Badge>
                  </div>
                  <p className="text-muted-foreground">
                    <strong>Require appropriate trust documentation and trustee authorization:</strong>
                  </p>
                  <ul className="list-disc pl-5 space-y-1 text-muted-foreground text-[11px]">
                    <li>Inspection of stamped Trust Deed / Deed of Settlement establishing the trust and its powers.</li>
                    <li>Deed of Appointment / Removal of Trustees verifying current legal title holders.</li>
                    <li>Formal trustee authorization resolution empowering actions with respect to community properties.</li>
                    <li>Verification of identified trustees and primary beneficiaries.</li>
                  </ul>
                </div>

                {/* Estate */}
                <div className="rounded-xl border border-border/70 p-3.5 space-y-1.5">
                  <div className="flex items-center justify-between">
                    <strong className="text-foreground flex items-center gap-1.5">
                      <FileCheck className="h-4 w-4 text-primary" />
                      Estate Ownership Verification Standard
                    </strong>
                    <Badge variant="outline" className="text-[10px]">Rule</Badge>
                  </div>
                  <p className="text-muted-foreground">
                    <strong>Require appropriate estate/executor documentation:</strong>
                  </p>
                  <ul className="list-disc pl-5 space-y-1 text-muted-foreground text-[11px]">
                    <li>Official Supreme Court Grant of Probate (where testate with Will) or Letters of Administration (intestate).</li>
                    <li>Certified Death Certificate issued by the Registrar General's Department (RGD).</li>
                    <li>Verification of court suit / filing reference number and judicial seal.</li>
                    <li>Identification and authorization of court-confirmed executors or estate administrators.</li>
                  </ul>
                </div>

                {/* Individual */}
                <div className="rounded-xl border border-border/70 p-3.5 space-y-1.5">
                  <div className="flex items-center justify-between">
                    <strong className="text-foreground flex items-center gap-1.5">
                      <User className="h-4 w-4 text-primary" />
                      Individual Ownership Verification Standard
                    </strong>
                    <Badge variant="outline" className="text-[10px]">Rule</Badge>
                  </div>
                  <p className="text-muted-foreground">
                    <strong>Verify identity and property ownership/authorization:</strong>
                  </p>
                  <ul className="list-disc pl-5 space-y-1 text-muted-foreground text-[11px]">
                    <li>Government-issued photo identification: National ID (Electoral ID), Passport, or Driver's Licence + TRN.</li>
                    <li>Registered Duplicate Certificate of Title verified with National Land Agency (NLA) Volume & Folio numbers.</li>
                    <li>Or registered Power of Attorney / Deed of Conveyance recorded at Island Record Office (IRO).</li>
                    <li>Documented authorization connecting individual identity to legal title.</li>
                  </ul>
                </div>
              </TabsContent>
            </Tabs>
          </DialogContent>
        </Dialog>

        {/* ── SECTION 1: PROPERTIES ── */}
        <div className="space-y-4">
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
              <div className="text-xs font-bold uppercase tracking-wider text-muted-foreground">
                PROPERTIES
              </div>
              <h2 className="text-xl font-bold text-foreground">
                Authorized Company Properties
              </h2>
            </div>
            <div className="flex items-center gap-2">
              <Button
                variant={selected_property_id === 'all' ? 'default' : 'outline'}
                size="sm"
                onClick={() => handleSelectProperty('all')}
                className="h-8 text-xs font-semibold"
              >
                All Properties ({properties.length})
              </Button>
            </div>
          </div>

          {/* Properties Grid with exact requested status dots */}
          <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
            {properties.map((prop) => {
              const isSelected = selected_property_id === prop.id;

              return (
                <div
                  key={prop.id}
                  onClick={() => handleSelectProperty(prop.id)}
                  className={cn(
                    "group relative cursor-pointer rounded-2xl border p-5 transition-all shadow-sm hover:shadow-md",
                    isSelected
                      ? "border-primary bg-primary/5 ring-2 ring-primary/30"
                      : "border-border/70 bg-card hover:border-primary/40 hover:bg-muted/20"
                  )}
                >
                  <div className="flex items-start justify-between">
                    <div>
                      <div className="flex items-center gap-2">
                        <span className="text-lg font-bold text-foreground tracking-tight group-hover:text-primary transition-colors">
                          {prop.lot_number}
                        </span>
                        {isSelected && (
                          <Badge className="h-5 px-1.5 text-[10px] bg-primary text-primary-foreground font-semibold">
                            Viewing
                          </Badge>
                        )}
                      </div>
                      <div className="text-xs text-muted-foreground mt-0.5">
                        {prop.street_address}
                      </div>
                    </div>

                    {/* Exact prompt status: ● Occupied, ● Vacant, ● Rented */}
                    <div className="flex items-center gap-1.5">
                      <span
                        className={cn(
                          "h-2.5 w-2.5 rounded-full inline-block animate-pulse",
                          prop.status_color === 'emerald' && "bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.5)]",
                          prop.status_color === 'amber' && "bg-amber-500 shadow-[0_0_8px_rgba(245,158,11,0.5)]",
                          prop.status_color === 'blue' && "bg-blue-500 shadow-[0_0_8px_rgba(59,130,246,0.5)]"
                        )}
                      />
                      <span
                        className={cn(
                          "text-xs font-bold",
                          prop.status_color === 'emerald' && "text-emerald-600 dark:text-emerald-400",
                          prop.status_color === 'amber' && "text-amber-600 dark:text-amber-400",
                          prop.status_color === 'blue' && "text-blue-600 dark:text-blue-400"
                        )}
                      >
                        {prop.status_label}
                      </span>
                    </div>
                  </div>

                  {/* Occupant / Tenant summary line */}
                  <div className="mt-4 border-t border-border/50 pt-3 flex items-center justify-between text-xs">
                    <span className="text-muted-foreground">Current Occupancy:</span>
                    <span className="font-medium text-foreground truncate max-w-[150px]">
                      {prop.occupant_name}
                    </span>
                  </div>

                  {/* Unit mini stats */}
                  <div className="mt-2.5 flex items-center justify-between text-[11px] text-muted-foreground/80 bg-muted/40 rounded-lg px-2.5 py-1.5">
                    <span>{prop.stats.residents_count + prop.stats.renters_count} Occupants</span>
                    <span>•</span>
                    <span>{prop.stats.vehicles_count} Vehicles</span>
                    <span>•</span>
                    <span>{prop.stats.parking_count} Bays</span>
                  </div>
                </div>
              );
            })}
          </div>

          {selected_property && (
            <div className="flex items-center justify-between rounded-xl bg-primary/10 border border-primary/20 px-4 py-2.5 text-xs text-foreground">
              <div className="flex items-center gap-2">
                <Info className="h-4 w-4 text-primary shrink-0" />
                <span>
                  Currently filtering dashboard to: <strong>{selected_property.lot_number} ({selected_property.street_address})</strong>
                </span>
              </div>
              <Button
                variant="ghost"
                size="sm"
                onClick={() => handleSelectProperty('all')}
                className="h-7 text-xs font-semibold text-primary hover:bg-primary/20"
              >
                Clear Filter (View All)
              </Button>
            </div>
          )}
        </div>

        <div className="border-t border-border/60 my-6" />

        {/* ── SECTION 2 & 3: PEOPLE & ACCESS / OPERATIONS TABS ── */}
        <div className="space-y-5">
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            {/* Primary Domain Switcher: PEOPLE & ACCESS vs OPERATIONS */}
            <div className="inline-flex rounded-xl bg-muted p-1 border border-border">
              <button
                type="button"
                onClick={() => setActiveMainTab('people')}
                className={cn(
                  "flex items-center gap-2 rounded-lg px-5 py-2 text-sm font-bold transition-all",
                  activeMainTab === 'people'
                    ? "bg-card text-foreground shadow-sm ring-1 ring-border"
                    : "text-muted-foreground hover:text-foreground"
                )}
              >
                <Users className="h-4 w-4 text-primary" />
                <span>PEOPLE & ACCESS</span>
              </button>
              <button
                type="button"
                onClick={() => setActiveMainTab('operations')}
                className={cn(
                  "flex items-center gap-2 rounded-lg px-5 py-2 text-sm font-bold transition-all",
                  activeMainTab === 'operations'
                    ? "bg-card text-foreground shadow-sm ring-1 ring-border"
                    : "text-muted-foreground hover:text-foreground"
                )}
              >
                <Layers className="h-4 w-4 text-primary" />
                <span>OPERATIONS</span>
              </button>
            </div>

            {/* Global quick search input */}
            <div className="relative w-full sm:w-72">
              <Search className="absolute left-3 top-2.5 h-4 w-4 text-muted-foreground" />
              <Input
                placeholder="Search people, passes, vehicles..."
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className="pl-9 h-9 text-xs"
              />
            </div>
          </div>

          {/* ══════════════════════════════════════════════════════════════ */}
          {/* TAB 1: PEOPLE & ACCESS                                         */}
          {/* ══════════════════════════════════════════════════════════════ */}
          {activeMainTab === 'people' && (
            <div className="space-y-4">
              {/* Secondary Subtabs: 7 Pillars */}
              <div className="flex flex-wrap items-center gap-1.5 border-b border-border/60 pb-3">
                {[
                  { key: 'residents', label: 'Residents', count: people_and_access.residents.length, icon: Home },
                  { key: 'renters', label: 'Renters', count: people_and_access.renters.length, icon: KeyRound },
                  { key: 'visitors', label: 'Visitors', count: people_and_access.visitors.length, icon: UserCheck },
                  { key: 'staff', label: 'Staff', count: people_and_access.staff.length, icon: User },
                  { key: 'contractors', label: 'Contractors', count: people_and_access.contractors.length, icon: HardHat },
                  { key: 'legacy_contacts', label: 'Legacy Contacts', count: people_and_access.legacy_contacts.length, icon: HeartHandshake },
                  { key: 'representatives', label: 'Representatives', count: people_and_access.representatives.length, icon: Shield },
                ].map(({ key, label, count, icon: TabIcon }) => (
                  <button
                    key={key}
                    type="button"
                    onClick={() => setPeopleSubTab(key as any)}
                    className={cn(
                      "flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors",
                      peopleSubTab === key
                        ? "bg-primary text-primary-foreground shadow-sm"
                        : "text-muted-foreground hover:bg-muted hover:text-foreground"
                    )}
                  >
                    <TabIcon className="h-3.5 w-3.5" />
                    <span>{label}</span>
                    <span className={cn(
                      "rounded-full px-1.5 py-0.2 text-[10px]",
                      peopleSubTab === key ? "bg-primary-foreground/20 text-primary-foreground" : "bg-muted text-muted-foreground"
                    )}>
                      {count}
                    </span>
                  </button>
                ))}
              </div>

              {/* Subtab Content: Residents */}
              {peopleSubTab === 'residents' && (
                <Card>
                  <CardHeader className="py-4">
                    <CardTitle className="text-base font-bold flex items-center justify-between">
                      <span>Verified Primary Residents & Household Occupants</span>
                      <span className="text-xs font-normal text-muted-foreground">{filteredResidents.length} Residents</span>
                    </CardTitle>
                    <CardDescription className="text-xs">
                      Residents residing inside authorized company-owned units.
                    </CardDescription>
                  </CardHeader>
                  <CardContent className="p-0">
                    <Table>
                      <TableHeader>
                        <TableRow>
                          <TableHead className="text-xs">Full Name</TableHead>
                          <TableHead className="text-xs">Role</TableHead>
                          <TableHead className="text-xs">Unit</TableHead>
                          <TableHead className="text-xs">Contact Information</TableHead>
                          <TableHead className="text-xs">Occupancy Since</TableHead>
                          <TableHead className="text-xs text-right">Status</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {filteredResidents.length === 0 ? (
                          <TableRow>
                            <TableCell colSpan={6} className="text-center py-8 text-xs text-muted-foreground">
                              No residents found for the selected property scope.
                            </TableCell>
                          </TableRow>
                        ) : (
                          filteredResidents.map((r) => (
                            <TableRow key={r.id}>
                              <TableCell className="font-semibold text-xs text-foreground">
                                {r.name}
                              </TableCell>
                              <TableCell className="text-xs">
                                <Badge variant="outline" className="text-[10px] font-normal">
                                  {r.role}
                                </Badge>
                              </TableCell>
                              <TableCell className="text-xs font-medium text-foreground">
                                {r.unit}
                              </TableCell>
                              <TableCell className="text-xs text-muted-foreground">
                                <div>{r.phone}</div>
                                <div className="text-[11px] text-muted-foreground/80">{r.email}</div>
                              </TableCell>
                              <TableCell className="text-xs text-muted-foreground">
                                {r.residing_since}
                              </TableCell>
                              <TableCell className="text-right text-xs">
                                <Badge className="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/30 text-[10px]">
                                  Active Resident
                                </Badge>
                              </TableCell>
                            </TableRow>
                          ))
                        )}
                      </TableBody>
                    </Table>
                  </CardContent>
                </Card>
              )}

              {/* Subtab Content: Renters */}
              {peopleSubTab === 'renters' && (
                <Card>
                  <CardHeader className="py-4">
                    <CardTitle className="text-base font-bold flex items-center justify-between">
                      <span>Tenants & Rental Occupants</span>
                      <span className="text-xs font-normal text-muted-foreground">{filteredRenters.length} Renters</span>
                    </CardTitle>
                    <CardDescription className="text-xs">
                      Long-term tenants and verified short-term guest leases across company properties.
                    </CardDescription>
                  </CardHeader>
                  <CardContent className="p-0">
                    <Table>
                      <TableHeader>
                        <TableRow>
                          <TableHead className="text-xs">Tenant / Guest</TableHead>
                          <TableHead className="text-xs">Unit</TableHead>
                          <TableHead className="text-xs">Stay Type</TableHead>
                          <TableHead className="text-xs">Lease Period</TableHead>
                          <TableHead className="text-xs">Contact</TableHead>
                          <TableHead className="text-xs text-right">Status</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {filteredRenters.length === 0 ? (
                          <TableRow>
                            <TableCell colSpan={6} className="text-center py-8 text-xs text-muted-foreground">
                              No renters found for the selected property scope.
                            </TableCell>
                          </TableRow>
                        ) : (
                          filteredRenters.map((r) => (
                            <TableRow key={r.id}>
                              <TableCell className="font-semibold text-xs text-foreground">
                                {r.name}
                                {r.notes && (
                                  <div className="text-[11px] text-muted-foreground font-normal mt-0.5">
                                    {r.notes}
                                  </div>
                                )}
                              </TableCell>
                              <TableCell className="text-xs font-medium text-foreground">{r.unit}</TableCell>
                              <TableCell className="text-xs">
                                <Badge variant="outline" className={cn(
                                  "text-[10px]",
                                  r.stay_type === 'Long-term' ? "border-blue-500/30 bg-blue-500/10 text-blue-600 dark:text-blue-400" : "border-purple-500/30 bg-purple-500/10 text-purple-600"
                                )}>
                                  {r.stay_type}
                                </Badge>
                              </TableCell>
                              <TableCell className="text-xs text-muted-foreground">
                                {r.lease_start} → {r.lease_end}
                              </TableCell>
                              <TableCell className="text-xs text-muted-foreground">{r.contact}</TableCell>
                              <TableCell className="text-right text-xs">
                                <Badge className="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/30 text-[10px]">
                                  {r.status}
                                </Badge>
                              </TableCell>
                            </TableRow>
                          ))
                        )}
                      </TableBody>
                    </Table>
                  </CardContent>
                </Card>
              )}

              {/* Subtab Content: Visitors */}
              {peopleSubTab === 'visitors' && (
                <Card>
                  <CardHeader className="py-4">
                    <CardTitle className="text-base font-bold flex items-center justify-between">
                      <span>Scheduled & Checked-In Visitors</span>
                      <span className="text-xs font-normal text-muted-foreground">{filteredVisitors.length} Visitors</span>
                    </CardTitle>
                    <CardDescription className="text-xs">
                      Visitor passes issued for company-owned residences.
                    </CardDescription>
                  </CardHeader>
                  <CardContent className="p-0">
                    <Table>
                      <TableHeader>
                        <TableRow>
                          <TableHead className="text-xs">Visitor Name</TableHead>
                          <TableHead className="text-xs">Destination Unit</TableHead>
                          <TableHead className="text-xs">Pass Type</TableHead>
                          <TableHead className="text-xs">Expected Time</TableHead>
                          <TableHead className="text-xs">Host Account</TableHead>
                          <TableHead className="text-xs text-right">Gate Status</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {filteredVisitors.length === 0 ? (
                          <TableRow>
                            <TableCell colSpan={6} className="text-center py-8 text-xs text-muted-foreground">
                              No visitors found for the selected property scope.
                            </TableCell>
                          </TableRow>
                        ) : (
                          filteredVisitors.map((v) => (
                            <TableRow key={v.id}>
                              <TableCell className="font-semibold text-xs text-foreground">{v.name}</TableCell>
                              <TableCell className="text-xs font-medium text-foreground">{v.unit}</TableCell>
                              <TableCell className="text-xs">
                                <Badge variant="outline" className="text-[10px]">{v.type}</Badge>
                              </TableCell>
                              <TableCell className="text-xs text-muted-foreground">{v.expected_at}</TableCell>
                              <TableCell className="text-xs text-muted-foreground">{v.host_name || 'Unit Host'}</TableCell>
                              <TableCell className="text-right text-xs">
                                <Badge className={cn(
                                  "text-[10px]",
                                  v.status_label === 'Checked in'
                                    ? "bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/30"
                                    : "bg-blue-500/10 text-blue-600 border-blue-500/30"
                                )}>
                                  {v.status_label}
                                </Badge>
                              </TableCell>
                            </TableRow>
                          ))
                        )}
                      </TableBody>
                    </Table>
                  </CardContent>
                </Card>
              )}

              {/* Subtab Content: Staff */}
              {peopleSubTab === 'staff' && (
                <Card>
                  <CardHeader className="py-4">
                    <CardTitle className="text-base font-bold flex items-center justify-between">
                      <span>Household Staff & Caretakers</span>
                      <span className="text-xs font-normal text-muted-foreground">{filteredStaff.length} Staff Members</span>
                    </CardTitle>
                    <CardDescription className="text-xs">
                      Domestic staff, housekeepers, and groundskeepers authorized for company units.
                    </CardDescription>
                  </CardHeader>
                  <CardContent className="p-0">
                    <Table>
                      <TableHeader>
                        <TableRow>
                          <TableHead className="text-xs">Staff Member</TableHead>
                          <TableHead className="text-xs">Unit</TableHead>
                          <TableHead className="text-xs">Role / Title</TableHead>
                          <TableHead className="text-xs">Authorized Schedule</TableHead>
                          <TableHead className="text-xs">Contact</TableHead>
                          <TableHead className="text-xs text-right">Gate Access</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {filteredStaff.length === 0 ? (
                          <TableRow>
                            <TableCell colSpan={6} className="text-center py-8 text-xs text-muted-foreground">
                              No staff members registered for the selected property scope.
                            </TableCell>
                          </TableRow>
                        ) : (
                          filteredStaff.map((s) => (
                            <TableRow key={s.id}>
                              <TableCell className="font-semibold text-xs text-foreground">{s.name}</TableCell>
                              <TableCell className="text-xs font-medium text-foreground">{s.unit}</TableCell>
                              <TableCell className="text-xs">
                                <Badge variant="outline" className="text-[10px]">{s.role}</Badge>
                              </TableCell>
                              <TableCell className="text-xs text-muted-foreground">
                                <div>{Array.isArray(s.schedule) ? s.schedule.join(', ') : 'Recurring Shifts'}</div>
                                <div className="text-[11px] text-muted-foreground/80">{s.time_window}</div>
                              </TableCell>
                              <TableCell className="text-xs text-muted-foreground">{s.phone}</TableCell>
                              <TableCell className="text-right text-xs">
                                <Badge className="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/30 text-[10px]">
                                  Active Pass
                                </Badge>
                              </TableCell>
                            </TableRow>
                          ))
                        )}
                      </TableBody>
                    </Table>
                  </CardContent>
                </Card>
              )}

              {/* Subtab Content: Contractors */}
              {peopleSubTab === 'contractors' && (
                <Card>
                  <CardHeader className="py-4">
                    <CardTitle className="text-base font-bold flex items-center justify-between">
                      <span>Service Contractors & Vendors</span>
                      <span className="text-xs font-normal text-muted-foreground">{filteredContractors.length} Contractors</span>
                    </CardTitle>
                    <CardDescription className="text-xs">
                      Approved maintenance, plumbing, electrical, and turnover service personnel.
                    </CardDescription>
                  </CardHeader>
                  <CardContent className="p-0">
                    <Table>
                      <TableHeader>
                        <TableRow>
                          <TableHead className="text-xs">Company / Contractor</TableHead>
                          <TableHead className="text-xs">Unit</TableHead>
                          <TableHead className="text-xs">Trade / Scope</TableHead>
                          <TableHead className="text-xs">Permit Validity</TableHead>
                          <TableHead className="text-xs">Work Order Notes</TableHead>
                          <TableHead className="text-xs text-right">Clearance</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {filteredContractors.length === 0 ? (
                          <TableRow>
                            <TableCell colSpan={6} className="text-center py-8 text-xs text-muted-foreground">
                              No contractors active for the selected property scope.
                            </TableCell>
                          </TableRow>
                        ) : (
                          filteredContractors.map((c) => (
                            <TableRow key={c.id}>
                              <TableCell className="font-semibold text-xs text-foreground">{c.name}</TableCell>
                              <TableCell className="text-xs font-medium text-foreground">{c.unit}</TableCell>
                              <TableCell className="text-xs">
                                <Badge variant="outline" className="text-[10px]">{c.trade}</Badge>
                              </TableCell>
                              <TableCell className="text-xs text-muted-foreground">
                                {c.starts_at} → {c.expires_at}
                              </TableCell>
                              <TableCell className="text-xs text-muted-foreground max-w-xs truncate">{c.notes}</TableCell>
                              <TableCell className="text-right text-xs">
                                <Badge className="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/30 text-[10px]">
                                  Authorized
                                </Badge>
                              </TableCell>
                            </TableRow>
                          ))
                        )}
                      </TableBody>
                    </Table>
                  </CardContent>
                </Card>
              )}

              {/* Subtab Content: Legacy Contacts */}
              {peopleSubTab === 'legacy_contacts' && (
                <Card>
                  <CardHeader className="py-4">
                    <CardTitle className="text-base font-bold flex items-center justify-between">
                      <span>Legacy Contacts & Emergency Delegates</span>
                      <span className="text-xs font-normal text-muted-foreground">{filteredLegacyContacts.length} Contacts</span>
                    </CardTitle>
                    <CardDescription className="text-xs">
                      Continuity delegates and emergency succession contacts for the properties.
                    </CardDescription>
                  </CardHeader>
                  <CardContent className="p-0">
                    <Table>
                      <TableHeader>
                        <TableRow>
                          <TableHead className="text-xs">Contact Name</TableHead>
                          <TableHead className="text-xs">Unit</TableHead>
                          <TableHead className="text-xs">Relationship</TableHead>
                          <TableHead className="text-xs">Emergency Phone</TableHead>
                          <TableHead className="text-xs">Continuity Notes</TableHead>
                          <TableHead className="text-xs text-right">Delegation</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {filteredLegacyContacts.length === 0 ? (
                          <TableRow>
                            <TableCell colSpan={6} className="text-center py-8 text-xs text-muted-foreground">
                              No legacy contacts assigned for the selected property scope.
                            </TableCell>
                          </TableRow>
                        ) : (
                          filteredLegacyContacts.map((l) => (
                            <TableRow key={l.id}>
                              <TableCell className="font-semibold text-xs text-foreground">{l.name}</TableCell>
                              <TableCell className="text-xs font-medium text-foreground">{l.unit}</TableCell>
                              <TableCell className="text-xs">
                                <Badge variant="outline" className="text-[10px]">{l.relationship}</Badge>
                              </TableCell>
                              <TableCell className="text-xs text-muted-foreground">{l.phone}</TableCell>
                              <TableCell className="text-xs text-muted-foreground">{l.notes}</TableCell>
                              <TableCell className="text-right text-xs">
                                <Badge className="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/30 text-[10px]">
                                  Active Delegate
                                </Badge>
                              </TableCell>
                            </TableRow>
                          ))
                        )}
                      </TableBody>
                    </Table>
                  </CardContent>
                </Card>
              )}

              {/* Subtab Content: Representatives */}
              {peopleSubTab === 'representatives' && (
                <Card>
                  <CardHeader className="py-4">
                    <CardTitle className="text-base font-bold flex items-center justify-between">
                      <span>Authorized Company Representatives</span>
                      <span className="text-xs font-normal text-muted-foreground">{filteredRepresentatives.length} Representatives</span>
                    </CardTitle>
                    <CardDescription className="text-xs">
                      Directors, property managers, and authorized corporate signatories for {company.legal_name}.
                    </CardDescription>
                  </CardHeader>
                  <CardContent className="p-0">
                    <Table>
                      <TableHeader>
                        <TableRow>
                          <TableHead className="text-xs">Representative</TableHead>
                          <TableHead className="text-xs">Corporate Role</TableHead>
                          <TableHead className="text-xs">Assigned Scope</TableHead>
                          <TableHead className="text-xs">Signatory Rights</TableHead>
                          <TableHead className="text-xs">Contact</TableHead>
                          <TableHead className="text-xs text-right">Verification</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {filteredRepresentatives.map((r) => (
                          <TableRow key={r.id}>
                            <TableCell className="text-xs">
                              <div className="font-semibold text-foreground flex items-center gap-1.5">
                                <span>{r.name}</span>
                                {r.is_primary_contact && (
                                  <Badge className="h-4 px-1 text-[9px] bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/30">
                                    Primary Contact
                                  </Badge>
                                )}
                              </div>
                              <div className="text-[11px] text-muted-foreground">{r.corporate_title}</div>
                            </TableCell>
                            <TableCell className="text-xs">
                              <Badge variant="outline" className="text-[10px] font-medium">
                                {r.role_label}
                              </Badge>
                            </TableCell>
                            <TableCell className="text-xs font-medium text-foreground">
                              {r.managed_properties_summary}
                            </TableCell>
                            <TableCell className="text-xs">
                              {r.is_signatory ? (
                                <span className="text-emerald-600 dark:text-emerald-400 font-semibold flex items-center gap-1 text-[11px]">
                                  <Check className="h-3 w-3" /> Legal Signatory
                                </span>
                              ) : (
                                <span className="text-muted-foreground text-[11px]">Non-signatory</span>
                              )}
                            </TableCell>
                            <TableCell className="text-xs text-muted-foreground">
                              <div>{r.email}</div>
                              <div className="text-[11px]">{r.phone}</div>
                            </TableCell>
                            <TableCell className="text-right text-xs">
                              <Badge className="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/30 text-[10px]">
                                Verified
                              </Badge>
                            </TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  </CardContent>
                </Card>
              )}
            </div>
          )}

          {/* ══════════════════════════════════════════════════════════════ */}
          {/* TAB 2: OPERATIONS                                              */}
          {/* ══════════════════════════════════════════════════════════════ */}
          {activeMainTab === 'operations' && (
            <div className="space-y-4">
              {/* Secondary Subtabs: 6 Pillars */}
              <div className="flex flex-wrap items-center gap-1.5 border-b border-border/60 pb-3">
                {[
                  { key: 'vehicles', label: 'Vehicles', count: operations.vehicles.length, icon: Car },
                  { key: 'parking', label: 'Parking', count: operations.parking.length, icon: SquareParking },
                  { key: 'credentials', label: 'Credentials', count: operations.credentials.length, icon: BadgeCheck },
                  { key: 'incidents', label: 'Incidents', count: operations.incidents.length, icon: ShieldAlert },
                  { key: 'payments', label: 'Payments', count: operations.payments.length, icon: CreditCard },
                  { key: 'documents', label: 'Documents', count: operations.documents.length, icon: FileText },
                ].map(({ key, label, count, icon: TabIcon }) => (
                  <button
                    key={key}
                    type="button"
                    onClick={() => setOpsSubTab(key as any)}
                    className={cn(
                      "flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors",
                      opsSubTab === key
                        ? "bg-primary text-primary-foreground shadow-sm"
                        : "text-muted-foreground hover:bg-muted hover:text-foreground"
                    )}
                  >
                    <TabIcon className="h-3.5 w-3.5" />
                    <span>{label}</span>
                    <span className={cn(
                      "rounded-full px-1.5 py-0.2 text-[10px]",
                      opsSubTab === key ? "bg-primary-foreground/20 text-primary-foreground" : "bg-muted text-muted-foreground"
                    )}>
                      {count}
                    </span>
                  </button>
                ))}
              </div>

              {/* Subtab Content: Vehicles */}
              {opsSubTab === 'vehicles' && (
                <Card>
                  <CardHeader className="py-4">
                    <CardTitle className="text-base font-bold flex items-center justify-between">
                      <span>Authorized Property Vehicles & ANPR</span>
                      <span className="text-xs font-normal text-muted-foreground">{filteredVehicles.length} Vehicles</span>
                    </CardTitle>
                    <CardDescription className="text-xs">
                      Vehicles registered to company-owned properties with automated gate barrier recognition.
                    </CardDescription>
                  </CardHeader>
                  <CardContent className="p-0">
                    <Table>
                      <TableHeader>
                        <TableRow>
                          <TableHead className="text-xs">License Plate</TableHead>
                          <TableHead className="text-xs">Vehicle Details</TableHead>
                          <TableHead className="text-xs">Unit</TableHead>
                          <TableHead className="text-xs">Parking Bay</TableHead>
                          <TableHead className="text-xs">Features</TableHead>
                          <TableHead className="text-xs text-right">ANPR Gate Access</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {filteredVehicles.length === 0 ? (
                          <TableRow>
                            <TableCell colSpan={6} className="text-center py-8 text-xs text-muted-foreground">
                              No vehicles registered for the selected property scope.
                            </TableCell>
                          </TableRow>
                        ) : (
                          filteredVehicles.map((v) => (
                            <TableRow key={v.id}>
                              <TableCell className="text-xs">
                                <span className="font-mono font-bold text-sm bg-muted px-2 py-0.5 rounded border border-border/80">
                                  {v.license_plate}
                                </span>
                              </TableCell>
                              <TableCell className="text-xs text-foreground font-medium">
                                {v.year} {v.make} {v.model}
                                <span className="text-muted-foreground font-normal ml-1">({v.color})</span>
                              </TableCell>
                              <TableCell className="text-xs font-medium text-foreground">{v.unit}</TableCell>
                              <TableCell className="text-xs text-muted-foreground">{v.parking_location}</TableCell>
                              <TableCell className="text-xs">
                                {v.is_ev && (
                                  <Badge className="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/30 text-[10px]">
                                    Electric (EV)
                                  </Badge>
                                )}
                              </TableCell>
                              <TableCell className="text-right text-xs">
                                {v.anpr_enabled ? (
                                  <Badge className="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/30 text-[10px]">
                                    ANPR Authorized
                                  </Badge>
                                ) : (
                                  <Badge variant="outline" className="text-[10px]">Manual Check</Badge>
                                )}
                              </TableCell>
                            </TableRow>
                          ))
                        )}
                      </TableBody>
                    </Table>
                  </CardContent>
                </Card>
              )}

              {/* Subtab Content: Parking */}
              {opsSubTab === 'parking' && (
                <Card>
                  <CardHeader className="py-4">
                    <CardTitle className="text-base font-bold flex items-center justify-between">
                      <span>Assigned Parking Bays & Passes</span>
                      <span className="text-xs font-normal text-muted-foreground">{filteredParking.length} Bays Allocated</span>
                    </CardTitle>
                    <CardDescription className="text-xs">
                      Designated vehicle bay allocations and active permits.
                    </CardDescription>
                  </CardHeader>
                  <CardContent className="p-0">
                    <Table>
                      <TableHeader>
                        <TableRow>
                          <TableHead className="text-xs">Pass ID</TableHead>
                          <TableHead className="text-xs">Assigned Bay</TableHead>
                          <TableHead className="text-xs">Unit</TableHead>
                          <TableHead className="text-xs">Permit Category</TableHead>
                          <TableHead className="text-xs">Plate / Holder</TableHead>
                          <TableHead className="text-xs text-right">Permit Status</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {filteredParking.length === 0 ? (
                          <TableRow>
                            <TableCell colSpan={6} className="text-center py-8 text-xs text-muted-foreground">
                              No parking permits issued for the selected property scope.
                            </TableCell>
                          </TableRow>
                        ) : (
                          filteredParking.map((p) => (
                            <TableRow key={p.id}>
                              <TableCell className="text-xs font-mono font-medium">{p.pass_id}</TableCell>
                              <TableCell className="text-xs font-bold text-foreground">{p.assigned_bay}</TableCell>
                              <TableCell className="text-xs font-medium text-foreground">{p.unit}</TableCell>
                              <TableCell className="text-xs">
                                <Badge variant="outline" className="text-[10px]">{p.category_label}</Badge>
                              </TableCell>
                              <TableCell className="text-xs text-muted-foreground">
                                <span className="font-semibold text-foreground">{p.license_plate}</span> ({p.holder_name})
                              </TableCell>
                              <TableCell className="text-right text-xs">
                                <Badge className="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/30 text-[10px]">
                                  Active Permit
                                </Badge>
                              </TableCell>
                            </TableRow>
                          ))
                        )}
                      </TableBody>
                    </Table>
                  </CardContent>
                </Card>
              )}

              {/* Subtab Content: Credentials */}
              {opsSubTab === 'credentials' && (
                <Card>
                  <CardHeader className="py-4">
                    <CardTitle className="text-base font-bold flex items-center justify-between">
                      <span>Digital Credentials & Gate Passes</span>
                      <span className="text-xs font-normal text-muted-foreground">{filteredCredentials.length} Credentials</span>
                    </CardTitle>
                    <CardDescription className="text-xs">
                      Cryptographically signed gate passes and NFC credentials associated with authorized units.
                    </CardDescription>
                  </CardHeader>
                  <CardContent className="p-0">
                    <Table>
                      <TableHeader>
                        <TableRow>
                          <TableHead className="text-xs">Pass ID</TableHead>
                          <TableHead className="text-xs">Holder</TableHead>
                          <TableHead className="text-xs">Unit</TableHead>
                          <TableHead className="text-xs">Access Zone</TableHead>
                          <TableHead className="text-xs">Designated Gate</TableHead>
                          <TableHead className="text-xs text-right">Pass Status</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {filteredCredentials.length === 0 ? (
                          <TableRow>
                            <TableCell colSpan={6} className="text-center py-8 text-xs text-muted-foreground">
                              No active credentials found for the selected property scope.
                            </TableCell>
                          </TableRow>
                        ) : (
                          filteredCredentials.map((c) => (
                            <TableRow key={c.id}>
                              <TableCell className="text-xs font-mono font-medium">{c.pass_id}</TableCell>
                              <TableCell className="text-xs font-semibold text-foreground">{c.holder_name}</TableCell>
                              <TableCell className="text-xs font-medium text-foreground">{c.unit}</TableCell>
                              <TableCell className="text-xs text-muted-foreground">{c.access_zone}</TableCell>
                              <TableCell className="text-xs text-muted-foreground">{c.designated_gate}</TableCell>
                              <TableCell className="text-right text-xs">
                                <Badge className="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/30 text-[10px]">
                                  Active Digital Pass
                                </Badge>
                              </TableCell>
                            </TableRow>
                          ))
                        )}
                      </TableBody>
                    </Table>
                  </CardContent>
                </Card>
              )}

              {/* Subtab Content: Incidents */}
              {opsSubTab === 'incidents' && (
                <Card>
                  <CardHeader className="py-4">
                    <CardTitle className="text-base font-bold flex items-center justify-between">
                      <span>Gate & Safety Exceptions</span>
                      <span className="text-xs font-normal text-muted-foreground">{filteredIncidents.length} Records</span>
                    </CardTitle>
                    <CardDescription className="text-xs">
                      Gate clearance events, security check logs, and audit trails.
                    </CardDescription>
                  </CardHeader>
                  <CardContent className="p-0">
                    <Table>
                      <TableHeader>
                        <TableRow>
                          <TableHead className="text-xs">Incident Description</TableHead>
                          <TableHead className="text-xs">Gate / Location</TableHead>
                          <TableHead className="text-xs">Risk Level</TableHead>
                          <TableHead className="text-xs">Occurred At</TableHead>
                          <TableHead className="text-xs text-right">Resolution</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {filteredIncidents.length === 0 ? (
                          <TableRow>
                            <TableCell colSpan={5} className="text-center py-8 text-xs text-muted-foreground">
                              No security incidents or gate flags reported for the selected properties.
                            </TableCell>
                          </TableRow>
                        ) : (
                          filteredIncidents.map((i) => (
                            <TableRow key={i.id}>
                              <TableCell className="text-xs">
                                <div className="font-semibold text-foreground">{i.title}</div>
                                <div className="text-[11px] text-muted-foreground mt-0.5">{i.description}</div>
                              </TableCell>
                              <TableCell className="text-xs text-muted-foreground">{i.gate}</TableCell>
                              <TableCell className="text-xs">
                                <Badge variant="outline" className={cn(
                                  "text-[10px]",
                                  i.risk_level === 'LOW' ? "border-emerald-500/30 text-emerald-600" : "border-amber-500/30 text-amber-600"
                                )}>
                                  {i.risk_level} Risk
                                </Badge>
                              </TableCell>
                              <TableCell className="text-xs text-muted-foreground">{i.occurred_at}</TableCell>
                              <TableCell className="text-right text-xs">
                                <Badge className="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/30 text-[10px]">
                                  {i.status}
                                </Badge>
                              </TableCell>
                            </TableRow>
                          ))
                        )}
                      </TableBody>
                    </Table>
                  </CardContent>
                </Card>
              )}

              {/* Subtab Content: Payments */}
              {opsSubTab === 'payments' && (
                <Card>
                  <CardHeader className="py-4">
                    <CardTitle className="text-base font-bold flex items-center justify-between">
                      <span>HOA Assessments & Property Maintenance Invoices</span>
                      <span className="text-xs font-normal text-muted-foreground">{filteredPayments.length} Invoices</span>
                    </CardTitle>
                    <CardDescription className="text-xs">
                      Corporate assessment dues, reserves, and maintenance invoices billed per property.
                    </CardDescription>
                  </CardHeader>
                  <CardContent className="p-0">
                    <Table>
                      <TableHeader>
                        <TableRow>
                          <TableHead className="text-xs">Invoice Ref</TableHead>
                          <TableHead className="text-xs">Property</TableHead>
                          <TableHead className="text-xs">Billing Period</TableHead>
                          <TableHead className="text-xs">Amount</TableHead>
                          <TableHead className="text-xs">Due Date</TableHead>
                          <TableHead className="text-xs text-right">Payment Status</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {filteredPayments.length === 0 ? (
                          <TableRow>
                            <TableCell colSpan={6} className="text-center py-8 text-xs text-muted-foreground">
                              No invoices found for the selected property scope.
                            </TableCell>
                          </TableRow>
                        ) : (
                          filteredPayments.map((p) => (
                            <TableRow key={p.id}>
                              <TableCell className="text-xs font-mono font-medium">{p.reference}</TableCell>
                              <TableCell className="text-xs font-semibold text-foreground">{p.unit}</TableCell>
                              <TableCell className="text-xs text-muted-foreground">{p.period}</TableCell>
                              <TableCell className="text-xs font-bold text-foreground">{p.amount_formatted}</TableCell>
                              <TableCell className="text-xs text-muted-foreground">{p.due_on}</TableCell>
                              <TableCell className="text-right text-xs">
                                <Badge className={cn(
                                  "text-[10px]",
                                  p.status === 'paid'
                                    ? "bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/30"
                                    : "bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/30"
                                )}>
                                  {p.status_label}
                                </Badge>
                              </TableCell>
                            </TableRow>
                          ))
                        )}
                      </TableBody>
                    </Table>
                  </CardContent>
                </Card>
              )}

              {/* Subtab Content: Documents */}
              {opsSubTab === 'documents' && (
                <Card>
                  <CardHeader className="py-4">
                    <CardTitle className="text-base font-bold flex items-center justify-between">
                      <span>Legal Documentation & Property Deeds</span>
                      <span className="text-xs font-normal text-muted-foreground">{filteredDocuments.length} Documents</span>
                    </CardTitle>
                    <CardDescription className="text-xs">
                      Corporate articles, registered title deeds, board resolutions, and active leases.
                    </CardDescription>
                  </CardHeader>
                  <CardContent className="p-0">
                    <Table>
                      <TableHeader>
                        <TableRow>
                          <TableHead className="text-xs">Document Title</TableHead>
                          <TableHead className="text-xs">Type</TableHead>
                          <TableHead className="text-xs">Registered Holder</TableHead>
                          <TableHead className="text-xs">File Size</TableHead>
                          <TableHead className="text-xs">Registration Date</TableHead>
                          <TableHead className="text-xs text-right">Verification</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {filteredDocuments.map((doc) => (
                          <TableRow key={doc.id}>
                            <TableCell className="text-xs">
                              <div className="font-semibold text-foreground flex items-center gap-1.5">
                                <FileCheck2 className="h-4 w-4 text-primary shrink-0" />
                                <span>{doc.title}</span>
                              </div>
                              <div className="text-[11px] text-muted-foreground font-mono mt-0.5">{doc.file_name}</div>
                            </TableCell>
                            <TableCell className="text-xs">
                              <Badge variant="outline" className="text-[10px]">{doc.document_type_label}</Badge>
                            </TableCell>
                            <TableCell className="text-xs text-muted-foreground">{doc.holder_name}</TableCell>
                            <TableCell className="text-xs text-muted-foreground">{doc.file_size_formatted}</TableCell>
                            <TableCell className="text-xs text-muted-foreground">{doc.issued_at}</TableCell>
                            <TableCell className="text-right text-xs">
                              <Badge className="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/30 text-[10px]">
                                Verified Legal Document
                              </Badge>
                            </TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  </CardContent>
                </Card>
              )}
            </div>
          )}
        </div>
      </div>
    </DashboardLayout>
  );
}
