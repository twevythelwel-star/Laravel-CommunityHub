import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import {
  Building2,
  HeartHandshake,
  Landmark,
  FileText,
  ShieldCheck,
  ShieldAlert,
  Lock,
  Users,
  UserPlus,
  Home,
  CheckCircle2,
  AlertCircle,
  ExternalLink,
  ChevronRight,
  Trash2,
  QrCode,
  FileBadge,
  Calendar,
  Briefcase,
  KeyRound,
  Eye,
  EyeOff,
  UserCheck,
  BadgeAlert,
  Info,
  Layers,
  Radio,
  Smartphone,
  Key,
  Clock,
  UserSquare2,
} from 'lucide-react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';

interface Representative {
  id: number;
  user_id: number | null;
  name: string;
  role: string;
  role_label: string;
  email: string | null;
  phone: string | null;
  id_document_type: string | null;
  id_document_number: string | null;
  permissions: string[];
  is_primary_contact: boolean;
  is_signatory: boolean;
  status: string;
  is_active: boolean;
  valid_until: string | null;
  authorized_properties: Array<{
    id: number;
    lot_number: string;
    street_address: string;
  }>;
}

interface HouseholdMember {
  id: number;
  name: string;
  email: string | null;
  phone: string | null;
  role_in_household: string;
  relationship_label: string | null;
  pass_category: string;
  credential_label: string;
  role_badge_class: string;
  permissions: string[];
  status: string;
  valid_until: string | null;
  gate_pass: {
    id: number;
    pass_id: string;
    category: string;
    status: string;
    token: string | null;
  } | null;
}

interface HierarchyPillarItem {
  id: string;
  source_id: number;
  pillar: 'entity_people' | 'occupants' | 'access_relationships' | 'credentials';
  functional_type: string;
  answers: {
    who: {
      name: string;
      email: string | null;
      phone: string | null;
      id_document: string;
      user_id: number | null;
    };
    relationship: {
      type: string;
      label: string;
      is_signatory: boolean;
      is_primary_contact: boolean;
    };
    scope: {
      entity_name: string;
      entity_type: string;
      properties: string[];
    };
    permissions: {
      granted: string[];
      summary: string;
    };
    validity: {
      valid_from: string;
      valid_until: string;
      status: string;
      is_active: boolean;
    };
    authorizer: {
      name: string;
      authority: string;
      source: string;
    };
  };
}

interface OwnershipProps {
  ownershipType: 'company' | 'individual' | 'trust' | 'estate';
  moduleTitle: string;
  moduleSubtitle: string;
  moduleBadge: string;
  moduleIcon: string;
  activeEntity: {
    id: number;
    legal_name: string;
    trade_name: string | null;
    entity_type: string;
    entity_type_label: string;
    registration_number: string | null;
    tax_id: string | null;
    jurisdiction: string | null;
    status: string;
    verification_status: string;
    is_verified: boolean;
    metadata: Record<string, any> | null;
  } | null;
  hierarchy: {
    entity: {
      id: number;
      name: string;
      type: string;
      type_label: string;
      registration_number: string | null;
      status: string;
    };
    properties_count: number;
    pillars: {
      entity_people: HierarchyPillarItem[];
      occupants: HierarchyPillarItem[];
      access_relationships: HierarchyPillarItem[];
      credentials: HierarchyPillarItem[];
    };
    summary: {
      total_entity_people: number;
      total_occupants: number;
      total_access_relationships: number;
      total_credentials: number;
    };
  } | null;
  verification: {
    is_verified: boolean;
    verification_status: string;
    verified_at: string | null;
    verified_by: string | null;
    verification_source: string | null;
    checklist: Record<string, boolean>;
    corporate_profile?: {
      registration_number?: string;
      incorporation_date?: string;
      good_standing?: boolean;
      directors?: Array<{ name: string; position?: string }>;
      shareholders?: Array<{ name: string; percentage?: number; type?: string }>;
    };
    trust_profile?: {
      trust_deed_reference?: string;
      settlement_date?: string;
      trustees?: Array<{ name: string }>;
    };
    estate_profile?: {
      grant_type?: string;
      court_suit_number?: string;
      deceased_name?: string;
      executors?: Array<{ name: string }>;
    };
    individual_profile?: {
      national_id_verified?: boolean;
      trn_verified?: boolean;
      title_reference?: string;
    };
  } | null;
  beneficialOwnership: {
    is_restricted: boolean;
    confidentiality_notice: string;
    data: {
      declaration_reference?: string;
      filing_date?: string;
      beneficial_owners?: Array<{
        full_name: string;
        nationality?: string;
        ownership_percentage?: number;
        nature_of_control?: string;
      }>;
    } | null;
  } | null;
  canViewBeneficialOwnership: boolean;
  representatives: Representative[];
  household: {
    id: number;
    name: string;
    property_number: string;
    address: string;
    members: HouseholdMember[];
  } | null;
  properties: Array<{
    id: number;
    lot_number: string;
    property_code: string;
    street_address: string;
    property_type: string;
    status: string;
    primary_occupant: { name: string; email: string } | null;
  }>;
  availableEntities: Array<{
    id: number;
    legal_name: string;
    entity_type: string;
    entity_type_label: string;
    property_count: number;
  }>;
  availableRoles: Array<{ value: string; label: string }>;
  availableHouseholdPermissions: Record<string, string>;
  canManage: boolean;
}

export default function Ownership({
  ownershipType,
  moduleTitle,
  moduleSubtitle,
  moduleBadge,
  activeEntity,
  hierarchy,
  verification,
  beneficialOwnership,
  canViewBeneficialOwnership,
  representatives,
  household,
  properties,
  availableEntities,
  availableRoles,
  canManage,
}: OwnershipProps) {
  const [activeTab, setActiveTab] = useState<string>('overview');
  const [isAddRepOpen, setIsAddRepOpen] = useState(false);
  const [isAddMemberOpen, setIsAddMemberOpen] = useState(false);
  const [selectedToken, setSelectedToken] = useState<string | null>(null);

  // A+ Data Model selected pillar and inspected item for 6-question breakdown
  const [selectedPillar, setSelectedPillar] = useState<'all' | 'entity_people' | 'occupants' | 'access_relationships' | 'credentials'>('all');
  const [inspectedItem, setInspectedItem] = useState<HierarchyPillarItem | null>(null);

  // Form states
  const [repForm, setRepForm] = useState({
    full_name: '',
    role: availableRoles[0]?.value || 'authorized_representative',
    email: '',
    phone: '',
    id_document_type: 'National ID / Passport',
    id_document_number: '',
    is_primary_contact: false,
    is_signatory: false,
  });

  const [memberForm, setMemberForm] = useState({
    name: '',
    email: '',
    phone: '',
    role_in_household: 'spouse',
    relationship_label: 'Spouse',
    permissions: ['invite_guests', 'view_cameras'],
  });

  const handleSwitchType = (type: string) => {
    router.get('/dashboard/ownership', { type }, { preserveState: false });
  };

  const handleSwitchEntity = (entityId: string) => {
    router.get('/dashboard/ownership', { entity_id: entityId }, { preserveState: false });
  };

  const submitRepresentative = (e: React.FormEvent) => {
    e.preventDefault();
    if (!activeEntity) return;

    router.post(
      '/dashboard/ownership/representatives',
      {
        ownership_entity_id: activeEntity.id,
        ...repForm,
      },
      {
        onSuccess: () => {
          setIsAddRepOpen(false);
          setRepForm({
            full_name: '',
            role: availableRoles[0]?.value || 'authorized_representative',
            email: '',
            phone: '',
            id_document_type: 'National ID / Passport',
            id_document_number: '',
            is_primary_contact: false,
            is_signatory: false,
          });
        },
      }
    );
  };

  const submitHouseholdMember = (e: React.FormEvent) => {
    e.preventDefault();
    router.post('/dashboard/household/members', memberForm, {
      onSuccess: () => {
        setIsAddMemberOpen(false);
        setMemberForm({
          name: '',
          email: '',
          phone: '',
          role_in_household: 'spouse',
          relationship_label: 'Spouse',
          permissions: ['invite_guests'],
        });
      },
    });
  };

  const revokeRep = (personId: number) => {
    if (confirm('Are you sure you want to revoke authorization for this person?')) {
      router.post(`/dashboard/ownership/representatives/${personId}/revoke`);
    }
  };

  const removeMember = (memberId: number) => {
    if (confirm('Remove this member from the household?')) {
      router.delete(`/dashboard/household/members/${memberId}`);
    }
  };

  // Dynamic icons
  const getEntityIcon = (type: string) => {
    switch (type) {
      case 'company':
        return <Building2 className="h-6 w-6 text-primary" />;
      case 'individual':
        return <HeartHandshake className="h-6 w-6 text-emerald-600 dark:text-emerald-400" />;
      case 'trust':
        return <Landmark className="h-6 w-6 text-amber-600 dark:text-amber-400" />;
      case 'estate':
        return <FileText className="h-6 w-6 text-purple-600 dark:text-purple-400" />;
      default:
        return <Building2 className="h-6 w-6 text-primary" />;
    }
  };

  // Compile all pillar items for the A+ view
  const allHierarchyItems: HierarchyPillarItem[] = React.useMemo(() => {
    if (!hierarchy?.pillars) return [];
    const { entity_people, occupants, access_relationships, credentials } = hierarchy.pillars;
    return [...entity_people, ...occupants, ...access_relationships, ...credentials];
  }, [hierarchy]);

  const filteredHierarchyItems = React.useMemo(() => {
    if (selectedPillar === 'all') return allHierarchyItems;
    return allHierarchyItems.filter((item) => item.pillar === selectedPillar);
  }, [allHierarchyItems, selectedPillar]);

  return (
    <DashboardLayout>
      <Head title={`${moduleTitle} — Community Hub`} />

      <div className="space-y-6 pb-12">
        {/* Top Header & Structure Switcher */}
        <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between border-b pb-5">
          <div className="space-y-1">
            <div className="flex items-center gap-2">
              <Badge variant="outline" className="px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wider bg-primary/5 text-primary border-primary/20">
                {moduleBadge}
              </Badge>
              {verification?.is_verified && (
                <Badge className="bg-emerald-600 hover:bg-emerald-600 text-white flex items-center gap-1 text-xs">
                  <CheckCircle2 className="h-3 w-3" />
                  Statutory Verified
                </Badge>
              )}
            </div>
            <div className="flex items-center gap-3 pt-1">
              <div className="p-2.5 rounded-xl bg-primary/10 border border-primary/15 shadow-sm">
                {getEntityIcon(ownershipType)}
              </div>
              <div>
                <h1 className="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
                  {moduleTitle}
                </h1>
                <p className="text-sm text-muted-foreground max-w-2xl">
                  {moduleSubtitle}
                </p>
              </div>
            </div>
          </div>

          {/* Structure Switcher Pills */}
          <div className="flex flex-col sm:flex-row items-start sm:items-center gap-2.5">
            <div className="flex items-center p-1 bg-muted/60 rounded-xl border text-xs font-medium shadow-inner">
              <button
                type="button"
                onClick={() => handleSwitchType('company')}
                className={`px-3 py-1.5 rounded-lg flex items-center gap-1.5 transition-all ${
                  ownershipType === 'company'
                    ? 'bg-background text-foreground shadow-sm font-semibold'
                    : 'text-muted-foreground hover:text-foreground'
                }`}
              >
                <Building2 className="h-3.5 w-3.5" />
                Company
              </button>
              <button
                type="button"
                onClick={() => handleSwitchType('individual')}
                className={`px-3 py-1.5 rounded-lg flex items-center gap-1.5 transition-all ${
                  ownershipType === 'individual'
                    ? 'bg-background text-foreground shadow-sm font-semibold'
                    : 'text-muted-foreground hover:text-foreground'
                }`}
              >
                <HeartHandshake className="h-3.5 w-3.5" />
                Individual
              </button>
              <button
                type="button"
                onClick={() => handleSwitchType('trust')}
                className={`px-3 py-1.5 rounded-lg flex items-center gap-1.5 transition-all ${
                  ownershipType === 'trust'
                    ? 'bg-background text-foreground shadow-sm font-semibold'
                    : 'text-muted-foreground hover:text-foreground'
                }`}
              >
                <Landmark className="h-3.5 w-3.5" />
                Trust
              </button>
              <button
                type="button"
                onClick={() => handleSwitchType('estate')}
                className={`px-3 py-1.5 rounded-lg flex items-center gap-1.5 transition-all ${
                  ownershipType === 'estate'
                    ? 'bg-background text-foreground shadow-sm font-semibold'
                    : 'text-muted-foreground hover:text-foreground'
                }`}
              >
                <FileText className="h-3.5 w-3.5" />
                Estate
              </button>
            </div>

            {/* Entity Switcher dropdown if multiple */}
            {availableEntities.length > 1 && (
              <Select
                value={String(activeEntity?.id || '')}
                onValueChange={handleSwitchEntity}
              >
                <SelectTrigger className="w-[180px] h-9 text-xs">
                  <SelectValue placeholder="Switch Entity" />
                </SelectTrigger>
                <SelectContent>
                  {availableEntities.map((ent) => (
                    <SelectItem key={ent.id} value={String(ent.id)} className="text-xs">
                      {ent.legal_name} ({ent.entity_type_label})
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            )}
          </div>
        </div>

        {/* Dynamic Navigation Tabs */}
        <Tabs value={activeTab} onValueChange={setActiveTab} className="space-y-6">
          <TabsList className="bg-muted/70 p-1 border">
            <TabsTrigger value="overview" className="gap-2 text-xs font-medium">
              <ShieldCheck className="h-3.5 w-3.5" />
              Ownership & Verification
            </TabsTrigger>

            {/* A+ Data Model Tab */}
            <TabsTrigger value="model" className="gap-2 text-xs font-medium">
              <Layers className="h-3.5 w-3.5 text-primary" />
              A+ Data Model & Invariants ({allHierarchyItems.length})
            </TabsTrigger>

            {ownershipType === 'company' && (
              <TabsTrigger value="people" className="gap-2 text-xs font-medium">
                <Users className="h-3.5 w-3.5" />
                Corporate Representatives ({representatives.length})
              </TabsTrigger>
            )}

            {ownershipType === 'individual' && (
              <TabsTrigger value="people" className="gap-2 text-xs font-medium">
                <HeartHandshake className="h-3.5 w-3.5" />
                Household & Family ({household?.members.length || 0})
              </TabsTrigger>
            )}

            {ownershipType === 'trust' && (
              <TabsTrigger value="people" className="gap-2 text-xs font-medium">
                <Landmark className="h-3.5 w-3.5" />
                Trustees & Appointees ({representatives.length})
              </TabsTrigger>
            )}

            {ownershipType === 'estate' && (
              <TabsTrigger value="people" className="gap-2 text-xs font-medium">
                <FileText className="h-3.5 w-3.5" />
                Executors & Appointees ({representatives.length})
              </TabsTrigger>
            )}

            <TabsTrigger value="properties" className="gap-2 text-xs font-medium">
              <Home className="h-3.5 w-3.5" />
              Property Portfolio ({properties.length})
            </TabsTrigger>

            <TabsTrigger value="documents" className="gap-2 text-xs font-medium">
              <FileBadge className="h-3.5 w-3.5" />
              Statutory Records
            </TabsTrigger>
          </TabsList>

          {/* TAB: A+ DATA MODEL & THE 6 CARDINAL INVARIANTS */}
          <TabsContent value="model" className="space-y-6">
            {/* Architecture Hierarchy Blueprint Card */}
            <Card className="border shadow-sm bg-gradient-to-br from-background via-muted/20 to-primary/5">
              <CardHeader className="pb-3">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                  <div>
                    <CardTitle className="text-base font-semibold flex items-center gap-2">
                      <Layers className="h-5 w-5 text-primary" />
                      The A+ Enterprise Data Architecture
                    </CardTitle>
                    <CardDescription className="text-xs">
                      Properties belong to legal ownership entities; people hold verified functional relationships answering all 6 cardinal authorization questions.
                    </CardDescription>
                  </div>
                  <Badge variant="outline" className="font-mono text-[11px] bg-background">
                    Owner = Entity, Not Person
                  </Badge>
                </div>
              </CardHeader>
              <CardContent className="space-y-4 pt-1">
                {/* Visual Tree Breakdown */}
                <div className="p-4 bg-muted/40 rounded-xl border font-mono text-xs text-foreground/90 space-y-1.5 leading-relaxed overflow-x-auto">
                  <div className="text-primary font-bold">Community (Fairview Estate)</div>
                  <div className="pl-4 text-foreground/80">└── Property Portfolio ({properties.length} Units)</div>
                  <div className="pl-8 text-foreground/90 font-semibold">
                    └── Ownership Entity ({activeEntity?.legal_name} • {activeEntity?.entity_type_label})
                  </div>
                  <div className="pl-12 text-emerald-600 dark:text-emerald-400">
                    ├── Entity People ({hierarchy?.summary.total_entity_people || 0}) — Director, Trustee, Representative, Manager, Executor
                  </div>
                  <div className="pl-12 text-sky-600 dark:text-sky-400">
                    ├── Occupants ({hierarchy?.summary.total_occupants || 0}) — Resident, Long-Term Renter, Short-Term Guest
                  </div>
                  <div className="pl-12 text-amber-600 dark:text-amber-400">
                    ├── Access Relationships ({hierarchy?.summary.total_access_relationships || 0}) — Visitor, Staff, Contractor, Legacy Contact
                  </div>
                  <div className="pl-12 text-purple-600 dark:text-purple-400">
                    └── Credentials ({hierarchy?.summary.total_credentials || 0}) — QR Code, NFC Keyfob, Mobile Wallet
                  </div>
                </div>

                {/* 4 Pillar Filter Buttons */}
                <div className="flex flex-wrap items-center gap-2 pt-2">
                  <Button
                    size="sm"
                    variant={selectedPillar === 'all' ? 'default' : 'outline'}
                    onClick={() => setSelectedPillar('all')}
                    className="text-xs h-8"
                  >
                    All Authorizations ({allHierarchyItems.length})
                  </Button>
                  <Button
                    size="sm"
                    variant={selectedPillar === 'entity_people' ? 'default' : 'outline'}
                    onClick={() => setSelectedPillar('entity_people')}
                    className="text-xs h-8 gap-1.5 text-emerald-600 dark:text-emerald-400 border-emerald-500/20"
                  >
                    <Briefcase className="h-3.5 w-3.5" />
                    Entity People ({hierarchy?.summary.total_entity_people || 0})
                  </Button>
                  <Button
                    size="sm"
                    variant={selectedPillar === 'occupants' ? 'default' : 'outline'}
                    onClick={() => setSelectedPillar('occupants')}
                    className="text-xs h-8 gap-1.5 text-sky-600 dark:text-sky-400 border-sky-500/20"
                  >
                    <Home className="h-3.5 w-3.5" />
                    Occupants ({hierarchy?.summary.total_occupants || 0})
                  </Button>
                  <Button
                    size="sm"
                    variant={selectedPillar === 'access_relationships' ? 'default' : 'outline'}
                    onClick={() => setSelectedPillar('access_relationships')}
                    className="text-xs h-8 gap-1.5 text-amber-600 dark:text-amber-400 border-amber-500/20"
                  >
                    <UserCheck className="h-3.5 w-3.5" />
                    Access Relationships ({hierarchy?.summary.total_access_relationships || 0})
                  </Button>
                  <Button
                    size="sm"
                    variant={selectedPillar === 'credentials' ? 'default' : 'outline'}
                    onClick={() => setSelectedPillar('credentials')}
                    className="text-xs h-8 gap-1.5 text-purple-600 dark:text-purple-400 border-purple-500/20"
                  >
                    <Key className="h-3.5 w-3.5" />
                    Credentials ({hierarchy?.summary.total_credentials || 0})
                  </Button>
                </div>
              </CardContent>
            </Card>

            {/* Pillar Items Grid with 6-question summary */}
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
              {filteredHierarchyItems.map((item) => (
                <Card
                  key={item.id}
                  onClick={() => setInspectedItem(item)}
                  className="border shadow-sm hover:border-primary/50 cursor-pointer transition-all flex flex-col justify-between group"
                >
                  <CardHeader className="p-4 pb-2">
                    <div className="flex items-start justify-between">
                      <div className="space-y-1">
                        <Badge
                          variant="outline"
                          className={`text-[10px] uppercase font-semibold tracking-wider ${
                            item.pillar === 'entity_people'
                              ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300 border-emerald-200'
                              : item.pillar === 'occupants'
                              ? 'bg-sky-50 text-sky-700 dark:bg-sky-950/40 dark:text-sky-300 border-sky-200'
                              : item.pillar === 'access_relationships'
                              ? 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300 border-amber-200'
                              : 'bg-purple-50 text-purple-700 dark:bg-purple-950/40 dark:text-purple-300 border-purple-200'
                          }`}
                        >
                          {item.answers.relationship.label}
                        </Badge>
                        <CardTitle className="text-sm font-semibold text-foreground group-hover:text-primary transition-colors">
                          {item.answers.who.name}
                        </CardTitle>
                      </div>
                      <Badge variant={item.answers.validity.is_active ? 'default' : 'secondary'} className="text-[10px]">
                        {item.answers.validity.status}
                      </Badge>
                    </div>
                  </CardHeader>

                  <CardContent className="p-4 pt-1 space-y-2.5 text-xs text-muted-foreground">
                    <div className="space-y-1">
                      <div>Scope: <span className="text-foreground font-medium">{item.answers.scope.properties.join(', ')}</span></div>
                      <div>Expiry: <span className="text-foreground">{item.answers.validity.valid_until}</span></div>
                      <div>Authorized By: <span className="text-foreground">{item.answers.authorizer.name}</span></div>
                    </div>

                    <div className="pt-2 border-t flex items-center justify-between text-[11px] text-primary font-medium">
                      <span>Inspect 6 Invariants</span>
                      <ChevronRight className="h-3.5 w-3.5 group-hover:translate-x-0.5 transition-transform" />
                    </div>
                  </CardContent>
                </Card>
              ))}
            </div>
          </TabsContent>

          {/* TAB 1: OVERVIEW & STATUTORY VERIFICATION */}
          <TabsContent value="overview" className="space-y-6">
            <div className="grid gap-6 md:grid-cols-3">
              {/* Card 1: Legal Entity Profile */}
              <Card className="md:col-span-2 shadow-sm border-border/80">
                <CardHeader className="pb-3">
                  <div className="flex items-center justify-between">
                    <CardTitle className="text-base font-semibold flex items-center gap-2">
                      {getEntityIcon(ownershipType)}
                      {activeEntity?.legal_name || 'Individual Property Owner'}
                    </CardTitle>
                    <Badge variant={verification?.is_verified ? 'default' : 'outline'} className="capitalize text-xs">
                      {verification?.verification_status || 'Unverified'}
                    </Badge>
                  </div>
                  <CardDescription className="text-xs">
                    {ownershipType === 'company' && 'Companies Office of Jamaica (COJ) corporate registry record.'}
                    {ownershipType === 'individual' && 'National Land Agency (NLA) registered title & primary occupancy.'}
                    {ownershipType === 'trust' && 'Deed of Trust fiduciary settlement record.'}
                    {ownershipType === 'estate' && 'Supreme Court probate court administration.'}
                  </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4 pt-1">
                  <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 p-3.5 bg-muted/30 rounded-xl border border-border/50 text-xs">
                    <div>
                      <div className="text-muted-foreground font-medium">Structure</div>
                      <div className="font-semibold text-foreground pt-0.5 capitalize">
                        {activeEntity?.entity_type_label || ownershipType}
                      </div>
                    </div>
                    <div>
                      <div className="text-muted-foreground font-medium">
                        {ownershipType === 'company' ? 'COJ Registration' : ownershipType === 'estate' ? 'Probate Suit #' : 'Reference / Tax ID'}
                      </div>
                      <div className="font-mono font-medium text-foreground pt-0.5">
                        {activeEntity?.registration_number || activeEntity?.tax_id || 'NLA-VOL-1402/FOL-88'}
                      </div>
                    </div>
                    <div>
                      <div className="text-muted-foreground font-medium">Jurisdiction</div>
                      <div className="font-medium text-foreground pt-0.5">
                        {activeEntity?.jurisdiction || 'Jamaica'}
                      </div>
                    </div>
                    <div>
                      <div className="text-muted-foreground font-medium">Status</div>
                      <div className="font-medium text-emerald-600 dark:text-emerald-400 pt-0.5 flex items-center gap-1">
                        <CheckCircle2 className="h-3 w-3" />
                        Good Standing
                      </div>
                    </div>
                  </div>

                  {/* Company specifics: Directors & Corporate Shareholders */}
                  {ownershipType === 'company' && verification?.corporate_profile && (
                    <div className="space-y-3 pt-2">
                      <div className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                        Public Corporate Registry Filings (COJ)
                      </div>
                      <div className="grid sm:grid-cols-2 gap-3 text-xs">
                        <div className="p-3 rounded-lg border bg-background/50 space-y-1.5">
                          <div className="font-semibold text-foreground flex items-center gap-1.5">
                            <Users className="h-3.5 w-3.5 text-primary" />
                            Registered Directors ({verification.corporate_profile.directors?.length || 0})
                          </div>
                          <ul className="space-y-1 text-muted-foreground">
                            {verification.corporate_profile.directors?.map((dir, idx) => (
                              <li key={idx} className="flex items-center justify-between">
                                <span>{dir.name}</span>
                                <span className="text-[10px] bg-muted px-1.5 py-0.5 rounded font-mono">{dir.position || 'Director'}</span>
                              </li>
                            ))}
                          </ul>
                        </div>
                        <div className="p-3 rounded-lg border bg-background/50 space-y-1.5">
                          <div className="font-semibold text-foreground flex items-center gap-1.5">
                            <Briefcase className="h-3.5 w-3.5 text-primary" />
                            Registered Corporate Shareholders
                          </div>
                          <ul className="space-y-1 text-muted-foreground">
                            {verification.corporate_profile.shareholders?.map((sh, idx) => (
                              <li key={idx} className="flex items-center justify-between">
                                <span>{sh.name}</span>
                                <span className="font-semibold text-foreground">{sh.percentage}%</span>
                              </li>
                            ))}
                          </ul>
                        </div>
                      </div>
                    </div>
                  )}

                  {/* Trust specifics: Trustees & Deed */}
                  {ownershipType === 'trust' && verification?.trust_profile && (
                    <div className="p-3.5 rounded-xl border bg-background/50 space-y-2 text-xs">
                      <div className="font-semibold text-foreground flex items-center gap-1.5">
                        <Landmark className="h-3.5 w-3.5 text-amber-500" />
                        Deed of Trust Settlement Information
                      </div>
                      <div className="grid sm:grid-cols-2 gap-2 text-muted-foreground">
                        <div>Deed Reg: <span className="font-mono text-foreground">{verification.trust_profile.trust_deed_reference || '#TR-2021-994'}</span></div>
                        <div>Date of Execution: <span className="text-foreground">{verification.trust_profile.settlement_date || 'May 14, 2021'}</span></div>
                      </div>
                    </div>
                  )}

                  {/* Estate specifics: Probate Court Record */}
                  {ownershipType === 'estate' && verification?.estate_profile && (
                    <div className="p-3.5 rounded-xl border bg-background/50 space-y-2 text-xs">
                      <div className="font-semibold text-foreground flex items-center gap-1.5">
                        <FileText className="h-3.5 w-3.5 text-purple-500" />
                        Supreme Court of Judicature of Jamaica — Probate Division
                      </div>
                      <div className="grid sm:grid-cols-2 gap-2 text-muted-foreground">
                        <div>Suit Number: <span className="font-mono text-foreground">{verification.estate_profile.court_suit_number || '#PROB-2023-0182'}</span></div>
                        <div>Grant: <span className="text-foreground">{verification.estate_profile.grant_type || 'Grant of Probate'}</span></div>
                      </div>
                    </div>
                  )}
                </CardContent>
              </Card>

              {/* Card 2: Beneficial Ownership Safeguard Card */}
              <Card className="shadow-sm border-border/80 flex flex-col justify-between">
                <CardHeader className="pb-3">
                  <div className="flex items-center justify-between">
                    <CardTitle className="text-sm font-semibold flex items-center gap-1.5">
                      <Lock className="h-4 w-4 text-amber-500" />
                      Beneficial Ownership
                    </CardTitle>
                    <Badge variant="outline" className="text-[10px] bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300 border-amber-200">
                      Private & Confidential
                    </Badge>
                  </div>
                  <CardDescription className="text-xs">
                    Statutory confidentiality rules under the Companies Office of Jamaica.
                  </CardDescription>
                </CardHeader>
                <CardContent className="space-y-3 pt-0">
                  {canViewBeneficialOwnership && beneficialOwnership?.data ? (
                    <div className="space-y-2.5">
                      <div className="p-2.5 rounded-lg bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800 text-xs">
                        <div className="font-semibold text-emerald-800 dark:text-emerald-300 flex items-center gap-1">
                          <CheckCircle2 className="h-3.5 w-3.5" />
                          Form 38 Declared (Director Access)
                        </div>
                        <div className="text-[11px] text-emerald-700/90 dark:text-emerald-400 mt-0.5">
                          Ref: {beneficialOwnership.data.declaration_reference || 'COJ-BO-2024'}
                        </div>
                      </div>
                      <div className="space-y-1.5 text-xs">
                        {beneficialOwnership.data.beneficial_owners?.map((owner, idx) => (
                          <div key={idx} className="p-2 rounded border bg-background flex items-center justify-between">
                            <div>
                              <div className="font-medium text-foreground">{owner.full_name}</div>
                              <div className="text-[10px] text-muted-foreground">{owner.nature_of_control || 'Direct Shareholder'}</div>
                            </div>
                            <span className="font-semibold text-xs text-primary">{owner.ownership_percentage}%</span>
                          </div>
                        ))}
                      </div>
                    </div>
                  ) : (
                    /* Statutory Confidentiality Privacy Shield */
                    <div className="p-3.5 rounded-xl bg-muted/60 border border-dashed text-xs space-y-2">
                      <div className="flex items-start gap-2 text-muted-foreground">
                        <ShieldAlert className="h-4 w-4 text-muted-foreground shrink-0 mt-0.5" />
                        <div className="space-y-1">
                          <span className="font-semibold text-foreground block">
                            Statutory Privacy Shield
                          </span>
                          <p className="text-[11px] leading-relaxed">
                            {beneficialOwnership?.confidentiality_notice ||
                              'Beneficial ownership records are restricted under the Companies Act of Jamaica. They are never disclosed to ordinary residents or security guards.'}
                          </p>
                        </div>
                      </div>
                    </div>
                  )}
                </CardContent>
              </Card>
            </div>
          </TabsContent>

          {/* TAB 2: PEOPLE & ACCESS (DYNAMIC BY OWNERSHIP TYPE) */}
          <TabsContent value="people" className="space-y-6">
            {/* If Company: Corporate Representatives */}
            {ownershipType === 'company' && (
              <div className="space-y-4">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                  <div>
                    <h2 className="text-lg font-semibold text-foreground">
                      Authorized Corporate Representatives
                    </h2>
                    <p className="text-xs text-muted-foreground">
                      Directors, Officers, and designated managers authorized to act on behalf of {activeEntity?.legal_name}.
                    </p>
                  </div>
                  {canManage && (
                    <Dialog open={isAddRepOpen} onOpenChange={setIsAddRepOpen}>
                      <DialogTrigger asChild>
                        <Button size="sm" className="gap-1.5 text-xs">
                          <UserPlus className="h-3.5 w-3.5" />
                          Appoint Representative
                        </Button>
                      </DialogTrigger>
                      <DialogContent className="sm:max-w-[480px]">
                        <form onSubmit={submitRepresentative} className="space-y-4">
                          <DialogHeader>
                            <DialogTitle className="text-base font-semibold">
                              Appoint Corporate Representative
                            </DialogTitle>
                            <DialogDescription className="text-xs">
                              Designate an authorized person with specific operational powers for {activeEntity?.legal_name}.
                            </DialogDescription>
                          </DialogHeader>

                          <div className="space-y-3 py-2 text-xs">
                            <div className="space-y-1">
                              <Label className="text-xs font-medium">Full Legal Name</Label>
                              <Input
                                required
                                value={repForm.full_name}
                                onChange={(e) => setRepForm({ ...repForm, full_name: e.target.value })}
                                placeholder="e.g. Marcus Sterling"
                                className="h-8 text-xs"
                              />
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                              <div className="space-y-1">
                                <Label className="text-xs font-medium">Corporate Role</Label>
                                <Select
                                  value={repForm.role}
                                  onValueChange={(val) => setRepForm({ ...repForm, role: val })}
                                >
                                  <SelectTrigger className="h-8 text-xs">
                                    <SelectValue placeholder="Select Role" />
                                  </SelectTrigger>
                                  <SelectContent>
                                    {availableRoles.map((r) => (
                                      <SelectItem key={r.value} value={r.value} className="text-xs">
                                        {r.label}
                                      </SelectItem>
                                    ))}
                                  </SelectContent>
                                </Select>
                              </div>

                              <div className="space-y-1">
                                <Label className="text-xs font-medium">ID Document Type</Label>
                                <Input
                                  value={repForm.id_document_type}
                                  onChange={(e) => setRepForm({ ...repForm, id_document_type: e.target.value })}
                                  placeholder="Passport / Driver's Licence"
                                  className="h-8 text-xs"
                                />
                              </div>
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                              <div className="space-y-1">
                                <Label className="text-xs font-medium">Email Address</Label>
                                <Input
                                  type="email"
                                  value={repForm.email}
                                  onChange={(e) => setRepForm({ ...repForm, email: e.target.value })}
                                  placeholder="representative@company.com"
                                  className="h-8 text-xs"
                                />
                              </div>

                              <div className="space-y-1">
                                <Label className="text-xs font-medium">Phone Number</Label>
                                <Input
                                  value={repForm.phone}
                                  onChange={(e) => setRepForm({ ...repForm, phone: e.target.value })}
                                  placeholder="+1 (876) 555-0199"
                                  className="h-8 text-xs"
                                />
                              </div>
                            </div>

                            <div className="pt-2 flex items-center gap-4">
                              <label className="flex items-center gap-2 cursor-pointer text-xs">
                                <input
                                  type="checkbox"
                                  checked={repForm.is_signatory}
                                  onChange={(e) => setRepForm({ ...repForm, is_signatory: e.target.checked })}
                                  className="rounded border-input text-primary"
                                />
                                <span>Authorized Signatory</span>
                              </label>

                              <label className="flex items-center gap-2 cursor-pointer text-xs">
                                <input
                                  type="checkbox"
                                  checked={repForm.is_primary_contact}
                                  onChange={(e) => setRepForm({ ...repForm, is_primary_contact: e.target.checked })}
                                  className="rounded border-input text-primary"
                                />
                                <span>Primary Point of Contact</span>
                              </label>
                            </div>
                          </div>

                          <DialogFooter>
                            <Button type="button" variant="outline" size="sm" onClick={() => setIsAddRepOpen(false)}>
                              Cancel
                            </Button>
                            <Button type="submit" size="sm">
                              Confirm Appointment
                            </Button>
                          </DialogFooter>
                        </form>
                      </DialogContent>
                    </Dialog>
                  )}
                </div>

                {/* Representatives List */}
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                  {representatives.map((rep) => (
                    <Card key={rep.id} className="border shadow-sm flex flex-col justify-between">
                      <CardHeader className="p-4 pb-2">
                        <div className="flex items-start justify-between">
                          <div>
                            <CardTitle className="text-sm font-semibold text-foreground">
                              {rep.name}
                            </CardTitle>
                            <CardDescription className="text-xs font-medium text-primary flex items-center gap-1 mt-0.5">
                              {rep.role_label}
                              {rep.is_signatory && (
                                <Badge variant="outline" className="text-[10px] px-1 py-0 h-4 border-primary/30 text-primary">
                                  Signatory
                                </Badge>
                              )}
                            </CardDescription>
                          </div>
                          <Badge variant={rep.is_active ? 'default' : 'secondary'} className="text-[10px]">
                            {rep.status}
                          </Badge>
                        </div>
                      </CardHeader>
                      <CardContent className="p-4 pt-1 space-y-3 text-xs">
                        <div className="space-y-1 text-muted-foreground">
                          {rep.email && <div>Email: <span className="text-foreground">{rep.email}</span></div>}
                          {rep.phone && <div>Phone: <span className="text-foreground">{rep.phone}</span></div>}
                          {rep.id_document_number && <div>ID: <span className="font-mono text-foreground">{rep.id_document_number}</span></div>}
                        </div>

                        {/* Granular Property Scope */}
                        <div className="pt-2 border-t text-[11px]">
                          <span className="text-muted-foreground block mb-1">Assigned Property Scope:</span>
                          {rep.authorized_properties.length > 0 ? (
                            <div className="flex flex-wrap gap-1">
                              {rep.authorized_properties.map((p) => (
                                <Badge key={p.id} variant="secondary" className="text-[10px] font-mono">
                                  {p.lot_number}
                                </Badge>
                              ))}
                            </div>
                          ) : (
                            <span className="text-xs text-foreground font-medium">All Company Properties</span>
                          )}
                        </div>

                        {canManage && (
                          <div className="pt-2 flex justify-end">
                            <Button
                              variant="ghost"
                              size="sm"
                              onClick={() => revokeRep(rep.id)}
                              className="text-xs text-destructive hover:text-destructive hover:bg-destructive/10 h-7 px-2"
                            >
                              <Trash2 className="h-3 w-3 mr-1" />
                              Revoke
                            </Button>
                          </div>
                        )}
                      </CardContent>
                    </Card>
                  ))}
                </div>
              </div>
            )}

            {/* If Individual: Household & Family Members */}
            {ownershipType === 'individual' && household && (
              <div className="space-y-4">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                  <div>
                    <h2 className="text-lg font-semibold text-foreground">
                      Household & Family Members
                    </h2>
                    <p className="text-xs text-muted-foreground">
                      Registered occupants living at {household.property_number} ({household.address}).
                    </p>
                  </div>

                  <Dialog open={isAddMemberOpen} onOpenChange={setIsAddMemberOpen}>
                    <DialogTrigger asChild>
                      <Button size="sm" className="gap-1.5 text-xs">
                        <UserPlus className="h-3.5 w-3.5" />
                        Add Household Member
                      </Button>
                    </DialogTrigger>
                    <DialogContent className="sm:max-w-[480px]">
                      <form onSubmit={submitHouseholdMember} className="space-y-4">
                        <DialogHeader>
                          <DialogTitle className="text-base font-semibold">
                            Register Household Member
                          </DialogTitle>
                          <DialogDescription className="text-xs">
                            Add a family member, dependent, or long-term resident to this household.
                          </DialogDescription>
                        </DialogHeader>

                        <div className="space-y-3 py-2 text-xs">
                          <div className="space-y-1">
                            <Label className="text-xs font-medium">Full Name</Label>
                            <Input
                              required
                              value={memberForm.name}
                              onChange={(e) => setMemberForm({ ...memberForm, name: e.target.value })}
                              placeholder="e.g. Sarah Sterling"
                              className="h-8 text-xs"
                            />
                          </div>

                          <div className="grid grid-cols-2 gap-3">
                            <div className="space-y-1">
                              <Label className="text-xs font-medium">Household Role</Label>
                              <Select
                                value={memberForm.role_in_household}
                                onValueChange={(val) => setMemberForm({ ...memberForm, role_in_household: val, relationship_label: val })}
                              >
                                <SelectTrigger className="h-8 text-xs">
                                  <SelectValue placeholder="Role" />
                                </SelectTrigger>
                                <SelectContent>
                                  <SelectItem value="spouse" className="text-xs">Spouse / Partner</SelectItem>
                                  <SelectItem value="child" className="text-xs">Child / Dependent</SelectItem>
                                  <SelectItem value="long_term_occupant" className="text-xs">Long-Term Occupant</SelectItem>
                                  <SelectItem value="caregiver" className="text-xs">Caregiver</SelectItem>
                                  <SelectItem value="other" className="text-xs">Other Resident</SelectItem>
                                </SelectContent>
                              </Select>
                            </div>

                            <div className="space-y-1">
                              <Label className="text-xs font-medium">Relationship Label</Label>
                              <Input
                                value={memberForm.relationship_label}
                                onChange={(e) => setMemberForm({ ...memberForm, relationship_label: e.target.value })}
                                placeholder="e.g. Daughter"
                                className="h-8 text-xs"
                              />
                            </div>
                          </div>

                          <div className="grid grid-cols-2 gap-3">
                            <div className="space-y-1">
                              <Label className="text-xs font-medium">Email (Optional)</Label>
                              <Input
                                type="email"
                                value={memberForm.email}
                                onChange={(e) => setMemberForm({ ...memberForm, email: e.target.value })}
                                placeholder="sarah@example.com"
                                className="h-8 text-xs"
                              />
                            </div>

                            <div className="space-y-1">
                              <Label className="text-xs font-medium">Phone (Optional)</Label>
                              <Input
                                value={memberForm.phone}
                                onChange={(e) => setMemberForm({ ...memberForm, phone: e.target.value })}
                                placeholder="+1 (876) 555-0123"
                                className="h-8 text-xs"
                              />
                            </div>
                          </div>
                        </div>

                        <DialogFooter>
                          <Button type="button" variant="outline" size="sm" onClick={() => setIsAddMemberOpen(false)}>
                            Cancel
                          </Button>
                          <Button type="submit" size="sm">
                            Add to Household
                          </Button>
                        </DialogFooter>
                      </form>
                    </DialogContent>
                  </Dialog>
                </div>

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                  {household.members.map((member) => (
                    <Card key={member.id} className="border shadow-sm flex flex-col justify-between">
                      <CardHeader className="p-4 pb-2">
                        <div className="flex items-start justify-between">
                          <div>
                            <CardTitle className="text-sm font-semibold text-foreground">
                              {member.name}
                            </CardTitle>
                            <CardDescription className="text-xs font-medium text-emerald-600 dark:text-emerald-400 capitalize">
                              {member.relationship_label || member.role_in_household}
                            </CardDescription>
                          </div>
                          <Badge variant="outline" className="text-[10px]">
                            {member.pass_category}
                          </Badge>
                        </div>
                      </CardHeader>
                      <CardContent className="p-4 pt-1 space-y-3 text-xs">
                        <div className="space-y-1 text-muted-foreground">
                          {member.email && <div>Email: <span className="text-foreground">{member.email}</span></div>}
                          {member.phone && <div>Phone: <span className="text-foreground">{member.phone}</span></div>}
                          <div>Credential: <span className="text-foreground font-medium">{member.credential_label}</span></div>
                        </div>

                        {member.gate_pass && (
                          <div className="pt-2 border-t flex items-center justify-between">
                            <span className="text-[11px] font-mono text-muted-foreground">
                              Pass: {member.gate_pass.pass_id}
                            </span>
                            {member.gate_pass.token && (
                              <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setSelectedToken(member.gate_pass?.token || null)}
                                className="h-6 text-[10px] gap-1 px-2"
                              >
                                <QrCode className="h-3 w-3" />
                                View QR
                              </Button>
                            )}
                          </div>
                        )}

                        <div className="pt-2 flex justify-end">
                          <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => removeMember(member.id)}
                            className="text-xs text-destructive hover:text-destructive hover:bg-destructive/10 h-7 px-2"
                          >
                            <Trash2 className="h-3 w-3 mr-1" />
                            Remove
                          </Button>
                        </div>
                      </CardContent>
                    </Card>
                  ))}
                </div>
              </div>
            )}

            {/* If Trust or Estate: Appointees List */}
            {(ownershipType === 'trust' || ownershipType === 'estate') && (
              <div className="space-y-4">
                <div className="flex items-center justify-between">
                  <div>
                    <h2 className="text-lg font-semibold text-foreground">
                      {ownershipType === 'trust' ? 'Appointed Trustees & Fiduciary Counsel' : 'Court-Appointed Executors & Administrators'}
                    </h2>
                    <p className="text-xs text-muted-foreground">
                      Authorized legal appointees and representatives for {activeEntity?.legal_name}.
                    </p>
                  </div>
                </div>

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                  {representatives.map((rep) => (
                    <Card key={rep.id} className="border shadow-sm p-4 space-y-3 text-xs">
                      <div className="flex items-start justify-between">
                        <div>
                          <div className="font-semibold text-sm text-foreground">{rep.name}</div>
                          <div className="text-xs text-primary font-medium">{rep.role_label}</div>
                        </div>
                        <Badge variant="outline" className="text-[10px]">
                          {rep.status}
                        </Badge>
                      </div>
                      <div className="space-y-1 text-muted-foreground">
                        {rep.email && <div>Email: <span className="text-foreground">{rep.email}</span></div>}
                        {rep.phone && <div>Phone: <span className="text-foreground">{rep.phone}</span></div>}
                        <div>Signatory: <span className="text-foreground">{rep.is_signatory ? 'Yes (Sole & Joint)' : 'No'}</span></div>
                      </div>
                    </Card>
                  ))}
                </div>
              </div>
            )}
          </TabsContent>

          {/* TAB 3: PROPERTY PORTFOLIO */}
          <TabsContent value="properties" className="space-y-4">
            <div className="flex items-center justify-between">
              <div>
                <h2 className="text-lg font-semibold text-foreground">
                  Linked Property Portfolio
                </h2>
                <p className="text-xs text-muted-foreground">
                  Real estate units and lots registered under {activeEntity?.legal_name || 'your profile'}.
                </p>
              </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
              {properties.map((prop) => (
                <Card key={prop.id} className="border shadow-sm">
                  <CardHeader className="p-4 pb-2">
                    <div className="flex items-center justify-between">
                      <CardTitle className="text-sm font-semibold flex items-center gap-1.5">
                        <Home className="h-4 w-4 text-primary" />
                        {prop.lot_number}
                      </CardTitle>
                      <Badge variant="outline" className="capitalize text-xs">
                        {prop.status}
                      </Badge>
                    </div>
                    <CardDescription className="text-xs">
                      {prop.street_address}
                    </CardDescription>
                  </CardHeader>
                  <CardContent className="p-4 pt-1 space-y-2 text-xs">
                    <div className="flex items-center justify-between text-muted-foreground">
                      <span>Type:</span>
                      <span className="text-foreground font-medium capitalize">{prop.property_type}</span>
                    </div>
                    <div className="flex items-center justify-between text-muted-foreground">
                      <span>Occupant:</span>
                      <span className="text-foreground font-medium">{prop.primary_occupant?.name || 'Vacant / Corporate'}</span>
                    </div>
                  </CardContent>
                </Card>
              ))}
            </div>
          </TabsContent>

          {/* TAB 4: STATUTORY RECORDS */}
          <TabsContent value="documents" className="space-y-4">
            <Card className="border shadow-sm">
              <CardHeader className="pb-3">
                <CardTitle className="text-base font-semibold flex items-center gap-2">
                  <FileBadge className="h-4 w-4 text-primary" />
                  Statutory & Compliance Records
                </CardTitle>
                <CardDescription className="text-xs">
                  Legal documents filed with Jamaican regulatory authorities.
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-2 text-xs">
                {ownershipType === 'company' && (
                  <>
                    <div className="p-3 rounded-lg border bg-background flex items-center justify-between">
                      <div>
                        <div className="font-semibold text-foreground">Certificate of Incorporation</div>
                        <div className="text-[11px] text-muted-foreground">Companies Office of Jamaica • Certified True Copy</div>
                      </div>
                      <Badge className="bg-emerald-600 text-white text-[10px]">Verified Valid</Badge>
                    </div>
                    <div className="p-3 rounded-lg border bg-background flex items-center justify-between">
                      <div>
                        <div className="font-semibold text-foreground">Articles of Incorporation</div>
                        <div className="text-[11px] text-muted-foreground">Certified Memorandum & Articles of Association</div>
                      </div>
                      <Badge className="bg-emerald-600 text-white text-[10px]">Verified Valid</Badge>
                    </div>
                    <div className="p-3 rounded-lg border bg-background flex items-center justify-between">
                      <div>
                        <div className="font-semibold text-foreground">Form 9 Annual Return</div>
                        <div className="text-[11px] text-muted-foreground">Current Good Standing Annual Return Record</div>
                      </div>
                      <Badge className="bg-emerald-600 text-white text-[10px]">Active Filing</Badge>
                    </div>
                  </>
                )}

                {ownershipType === 'individual' && (
                  <div className="p-3 rounded-lg border bg-background flex items-center justify-between">
                    <div>
                      <div className="font-semibold text-foreground">Duplicate Certificate of Title</div>
                      <div className="text-[11px] text-muted-foreground">National Land Agency (NLA) • Volume 1402, Folio 88</div>
                    </div>
                    <Badge className="bg-emerald-600 text-white text-[10px]">Verified Title</Badge>
                  </div>
                )}

                {ownershipType === 'trust' && (
                  <div className="p-3 rounded-lg border bg-background flex items-center justify-between">
                    <div>
                      <div className="font-semibold text-foreground">Stamp Commissioner Certified Trust Deed</div>
                      <div className="text-[11px] text-muted-foreground">Stamped Deed of Settlement & Trustee Resolution</div>
                    </div>
                    <Badge className="bg-emerald-600 text-white text-[10px]">Verified Deed</Badge>
                  </div>
                )}

                {ownershipType === 'estate' && (
                  <div className="p-3 rounded-lg border bg-background flex items-center justify-between">
                    <div>
                      <div className="font-semibold text-foreground">Supreme Court Grant of Probate</div>
                      <div className="text-[11px] text-muted-foreground">Suit #PROB-2023-0182 with Certified Death Certificate</div>
                    </div>
                    <Badge className="bg-emerald-600 text-white text-[10px]">Court Order Valid</Badge>
                  </div>
                )}
              </CardContent>
            </Card>
          </TabsContent>
        </Tabs>
      </div>

      {/* QR Code Inspection Dialog */}
      {selectedToken && (
        <Dialog open={!!selectedToken} onOpenChange={() => setSelectedToken(null)}>
          <DialogContent className="sm:max-w-xs text-center space-y-3">
            <DialogHeader>
              <DialogTitle className="text-base font-semibold">
                Digital Credential Token
              </DialogTitle>
              <DialogDescription className="text-xs">
                Scan at the gate scanner to verify entry.
              </DialogDescription>
            </DialogHeader>
            <div className="p-6 bg-white rounded-xl flex items-center justify-center border shadow-inner">
              <QrCode className="h-32 w-32 text-black" />
            </div>
            <div className="font-mono text-xs bg-muted p-2 rounded break-all">
              {selectedToken}
            </div>
          </DialogContent>
        </Dialog>
      )}

      {/* THE 6 CARDINAL INVARIANTS INSPECTION MODAL */}
      {inspectedItem && (
        <Dialog open={!!inspectedItem} onOpenChange={() => setInspectedItem(null)}>
          <DialogContent className="sm:max-w-xl space-y-4">
            <DialogHeader className="pb-2 border-b">
              <div className="flex items-center gap-2">
                <Badge variant="outline" className="text-[10px] font-mono capitalize">
                  {inspectedItem.pillar.replace('_', ' ')}
                </Badge>
                <Badge className="bg-primary/10 text-primary text-[10px]">
                  {inspectedItem.answers.relationship.label}
                </Badge>
              </div>
              <DialogTitle className="text-lg font-bold flex items-center gap-2 pt-1">
                <UserSquare2 className="h-5 w-5 text-primary" />
                Authorization Invariant Inspector
              </DialogTitle>
              <DialogDescription className="text-xs">
                Complete enterprise audit trail resolving the 6 mandatory authorization questions.
              </DialogDescription>
            </DialogHeader>

            <div className="space-y-3 text-xs">
              {/* Question 1: Who is this person? */}
              <div className="p-3 rounded-lg bg-muted/40 border space-y-1">
                <div className="font-semibold text-foreground flex items-center gap-1.5 text-primary">
                  <span>1.</span> Who is this person?
                </div>
                <div className="grid sm:grid-cols-2 gap-2 text-muted-foreground pt-1 pl-4">
                  <div>Name: <span className="font-medium text-foreground">{inspectedItem.answers.who.name}</span></div>
                  <div>ID / Credential: <span className="font-mono text-foreground">{inspectedItem.answers.who.id_document}</span></div>
                  {inspectedItem.answers.who.email && <div>Email: <span className="text-foreground">{inspectedItem.answers.who.email}</span></div>}
                  {inspectedItem.answers.who.phone && <div>Phone: <span className="text-foreground">{inspectedItem.answers.who.phone}</span></div>}
                </div>
              </div>

              {/* Question 2: What legal/functional relationship do they have? */}
              <div className="p-3 rounded-lg bg-muted/40 border space-y-1">
                <div className="font-semibold text-foreground flex items-center gap-1.5 text-primary">
                  <span>2.</span> What legal/functional relationship do they have?
                </div>
                <div className="grid sm:grid-cols-2 gap-2 text-muted-foreground pt-1 pl-4">
                  <div>Relationship: <span className="font-medium text-foreground">{inspectedItem.answers.relationship.label}</span></div>
                  <div>Category: <span className="font-mono text-foreground capitalize">{inspectedItem.functional_type}</span></div>
                  <div>Signatory Authority: <span className="text-foreground">{inspectedItem.answers.relationship.is_signatory ? 'Yes (Legal Signatory)' : 'No'}</span></div>
                  <div>Primary Contact: <span className="text-foreground">{inspectedItem.answers.relationship.is_primary_contact ? 'Yes' : 'No'}</span></div>
                </div>
              </div>

              {/* Question 3: Which entity/property does that relationship apply to? */}
              <div className="p-3 rounded-lg bg-muted/40 border space-y-1">
                <div className="font-semibold text-foreground flex items-center gap-1.5 text-primary">
                  <span>3.</span> Which entity/property does that relationship apply to?
                </div>
                <div className="grid sm:grid-cols-2 gap-2 text-muted-foreground pt-1 pl-4">
                  <div>Owning Entity: <span className="font-medium text-foreground">{inspectedItem.answers.scope.entity_name}</span></div>
                  <div>Entity Type: <span className="text-foreground">{inspectedItem.answers.scope.entity_type}</span></div>
                  <div className="sm:col-span-2">
                    Scope: <span className="font-medium text-foreground">{inspectedItem.answers.scope.properties.join(', ')}</span>
                  </div>
                </div>
              </div>

              {/* Question 4: What are they allowed to do? */}
              <div className="p-3 rounded-lg bg-muted/40 border space-y-1">
                <div className="font-semibold text-foreground flex items-center gap-1.5 text-primary">
                  <span>4.</span> What are they allowed to do?
                </div>
                <div className="pt-1 pl-4 space-y-1.5">
                  <div className="text-muted-foreground text-[11px]">{inspectedItem.answers.permissions.summary}</div>
                  <div className="flex flex-wrap gap-1">
                    {inspectedItem.answers.permissions.granted.map((perm, idx) => (
                      <Badge key={idx} variant="secondary" className="text-[10px] font-mono">
                        {perm}
                      </Badge>
                    ))}
                  </div>
                </div>
              </div>

              {/* Question 5: When does that authorization expire? */}
              <div className="p-3 rounded-lg bg-muted/40 border space-y-1">
                <div className="font-semibold text-foreground flex items-center gap-1.5 text-primary">
                  <span>5.</span> When does that authorization expire?
                </div>
                <div className="grid sm:grid-cols-2 gap-2 text-muted-foreground pt-1 pl-4">
                  <div>Valid From: <span className="text-foreground">{inspectedItem.answers.validity.valid_from}</span></div>
                  <div>Expires At: <span className="font-semibold text-foreground">{inspectedItem.answers.validity.valid_until}</span></div>
                  <div>Status: <span className="font-medium text-emerald-600 dark:text-emerald-400 capitalize">{inspectedItem.answers.validity.status}</span></div>
                </div>
              </div>

              {/* Question 6: Who authorized them? */}
              <div className="p-3 rounded-lg bg-muted/40 border space-y-1">
                <div className="font-semibold text-foreground flex items-center gap-1.5 text-primary">
                  <span>6.</span> Who authorized them?
                </div>
                <div className="grid sm:grid-cols-2 gap-2 text-muted-foreground pt-1 pl-4">
                  <div>Authorizing Principal: <span className="font-medium text-foreground">{inspectedItem.answers.authorizer.name}</span></div>
                  <div>Authority: <span className="text-foreground">{inspectedItem.answers.authorizer.authority}</span></div>
                  <div className="sm:col-span-2">Source Document: <span className="font-mono text-foreground">{inspectedItem.answers.authorizer.source}</span></div>
                </div>
              </div>
            </div>

            <DialogFooter className="pt-2 border-t">
              <Button type="button" size="sm" onClick={() => setInspectedItem(null)}>
                Close Inspector
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      )}
    </DashboardLayout>
  );
}
