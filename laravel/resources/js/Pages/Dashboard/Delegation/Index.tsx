import React, { useState, useMemo, useEffect } from 'react';
import { Head, router } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
  AlertTriangle,
  CheckCircle2,
  Clock,
  History,
  Lock,
  Plus,
  QrCode,
  Shield,
  ShieldAlert,
  ShieldCheck,
  UserCheck,
  Users,
  Phone,
  Mail,
  Home,
  XCircle,
  FileText,
  Key,
  Check,
  Ban,
  Car,
  Waves,
  Dumbbell,
  Building,
  Calendar,
  Layers,
  Sparkles,
  Search,
  SlidersHorizontal,
  Share2,
  Copy,
  ExternalLink,
  HeartPulse,
  Briefcase,
  HardHat,
  UserX,
  RefreshCw,
  MoreVertical,
  CalendarRange,
  ChevronRight,
  Info,
} from 'lucide-react';
import { useToast } from '@/hooks/use-toast';

export interface AuthorizedPerson {
  id: string;
  sourceType: 'user' | 'delegation' | 'renter' | 'visitor' | 'household_member';
  sourceId: number;
  categoryKey:
    | 'residents'
    | 'long_term_occupants'
    | 'short_term_rentals'
    | 'legacy_contacts'
    | 'family_members'
    | 'caregivers'
    | 'property_managers'
    | 'domestic_staff'
    | 'staff'
    | 'contractors'
    | 'visitors'
    | 'former_occupants'
    | 'my_stay'
    | 'my_visitors'
    | 'my_access'
    | 'property_services';
  categoryLabel: string;
  categoryBadgeColor: string;
  name: string;
  email?: string | null;
  phone?: string | null;
  relationship: string;
  roleOrTitle: string;
  propertyLabel: string;
  propertyId?: number | null;
  status: string;
  isEmergencyActive?: boolean;
  startsAt?: string | null;
  expiresAt?: string | null;
  durationType?: string | null;
  durationLabel?: string | null;
  activationMethod?: string | null;
  approvalStatus?: string | null;
  permissions: Record<string, string> | string[];
  accessRules: Record<string, any>;
  credential?: {
    passId: string;
    category: string;
    categoryCode: string;
    shape: string;
    status: string;
    isActive: boolean;
    validFrom?: string | null;
    validUntil?: string | null;
  } | null;
  canManage?: boolean;
  raw: any;
}

interface ContinuityPlan {
  id?: number;
  primary_delegate_id: number | null;
  secondary_delegate_id: number | null;
  activation_conditions: string[];
  required_verification: string;
  authorized_actions: string[];
  max_duration_days: number;
  requires_admin_approval: boolean;
  notify_homeowner_on_trigger: boolean;
  notify_community_security: boolean;
  special_instructions: string | null;
  primary_delegate?: { name: string; email: string };
  secondary_delegate?: { name: string; email: string };
}

export interface HomeownerSection {
  key: string;
  title: string;
  subtitle: string;
  description: string;
  count: number;
  items: AuthorizedPerson[];
  emptyMessage: string;
  actionLabel: string;
}

export interface AccessPermissions {
  canSee: {
    ownHousehold: boolean;
    ownVisitors: boolean;
    propertyStaff: boolean;
    propertyContractors: boolean;
    ownLegacyContacts: boolean;
    otherProperties: boolean;
    entireCommunity: boolean;
    homeownerFinancials: boolean;
    shortTermRentals: boolean;
    propertyOwnership: boolean;
  };
  canAct: {
    removeHomeowner: boolean;
    changePropertyOwnership: boolean;
    viewHomeownerFinancials: boolean;
    createPermanentCredentials: boolean;
    modifyCommunitySettings: boolean;
    preClearVisitor: boolean;
    authorizeStaff: boolean;
    authorizeContractor: boolean;
    addOccupants: boolean;
    manageLegacyContacts: boolean;
    revokeOwnCredentials: boolean;
  };
  isShortTerm?: boolean;
  isRenter: boolean;
  isHomeowner: boolean;
  isAdmin: boolean;
  roleLabel: string;
  leaseEndDate?: string | null;
  checkoutDate?: string | null;
  scopeBadge?: string;
}

interface Props {
  authorizedPeople?: AuthorizedPerson[];
  categoryCounts?: Record<string, number>;
  homeownerSections?: HomeownerSection[];
  sections?: HomeownerSection[];
  permissions?: AccessPermissions;
  scopeBadge?: string;
  pageSubtitle?: string;
  unitLabel?: string;
  pageTitle?: string;
  contacts?: any[];
  delegates?: any[];
  longTermOccupants?: any[];
  allDelegations?: any[];
  continuityPlan: ContinuityPlan | null;
  properties: Array<{ id: number; lot_number: string; street_address: string }>;
  accessLevels: Record<string, string>;
  relationships: string[];
  availablePermissions: Record<string, string>;
  authorizationTypes?: Array<{
    value: string;
    label: string;
    tier: string;
    isResident: boolean;
    defaultRules: any;
  }>;
  continuityConditions: Record<string, string>;
  continuityVerifications: Record<string, string>;
  continuityActions: Record<string, string>;
  durationTypes?: Record<string, string>;
}

// 6 Temporary Access Duration Presets
export const DURATION_PRESETS = [
  {
    key: '2_hours',
    label: '2 Hours',
    shortLabel: '2 Hours',
    icon: Clock,
    badgeColor: 'bg-amber-500/10 text-amber-500 border-amber-500/30',
    description: 'Instant entry for deliveries, quick maintenance & urgent visits',
  },
  {
    key: '1_day',
    label: '1 Day',
    shortLabel: '1 Day',
    icon: Calendar,
    badgeColor: 'bg-blue-500/10 text-blue-500 border-blue-500/30',
    description: 'Single-day access for cleaners, babysitters, or party guests',
  },
  {
    key: '1_week',
    label: '1 Week',
    shortLabel: '1 Week',
    icon: CalendarRange,
    badgeColor: 'bg-purple-500/10 text-purple-500 border-purple-500/30',
    description: 'Extended short stay for contractors, project teams, or visiting relatives',
  },
  {
    key: 'custom',
    label: 'Custom Range',
    shortLabel: 'Custom',
    icon: SlidersHorizontal,
    badgeColor: 'bg-sky-500/10 text-sky-500 border-sky-500/30',
    description: 'Explicit start and expiration calendar dates with precision hours',
  },
  {
    key: 'recurring',
    label: 'Recurring Days',
    shortLabel: 'Recurring',
    icon: RefreshCw,
    badgeColor: 'bg-emerald-500/10 text-emerald-500 border-emerald-500/30',
    description: 'Scheduled weekly recurring days (e.g., Mon/Wed/Fri housekeepers & caregivers)',
  },
  {
    key: 'manual_revocation',
    label: 'Until Revoked',
    shortLabel: 'Until Revoked',
    icon: Lock,
    badgeColor: 'bg-teal-500/10 text-teal-500 border-teal-500/30',
    description: 'Continuous authorized access until homeowner or security manually revokes',
  },
];

// Recurring Worker Quick Templates for Ongoing Service Providers
export const RECURRING_WORKER_TEMPLATES = [
  {
    name: 'Domestic Worker',
    role: 'Housekeeper / Maid',
    relationship: 'Domestic Worker',
    categoryKey: 'domestic_staff',
    accessLevel: 'Domestic Staff',
    authType: 'domestic_staff',
    days: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
    startTime: '08:00',
    endTime: '17:00',
    icon: Building,
    badge: 'Mon–Fri 8am–5pm',
    desc: 'Regular household cleaning & upkeep',
  },
  {
    name: 'Caregiver',
    role: 'Private Caregiver / Nurse',
    relationship: 'Caregiver',
    categoryKey: 'caregivers',
    accessLevel: 'Caregiver',
    authType: 'caregiver',
    days: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
    startTime: '07:00',
    endTime: '19:00',
    icon: HeartPulse,
    badge: 'Mon–Fri 7am–7pm',
    desc: 'Patient assistance, medication & personal care',
  },
  {
    name: 'Gardener',
    role: 'Landscaper / Grounds',
    relationship: 'Gardener',
    categoryKey: 'domestic_staff',
    accessLevel: 'Domestic Staff',
    authType: 'domestic_staff',
    days: ['Mon', 'Wed', 'Fri'],
    startTime: '08:00',
    endTime: '16:00',
    icon: Sparkles,
    badge: 'Mon/Wed/Fri 8am–4pm',
    desc: 'Lawn, garden & outdoor grounds maintenance',
  },
  {
    name: 'Pool Maintenance',
    role: 'Pool Technician',
    relationship: 'Pool Maintenance',
    categoryKey: 'contractors',
    accessLevel: 'Contractor',
    authType: 'contractor',
    days: ['Tue', 'Fri'],
    startTime: '08:00',
    endTime: '13:00',
    icon: Waves,
    badge: 'Tue & Fri 8am–1pm',
    desc: 'Pool chemical balancing & cleaning service',
  },
  {
    name: 'Private Driver',
    role: 'Chauffeur / Driver',
    relationship: 'Driver',
    categoryKey: 'domestic_staff',
    accessLevel: 'Domestic Staff',
    authType: 'domestic_staff',
    days: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
    startTime: '06:00',
    endTime: '20:00',
    icon: Car,
    badge: 'Mon–Sat 6am–8pm',
    desc: 'Dedicated resident transportation & errands',
  },
  {
    name: 'Service Provider',
    role: 'Long-Term Contractor',
    relationship: 'Long-Term Service Provider',
    categoryKey: 'contractors',
    accessLevel: 'Contractor',
    authType: 'contractor',
    days: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
    startTime: '08:00',
    endTime: '17:00',
    icon: HardHat,
    badge: 'Mon–Fri 8am–5pm',
    desc: 'Recurring facility, HVAC or technical contractor',
  },
];

// 7 Official Homeowner Access Sections
export const HOMEOWNER_SECTIONS_CONFIG = [
  {
    key: 'residents',
    title: 'Residents',
    subtitle: 'Homeowner(s) • Household members',
    description: 'Primary homeowners and verified household residents living in the home',
    icon: Home,
    badgeColor: 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30',
    emptyText: 'No other household members currently registered for this property.',
    actionCategory: 'residents',
    actionLabel: 'Add Household Member',
  },
  {
    key: 'long_term_occupants',
    title: 'Long-Term Guests / Renters',
    subtitle: 'Authorized long-term occupants',
    description: 'Authorized extended occupants and long-term lease tenants',
    icon: Key,
    badgeColor: 'bg-indigo-500/10 text-indigo-400 border-indigo-500/30',
    emptyText: 'No active long-term occupants or lease tenants registered for this property.',
    actionCategory: 'long_term_occupants',
    actionLabel: 'Add Long-Term Occupant',
  },
  {
    key: 'short_term_rentals',
    title: 'Short-Term Rental',
    subtitle: 'Active Airbnb/short-term occupants, if applicable',
    description: 'Active Airbnb reservations and temporary short-term occupants',
    icon: Sparkles,
    badgeColor: 'bg-pink-500/10 text-pink-400 border-pink-500/30',
    emptyText: 'No active Airbnb or short-term occupants currently staying at this property.',
    actionCategory: 'short_term_rentals',
    actionLabel: 'Register Short-Term Guest',
  },
  {
    key: 'visitors',
    title: 'Visitors',
    subtitle: 'Current and upcoming visitors',
    description: 'Pre-cleared day visitors, deliveries, and expected guests',
    icon: UserCheck,
    badgeColor: 'bg-teal-500/10 text-teal-400 border-teal-500/30',
    emptyText: 'No current or upcoming visitors scheduled for this property.',
    actionCategory: 'visitors',
    actionLabel: 'Pre-Clear Visitor',
  },
  {
    key: 'staff',
    title: 'Staff',
    subtitle: 'Staff authorized for this property',
    description: 'Domestic staff, housekeepers, caregivers, gardeners, and drivers',
    icon: Building,
    badgeColor: 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30',
    emptyText: 'No dedicated household staff currently authorized for this property.',
    actionCategory: 'domestic_staff',
    actionLabel: 'Authorize Staff Member',
  },
  {
    key: 'contractors',
    title: 'Contractors',
    subtitle: 'Contractors authorized for this property',
    description: 'Authorized maintenance contractors, electricians, and technicians',
    icon: HardHat,
    badgeColor: 'bg-amber-500/10 text-amber-400 border-amber-500/30',
    emptyText: 'No active contractors currently cleared for this property.',
    actionCategory: 'contractors',
    actionLabel: 'Authorize Contractor',
  },
  {
    key: 'legacy_contacts',
    title: 'Legacy Contacts',
    subtitle: 'Active legacy/emergency delegates',
    description: 'Designated emergency contacts, attorneys, and succession delegates',
    icon: ShieldCheck,
    badgeColor: 'bg-purple-500/10 text-purple-400 border-purple-500/30',
    emptyText: 'No active legacy contacts or emergency delegates configured.',
    actionCategory: 'legacy_contacts',
    actionLabel: 'Add Legacy Contact',
  },
];

// Navigation Tabs for Quick Filtering
const CATEGORY_TABS = [
  { key: 'all', label: 'All (7 Sections)', icon: Users, desc: 'Complete property access overview' },
  { key: 'residents', label: 'Residents', icon: Home, desc: 'Homeowner(s) & household members' },
  { key: 'long_term_occupants', label: 'Long-Term Guests / Renters', icon: Key, desc: 'Authorized long-term occupants' },
  { key: 'short_term_rentals', label: 'Short-Term Rental', icon: Sparkles, desc: 'Active Airbnb/short-term occupants' },
  { key: 'visitors', label: 'Visitors', icon: UserCheck, desc: 'Current and upcoming visitors' },
  { key: 'staff', label: 'Staff', icon: Building, desc: 'Staff authorized for this unit' },
  { key: 'contractors', label: 'Contractors', icon: HardHat, desc: 'Contractors authorized for this unit' },
  { key: 'legacy_contacts', label: 'Legacy Contacts', icon: ShieldCheck, desc: 'Active legacy/emergency delegates' },
  { key: 'former_occupants', label: 'Former Occupants', icon: UserX, desc: 'Expired and revoked records' },
];

// 5 Official Long-Term Renter Access Sections
export const RENTER_SECTIONS_CONFIG = [
  {
    key: 'residents',
    title: 'Household & Occupants',
    subtitle: 'Your household & authorized co-occupants',
    description: 'Verified co-occupants and household members residing under your lease',
    icon: Home,
    badgeColor: 'bg-indigo-500/10 text-indigo-400 border-indigo-500/30',
    emptyText: 'No additional household members or authorized co-occupants registered for your lease.',
    actionCategory: 'long_term_occupants',
    actionLabel: 'Add Co-Occupant',
  },
  {
    key: 'visitors',
    title: 'Visitors',
    subtitle: 'Visitors authorized for your unit',
    description: 'Current and upcoming visitors invited by you',
    icon: UserCheck,
    badgeColor: 'bg-teal-500/10 text-teal-400 border-teal-500/30',
    emptyText: 'No current or upcoming visitors scheduled for your unit.',
    actionCategory: 'visitors',
    actionLabel: 'Pre-Clear Visitor',
  },
  {
    key: 'staff',
    title: 'Staff',
    subtitle: 'Staff authorized for your property',
    description: 'Domestic workers, cleaners, and service providers authorized for your unit',
    icon: Building,
    badgeColor: 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30',
    emptyText: 'No dedicated staff currently authorized for your unit.',
    actionCategory: 'domestic_staff',
    actionLabel: 'Authorize Staff Member',
  },
  {
    key: 'contractors',
    title: 'Contractors',
    subtitle: 'Contractors authorized for your property',
    description: 'Maintenance contractors, electricians, and technicians cleared for your unit',
    icon: HardHat,
    badgeColor: 'bg-amber-500/10 text-amber-400 border-amber-500/30',
    emptyText: 'No active contractors currently cleared for your unit.',
    actionCategory: 'contractors',
    actionLabel: 'Authorize Contractor',
  },
  {
    key: 'legacy_contacts',
    title: 'Legacy Contacts',
    subtitle: 'Your emergency & continuity delegates',
    description: 'Designated personal emergency contacts and trusted delegates authorized for your tenancy',
    icon: ShieldCheck,
    badgeColor: 'bg-purple-500/10 text-purple-400 border-purple-500/30',
    emptyText: 'No emergency contacts configured for your lease.',
    actionCategory: 'legacy_contacts',
    actionLabel: 'Add Emergency Contact',
  },
];

// Navigation Tabs for Long-Term Renter
const RENTER_CATEGORY_TABS = [
  { key: 'all', label: 'All (5 Sections)', icon: Users, desc: 'Complete tenancy access overview' },
  { key: 'residents', label: 'Household & Occupants', icon: Home, desc: 'Your household & co-occupants' },
  { key: 'visitors', label: 'Visitors', icon: UserCheck, desc: 'Visitors invited by you' },
  { key: 'staff', label: 'Staff', icon: Building, desc: 'Staff authorized for your unit' },
  { key: 'contractors', label: 'Contractors', icon: HardHat, desc: 'Contractors authorized for your unit' },
  { key: 'legacy_contacts', label: 'Legacy Contacts', icon: ShieldCheck, desc: 'Your emergency delegates' },
  { key: 'former_occupants', label: 'Former Occupants', icon: UserX, desc: 'Expired and revoked records' },
];

// 4 Official Short-Term Rental / Airbnb Access Sections
export const SHORT_TERM_SECTIONS_CONFIG = [
  {
    key: 'my_stay',
    title: 'My Stay',
    subtitle: 'Property & Reservation Details',
    description: 'Reservation check-in / check-out validity, property unit, and host contact information',
    icon: Calendar,
    badgeColor: 'bg-pink-500/10 text-pink-400 border-pink-500/30',
    emptyText: 'No active reservation record found for this stay.',
    actionCategory: '',
    actionLabel: '',
  },
  {
    key: 'my_visitors',
    title: 'My Visitors',
    subtitle: 'Your authorized booking visitors',
    description: 'Pre-cleared visitors and reservation guests registered exclusively for your stay',
    icon: UserCheck,
    badgeColor: 'bg-teal-500/10 text-teal-400 border-teal-500/30',
    emptyText: 'No visitors currently registered for your stay.',
    actionCategory: 'visitors',
    actionLabel: 'Pre-Clear Visitor',
  },
  {
    key: 'my_access',
    title: 'My Access',
    subtitle: 'QR Credential & Gate Clearance',
    description: 'Your verified dynamic QR gate pass, active validity period, and Main Gate clearance',
    icon: QrCode,
    badgeColor: 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30',
    emptyText: 'No gate credential active for this stay.',
    actionCategory: '',
    actionLabel: '',
  },
  {
    key: 'property_services',
    title: 'Property Services',
    subtitle: 'Authorized property staff & contractors',
    description: 'Authorized maintenance, housekeeping, or emergency contractor contacts for the property (read-only)',
    icon: Building,
    badgeColor: 'bg-amber-500/10 text-amber-400 border-amber-500/30',
    emptyText: 'No external property services scheduled during your stay.',
    actionCategory: '',
    actionLabel: '',
  },
];

// Navigation Tabs for Short-Term Rental Guest
const SHORT_TERM_CATEGORY_TABS = [
  { key: 'all', label: 'All (4 Sections)', icon: Users, desc: 'Complete stay overview' },
  { key: 'my_stay', label: 'My Stay', icon: Calendar, desc: 'Reservation & Property' },
  { key: 'my_visitors', label: 'My Visitors', icon: UserCheck, desc: 'Your registered visitors' },
  { key: 'my_access', label: 'My Access', icon: QrCode, desc: 'QR code & gate clearance' },
  { key: 'property_services', label: 'Property Services', icon: Building, desc: 'Authorized property staff' },
];

export default function DelegatedAccessCenter({
  authorizedPeople = [],
  categoryCounts = {},
  homeownerSections = [],
  sections = [],
  permissions,
  scopeBadge,
  pageSubtitle,
  unitLabel = '',
  pageTitle,
  continuityPlan,
  properties = [],
  accessLevels = {},
  relationships = [],
  availablePermissions = {},
  authorizationTypes = [],
  continuityConditions = {},
  continuityVerifications = {},
  continuityActions = {},
  durationTypes = {},
}: Props) {
  // The viewer's own access details (checkout date, lease end). Named apart
  // because a form handler below has a local `permissions` list that shadowed
  // it, so a short-term guest's dates were never held to their checkout.
  const viewerPermissions = permissions;
  const { toast } = useToast();

  const isShortTerm = permissions?.isShortTerm ?? false;
  const isRenter = (!isShortTerm && permissions?.isRenter) ?? false;
  const activeSectionsConfig = isShortTerm
    ? SHORT_TERM_SECTIONS_CONFIG
    : isRenter
    ? RENTER_SECTIONS_CONFIG
    : HOMEOWNER_SECTIONS_CONFIG;
  const activeCategoryTabs = isShortTerm
    ? SHORT_TERM_CATEGORY_TABS
    : isRenter
    ? RENTER_CATEGORY_TABS
    : CATEGORY_TABS;

  // Active navigation tab
  const [selectedCategory, setSelectedCategory] = useState<string>('all');
  const [searchQuery, setSearchQuery] = useState('');
  const [statusFilter, setStatusFilter] = useState('all');
  const [viewMode, setViewMode] = useState<'sections' | 'cards' | 'table'>('sections');
  const [showScopeMatrix, setShowScopeMatrix] = useState(false);

  // Modals state
  const [isAuthorizeModalOpen, setIsAuthorizeModalOpen] = useState(false);
  const [isCredentialModalOpen, setIsCredentialModalOpen] = useState(false);
  const [isEditRulesModalOpen, setIsEditRulesModalOpen] = useState(false);
  const [isEmergencyModalOpen, setIsEmergencyModalOpen] = useState(false);
  const [isAuditModalOpen, setIsAuditModalOpen] = useState(false);
  const [isRevokeModalOpen, setIsRevokeModalOpen] = useState(false);
  const [isContinuityModalOpen, setIsContinuityModalOpen] = useState(false);

  // Active Person selected for details/actions
  const [activePerson, setActivePerson] = useState<AuthorizedPerson | null>(null);
  const [auditEvents, setAuditEvents] = useState<any[]>([]);
  const [loadingAudit, setLoadingAudit] = useState(false);
  const [isReissuing, setIsReissuing] = useState(false);

  // Live Slot Timer for QR Credential Modal
  const [secondsRemaining, setSecondsRemaining] = useState(30);

  useEffect(() => {
    const timer = setInterval(() => {
      const nowSec = Math.floor(Date.now() / 1000);
      const remaining = 30 - (nowSec % 30);
      setSecondsRemaining(remaining);
    }, 1000);
    return () => clearInterval(timer);
  }, []);

  // Form State: Authorize Person
  const [formData, setFormData] = useState({
    name: '',
    email: '',
    phone: '',
    relationship: 'Family/Friend',
    categoryKey: 'long_term_occupants',
    access_level: 'Long-Term Occupant',
    authorization_type: 'long_term_occupant',
    duration_type: 'custom',
    permissions: ['gate_access'],
    property_id: properties[0]?.id ? String(properties[0].id) : '',
    activation_method: 'immediate',
    starts_at: new Date().toISOString().split('T')[0],
    expires_at: new Date(Date.now() + 180 * 86400000).toISOString().split('T')[0],
    security_notes: '',
    access_rules: {
      gate_access: true,
      pool_access: true,
      gym_access: false,
      clubhouse_access: true,
      parking_allocated: true,
      can_invite_visitors: false,
      requires_id_verification: true,
      allowed_days: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
      entry_start_time: '06:00',
      entry_end_time: '22:00',
    },
  });

  // Form State: Edit Rules
  const [editRulesData, setEditRulesData] = useState({
    duration_type: 'custom',
    starts_at: '',
    expires_at: '',
    permissions: [] as string[],
    access_rules: {
      gate_access: true,
      pool_access: false,
      gym_access: false,
      clubhouse_access: false,
      parking_allocated: false,
      can_invite_visitors: false,
      requires_id_verification: false,
      allowed_days: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
      entry_start_time: '00:00',
      entry_end_time: '23:59',
    },
  });

  const handleSelectDurationPreset = (presetKey: string) => {
    const today = new Date();
    const todayStr = today.toISOString().split('T')[0];
    const currentYear = today.getFullYear();

    let expiresStr = '';
    if (presetKey === '2_hours' || presetKey === '1_day') {
      expiresStr = todayStr;
    } else if (presetKey === '1_week') {
      const nextWeek = new Date(today.getTime() + 7 * 86400000);
      expiresStr = nextWeek.toISOString().split('T')[0];
    } else if (presetKey === 'recurring') {
      // Default recurring passes through December 31 of current year
      expiresStr = `${currentYear}-12-31`;
    } else if (presetKey === 'manual_revocation') {
      expiresStr = '';
    } else {
      const defaultCustom = new Date(today.getTime() + 30 * 86400000);
      expiresStr = defaultCustom.toISOString().split('T')[0];
    }

    setFormData((prev) => ({
      ...prev,
      duration_type: presetKey,
      starts_at: prev.starts_at || todayStr,
      expires_at: expiresStr,
    }));
  };

  const handleSelectEditRulesDurationPreset = (presetKey: string) => {
    const today = new Date();
    const todayStr = today.toISOString().split('T')[0];
    const currentYear = today.getFullYear();

    let expiresStr = '';
    if (presetKey === '2_hours' || presetKey === '1_day') {
      expiresStr = todayStr;
    } else if (presetKey === '1_week') {
      const nextWeek = new Date(today.getTime() + 7 * 86400000);
      expiresStr = nextWeek.toISOString().split('T')[0];
    } else if (presetKey === 'recurring') {
      expiresStr = `${currentYear}-12-31`;
    } else if (presetKey === 'manual_revocation') {
      expiresStr = '';
    } else {
      const defaultCustom = new Date(today.getTime() + 30 * 86400000);
      expiresStr = defaultCustom.toISOString().split('T')[0];
    }

    setEditRulesData((prev) => ({
      ...prev,
      duration_type: presetKey,
      starts_at: prev.starts_at || todayStr,
      expires_at: expiresStr,
    }));
  };

  const handleApplyRecurringWorkerTemplate = (template: (typeof RECURRING_WORKER_TEMPLATES)[0]) => {
    const currentYear = new Date().getFullYear();
    const endOfYear = `${currentYear}-12-31`;
    const todayStr = new Date().toISOString().split('T')[0];

    setFormData((prev) => ({
      ...prev,
      relationship: template.relationship,
      categoryKey: template.categoryKey,
      access_level: template.accessLevel,
      authorization_type: template.authType,
      duration_type: 'recurring',
      starts_at: prev.starts_at || todayStr,
      expires_at: endOfYear,
      access_rules: {
        ...prev.access_rules,
        gate_access: true,
        allowed_days: template.days,
        entry_start_time: template.startTime,
        entry_end_time: template.endTime,
      },
    }));

    toast({
      title: `${template.name} Preset Applied`,
      description: `Configured for ${template.badge}, valid through Dec 31, ${currentYear}. Single persistent QR credential.`,
    });
  };

  const applyQuickSchedule = (
    type: 'mon_fri' | 'everyday' | 'weekends' | 'mon_sat' | 'tue_thu',
    target: 'form' | 'edit'
  ) => {
    let days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'];
    let start = '08:00';
    let end = '17:00';

    if (type === 'mon_fri') {
      days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'];
      start = '08:00';
      end = '17:00';
    } else if (type === 'everyday') {
      days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
      start = '00:00';
      end = '23:59';
    } else if (type === 'weekends') {
      days = ['Sat', 'Sun'];
      start = '08:00';
      end = '18:00';
    } else if (type === 'mon_sat') {
      days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
      start = '06:00';
      end = '20:00';
    } else if (type === 'tue_thu') {
      days = ['Tue', 'Thu'];
      start = '08:00';
      end = '16:00';
    }

    if (target === 'form') {
      setFormData((prev) => ({
        ...prev,
        access_rules: {
          ...prev.access_rules,
          allowed_days: days,
          entry_start_time: start,
          entry_end_time: end,
        },
      }));
    } else {
      setEditRulesData((prev) => ({
        ...prev,
        access_rules: {
          ...prev.access_rules,
          allowed_days: days,
          entry_start_time: start,
          entry_end_time: end,
        },
      }));
    }

    toast({
      title: 'Schedule Applied',
      description: `${days.join(', ')} • ${start} - ${end}`,
    });
  };

  const setEndOfYearExpiration = (target: 'form' | 'edit') => {
    const currentYear = new Date().getFullYear();
    const endOfYear = `${currentYear}-12-31`;
    if (target === 'form') {
      setFormData((prev) => ({ ...prev, expires_at: endOfYear }));
    } else {
      setEditRulesData((prev) => ({ ...prev, expires_at: endOfYear }));
    }

    toast({
      title: 'Validity Horizon Set',
      description: `Access will remain active through December 31, ${currentYear}.`,
    });
  };

  // Emergency activation form state
  const [emergencyReason, setEmergencyReason] = useState('Medical emergency requiring caregiver / family access');
  const [emergencyDurationDays, setEmergencyDurationDays] = useState(7);
  const [emergencyNotes, setEmergencyNotes] = useState('');

  // Revoke reason
  const [revokeReason, setRevokeReason] = useState('Authorized period concluded');

  // Continuity plan form state
  const [planForm, setPlanForm] = useState({
    primary_delegate_id: continuityPlan?.primary_delegate_id ? String(continuityPlan.primary_delegate_id) : '',
    secondary_delegate_id: continuityPlan?.secondary_delegate_id ? String(continuityPlan.secondary_delegate_id) : '',
    activation_conditions: continuityPlan?.activation_conditions || ['medical_emergency', 'incapacity'],
    required_verification: continuityPlan?.required_verification || 'admin_verification',
    authorized_actions: continuityPlan?.authorized_actions || ['gate_access', 'visitor_management', 'property_maintenance'],
    max_duration_days: continuityPlan?.max_duration_days || 30,
    requires_admin_approval: continuityPlan?.requires_admin_approval ?? true,
    notify_homeowner_on_trigger: continuityPlan?.notify_homeowner_on_trigger ?? true,
    notify_community_security: continuityPlan?.notify_community_security ?? true,
    special_instructions: continuityPlan?.special_instructions || '',
  });

  // Configure form defaults based on category selection
  const applyCategoryDefaults = (category: string) => {
    let accessLevel = 'Long-Term Occupant';
    let authType = 'long_term_occupant';
    let relationship = 'Family/Friend';
    let durationType = 'custom';
    let permissions = ['gate_access'];
    let rules = {
      gate_access: true,
      pool_access: false,
      gym_access: false,
      clubhouse_access: false,
      parking_allocated: false,
      can_invite_visitors: false,
      requires_id_verification: false,
      allowed_days: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
      entry_start_time: '00:00',
      entry_end_time: '23:59',
    };

    if (category === 'long_term_occupants') {
      accessLevel = 'Long-Term Occupant';
      authType = 'long_term_occupant';
      relationship = 'Family/Friend';
      durationType = 'custom';
      permissions = ['gate_access', 'pool_access', 'gym_access'];
      rules = {
        gate_access: true,
        pool_access: true,
        gym_access: true,
        clubhouse_access: true,
        parking_allocated: true,
        can_invite_visitors: true,
        requires_id_verification: true,
        allowed_days: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
        entry_start_time: '00:00',
        entry_end_time: '23:59',
      };
    } else if (category === 'legacy_contacts') {
      accessLevel = 'Legacy Delegate';
      authType = 'legacy_contact';
      relationship = 'Attorney';
      durationType = 'manual_revocation';
      permissions = ['emergency_communications', 'gate_access'];
      rules = {
        gate_access: true,
        pool_access: false,
        gym_access: false,
        clubhouse_access: false,
        parking_allocated: false,
        can_invite_visitors: false,
        requires_id_verification: true,
        allowed_days: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
        entry_start_time: '00:00',
        entry_end_time: '23:59',
      };
    } else if (category === 'family_members') {
      accessLevel = 'Family Member';
      authType = 'family_member';
      relationship = 'Family member';
      durationType = 'recurring';
      permissions = ['gate_access', 'pool_access', 'gym_access'];
      rules = {
        gate_access: true,
        pool_access: true,
        gym_access: true,
        clubhouse_access: true,
        parking_allocated: true,
        can_invite_visitors: true,
        requires_id_verification: false,
        allowed_days: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
        entry_start_time: '00:00',
        entry_end_time: '23:59',
      };
    } else if (category === 'caregivers') {
      accessLevel = 'Caregiver';
      authType = 'caregiver';
      relationship = 'Caregiver';
      durationType = 'recurring';
      permissions = ['gate_access'];
      rules = {
        gate_access: true,
        pool_access: false,
        gym_access: false,
        clubhouse_access: false,
        parking_allocated: true,
        can_invite_visitors: false,
        requires_id_verification: true,
        allowed_days: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
        entry_start_time: '07:00',
        entry_end_time: '19:00',
      };
    } else if (category === 'property_managers') {
      accessLevel = 'Property Delegate';
      authType = 'property_manager';
      relationship = 'Property manager';
      durationType = 'recurring';
      permissions = ['gate_access', 'property_maintenance', 'visitor_authorization'];
      rules = {
        gate_access: true,
        pool_access: false,
        gym_access: false,
        clubhouse_access: true,
        parking_allocated: true,
        can_invite_visitors: true,
        requires_id_verification: true,
        allowed_days: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
        entry_start_time: '08:00',
        entry_end_time: '18:00',
      };
    } else if (category === 'domestic_staff') {
      accessLevel = 'Domestic Staff';
      authType = 'domestic_staff';
      relationship = 'Cleaner';
      durationType = 'recurring';
      permissions = ['gate_access'];
      rules = {
        gate_access: true,
        pool_access: false,
        gym_access: false,
        clubhouse_access: false,
        parking_allocated: true,
        can_invite_visitors: false,
        requires_id_verification: true,
        allowed_days: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
        entry_start_time: '08:00',
        entry_end_time: '17:00',
      };
    } else if (category === 'contractors') {
      accessLevel = 'Contractor';
      authType = 'contractor';
      relationship = 'Contractor';
      durationType = '1_week';
      permissions = ['gate_access'];
      rules = {
        gate_access: true,
        pool_access: false,
        gym_access: false,
        clubhouse_access: false,
        parking_allocated: true,
        can_invite_visitors: false,
        requires_id_verification: true,
        allowed_days: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
        entry_start_time: '08:00',
        entry_end_time: '17:00',
      };
    } else if (category === 'visitors') {
      accessLevel = 'Visitor';
      authType = 'visitor';
      relationship = 'Delivery Personnel';
      durationType = '2_hours';
      permissions = ['gate_access'];
      rules = {
        gate_access: true,
        pool_access: false,
        gym_access: false,
        clubhouse_access: false,
        parking_allocated: false,
        can_invite_visitors: false,
        requires_id_verification: false,
        allowed_days: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
        entry_start_time: '00:00',
        entry_end_time: '23:59',
      };
    }

    const today = new Date();
    const todayStr = today.toISOString().split('T')[0];
    let expiresStr = '';
    if (durationType === '2_hours' || durationType === '1_day') {
      expiresStr = todayStr;
    } else if (durationType === '1_week') {
      expiresStr = new Date(today.getTime() + 7 * 86400000).toISOString().split('T')[0];
    } else if (durationType === 'recurring') {
      expiresStr = new Date(today.getTime() + 180 * 86400000).toISOString().split('T')[0];
    } else if (durationType === 'manual_revocation') {
      expiresStr = '';
    } else {
      expiresStr = new Date(today.getTime() + 180 * 86400000).toISOString().split('T')[0];
    }

    if (isShortTerm && viewerPermissions?.checkoutDate) {
      if (!expiresStr || expiresStr > viewerPermissions.checkoutDate) {
        expiresStr = viewerPermissions.checkoutDate;
      }
    }

    setFormData((prev) => ({
      ...prev,
      categoryKey: isShortTerm ? 'visitors' : category,
      access_level: isShortTerm ? 'Pre-Cleared Visitor' : accessLevel,
      authorization_type: isShortTerm ? 'visitor' : authType,
      relationship: relationship,
      duration_type: durationType,
      starts_at: todayStr,
      expires_at: expiresStr,
      permissions: permissions,
      access_rules: rules,
    }));
  };

  const handleOpenAuthorizeModal = (defaultCategory = 'long_term_occupants') => {
    applyCategoryDefaults(isShortTerm ? 'visitors' : (defaultCategory || 'long_term_occupants'));
    setIsAuthorizeModalOpen(true);
  };

  const handleOpenCredentialModal = (person: AuthorizedPerson) => {
    setActivePerson(person);
    setIsCredentialModalOpen(true);
  };

  const handleOpenEditRulesModal = (person: AuthorizedPerson) => {
    setActivePerson(person);
    const personPerms = Array.isArray(person.permissions)
      ? person.permissions
      : Object.keys(person.permissions || {});

    setEditRulesData({
      duration_type: person.durationType || 'custom',
      starts_at: person.startsAt ? person.startsAt.split('T')[0] : new Date().toISOString().split('T')[0],
      expires_at: person.expiresAt ? person.expiresAt.split('T')[0] : '',
      permissions: personPerms,
      access_rules: {
        gate_access: person.accessRules?.gate_access ?? true,
        pool_access: person.accessRules?.pool_access ?? false,
        gym_access: person.accessRules?.gym_access ?? false,
        clubhouse_access: person.accessRules?.clubhouse_access ?? false,
        parking_allocated: person.accessRules?.parking_allocated ?? false,
        can_invite_visitors: person.accessRules?.can_invite_visitors ?? false,
        requires_id_verification: person.accessRules?.requires_id_verification ?? false,
        allowed_days: person.accessRules?.allowed_days ?? ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
        entry_start_time: person.accessRules?.entry_start_time ?? '00:00',
        entry_end_time: person.accessRules?.entry_end_time ?? '23:59',
      },
    });
    setIsEditRulesModalOpen(true);
  };

  const handleOpenEmergencyModal = (person: AuthorizedPerson) => {
    setActivePerson(person);
    setIsEmergencyModalOpen(true);
  };

  const handleOpenRevokeModal = (person: AuthorizedPerson) => {
    setActivePerson(person);
    setIsRevokeModalOpen(true);
  };

  const handleViewAudit = async (person: AuthorizedPerson) => {
    if (person.sourceType !== 'delegation') {
      toast({
        title: 'Audit Log',
        description: 'Standard security logs are synchronized with Estate Gate Pass registry.',
      });
      return;
    }

    setActivePerson(person);
    setIsAuditModalOpen(true);
    setLoadingAudit(true);

    try {
      const res = await fetch(`/dashboard/delegation/${person.sourceId}/audit-events`, {
        headers: { Accept: 'application/json' },
      });
      const data = await res.json();
      setAuditEvents(data.events || []);
    } catch {
      toast({
        title: 'Error',
        description: 'Could not fetch audit trail.',
        variant: 'destructive',
      });
    } finally {
      setLoadingAudit(false);
    }
  };

  const handleReissuePass = (person: AuthorizedPerson) => {
    if (person.sourceType !== 'delegation') {
      toast({
        title: 'Pass Issued via Gate Registry',
        description: 'Resident and visitor passes rotate automatically per scheduled security cycles.',
      });
      return;
    }

    setIsReissuing(true);
    router.post(
      `/dashboard/delegation/${person.sourceId}/reissue-pass`,
      {},
      {
        onSuccess: () => {
          toast({
            title: 'QR Credential Rotated',
            description: `A brand-new, cryptographically signed gate credential has been minted for ${person.name}.`,
          });
          setIsReissuing(false);
        },
        onError: () => {
          toast({
            title: 'Reissue Failed',
            description: 'Could not reissue pass. Check network connection.',
            variant: 'destructive',
          });
          setIsReissuing(false);
        },
      }
    );
  };

  const handleSaveRules = (e: React.FormEvent) => {
    e.preventDefault();
    if (!activePerson || activePerson.sourceType !== 'delegation') {
      setIsEditRulesModalOpen(false);
      return;
    }

    router.patch(
      `/dashboard/delegation/${activePerson.sourceId}/rules`,
      editRulesData,
      {
        onSuccess: () => {
          toast({
            title: 'Access Rules Updated',
            description: `Operational schedule and permissions successfully updated for ${activePerson.name}.`,
          });
          setIsEditRulesModalOpen(false);
        },
        onError: () => {
          toast({
            title: 'Update Failed',
            description: 'Please verify the rule parameters.',
            variant: 'destructive',
          });
        },
      }
    );
  };

  const handleCreateAuthorization = (e: React.FormEvent) => {
    e.preventDefault();
    router.post('/dashboard/delegation', formData, {
      onSuccess: () => {
        toast({
          title: 'Person Authorized',
          description: `${formData.name} is now registered under ${formData.access_level} with digital credentials.`,
        });
        setIsAuthorizeModalOpen(false);
      },
      onError: (err) => {
        toast({
          title: 'Authorization Failed',
          description: Object.values(err)[0] as string || 'Please verify form values.',
          variant: 'destructive',
        });
      },
    });
  };

  const handleActivateEmergency = (e: React.FormEvent) => {
    e.preventDefault();
    if (!activePerson) return;

    router.post(
      `/dashboard/delegation/${activePerson.sourceId}/emergency/activate`,
      {
        reason: emergencyReason,
        duration_days: emergencyDurationDays,
        notes: emergencyNotes,
      },
      {
        onSuccess: () => {
          toast({
            title: 'Emergency Access Activated',
            description: `Emergency override activated for ${activePerson.name} (${emergencyDurationDays} days). Estate security alerted.`,
          });
          setIsEmergencyModalOpen(false);
        },
        onError: () => {
          toast({
            title: 'Activation Failed',
            description: 'Could not activate emergency override.',
            variant: 'destructive',
          });
        },
      }
    );
  };

  const handleDeactivateEmergency = (person: AuthorizedPerson) => {
    router.post(
      `/dashboard/delegation/${person.sourceId}/emergency/deactivate`,
      {},
      {
        onSuccess: () => {
          toast({
            title: 'Emergency Deactivated',
            description: `Access for ${person.name} returned to standard limits.`,
          });
        },
      }
    );
  };

  const handleRevoke = (e: React.FormEvent) => {
    e.preventDefault();
    if (!activePerson) return;

    router.post(
      `/dashboard/delegation/${activePerson.sourceId}/revoke`,
      { reason: revokeReason },
      {
        onSuccess: () => {
          toast({
            title: 'Access Revoked',
            description: `Access credentials for ${activePerson.name} were permanently invalidated.`,
          });
          setIsRevokeModalOpen(false);
        },
        onError: () => {
          toast({
            title: 'Revoke Failed',
            description: 'Could not revoke access.',
            variant: 'destructive',
          });
        },
      }
    );
  };

  const handleSaveContinuityPlan = (e: React.FormEvent) => {
    e.preventDefault();
    router.post('/dashboard/delegation/continuity-plan', planForm, {
      onSuccess: () => {
        toast({
          title: 'Continuity Plan Updated',
          description: 'Emergency and incapacity succession parameters locked in with estate administration.',
        });
        setIsContinuityModalOpen(false);
      },
      onError: () => {
        toast({
          title: 'Update Failed',
          description: 'Please verify all continuity requirements.',
          variant: 'destructive',
        });
      },
    });
  };

  // Filtered People List
  const filteredPeople = useMemo(() => {
    return authorizedPeople.filter((p) => {
      // Category tab filter
      if (selectedCategory !== 'all') {
        if (selectedCategory === 'staff') {
          if (!['staff', 'domestic_staff', 'caregivers', 'property_managers'].includes(p.categoryKey)) {
            return false;
          }
        } else if (selectedCategory === 'legacy_contacts') {
          if (!['legacy_contacts', 'family_members'].includes(p.categoryKey)) {
            return false;
          }
        } else if (selectedCategory === 'my_visitors') {
          if (!['my_visitors', 'visitors'].includes(p.categoryKey)) {
            return false;
          }
        } else if (selectedCategory === 'property_services') {
          if (!['property_services', 'staff', 'domestic_staff', 'caregivers', 'property_managers', 'contractors'].includes(p.categoryKey)) {
            return false;
          }
        } else if (p.categoryKey !== selectedCategory) {
          return false;
        }
      }

      // Status filter
      if (statusFilter === 'active' && p.status !== 'active') return false;
      if (statusFilter === 'pending' && p.status !== 'pending') return false;
      if (statusFilter === 'revoked_expired' && !['expired', 'revoked'].includes(p.status)) return false;

      // Search query
      if (searchQuery.trim()) {
        const q = searchQuery.toLowerCase();
        const matchesName = p.name.toLowerCase().includes(q);
        const matchesEmail = p.email?.toLowerCase().includes(q);
        const matchesPhone = p.phone?.toLowerCase().includes(q);
        const matchesPassId = p.credential?.passId.toLowerCase().includes(q);
        const matchesRel = p.relationship.toLowerCase().includes(q);
        const matchesCat = p.categoryLabel.toLowerCase().includes(q);
        if (!matchesName && !matchesEmail && !matchesPhone && !matchesPassId && !matchesRel && !matchesCat) {
          return false;
        }
      }

      return true;
    });
  }, [authorizedPeople, selectedCategory, statusFilter, searchQuery]);

  // Tab Count Helper
  const getTabCount = (tabKey: string) => {
    if (tabKey === 'all') {
      return categoryCounts.all ?? authorizedPeople.length;
    }
    if (tabKey === 'staff') {
      return (categoryCounts.staff ?? 0) || ((categoryCounts.domestic_staff ?? 0) + (categoryCounts.caregivers ?? 0) + (categoryCounts.property_managers ?? 0));
    }
    if (tabKey === 'legacy_contacts') {
      return (categoryCounts.legacy_contacts ?? 0) + (categoryCounts.family_members ?? 0);
    }
    if (tabKey === 'my_visitors') {
      return (categoryCounts.my_visitors ?? 0) || (categoryCounts.visitors ?? 0) || authorizedPeople.filter((p) => ['my_visitors', 'visitors'].includes(p.categoryKey)).length;
    }
    if (tabKey === 'property_services') {
      return categoryCounts.property_services ?? authorizedPeople.filter((p) => ['property_services', 'staff', 'domestic_staff', 'caregivers', 'property_managers', 'contractors'].includes(p.categoryKey)).length;
    }
    return categoryCounts[tabKey] ?? authorizedPeople.filter((p) => p.categoryKey === tabKey).length;
  };

  // Helper: Credential Shape Renderer
  const renderShapeIcon = (shape?: string) => {
    switch (shape) {
      case 'Hexagon':
        return <div className="w-5 h-5 rounded-sm rotate-45 border-2 border-emerald-500 bg-emerald-500/20 flex items-center justify-center text-[10px] font-bold text-emerald-400">H</div>;
      case 'RoundedSquare':
        return <div className="w-5 h-5 rounded-md border-2 border-indigo-500 bg-indigo-500/20 flex items-center justify-center text-[10px] font-bold text-indigo-400">L</div>;
      case 'HouseHex':
        return <div className="w-5 h-5 rounded-t-lg rounded-b-sm border-2 border-teal-500 bg-teal-500/20 flex items-center justify-center text-[10px] font-bold text-teal-400">S</div>;
      case 'Pentagon':
        return <div className="w-5 h-5 clip-polygon border-2 border-orange-500 bg-orange-500/20 flex items-center justify-center text-[10px] font-bold text-orange-400">C</div>;
      case 'Circle':
        return <div className="w-5 h-5 rounded-full border-2 border-cyan-500 bg-cyan-500/20 flex items-center justify-center text-[10px] font-bold text-cyan-400">V</div>;
      case 'Octagon':
      default:
        return <div className="w-5 h-5 rounded-sm border-2 border-amber-500 bg-amber-500/20 flex items-center justify-center text-[10px] font-bold text-amber-400">D</div>;
    }
  };

  // Reusable Individual Person Card Component
  const renderPersonCard = (person: AuthorizedPerson) => {
    const isActive = person.status === 'active' && person.credential?.isActive;
    const isFormer = person.categoryKey === 'former_occupants' || ['revoked', 'expired'].includes(person.status);

    return (
      <Card
        key={person.id}
        className={`relative flex flex-col justify-between overflow-hidden border transition-all hover:shadow-md ${
          person.isEmergencyActive
            ? 'border-red-500/50 bg-red-950/10'
            : isFormer
            ? 'border-border/30 opacity-75 bg-muted/10'
            : 'border-border/60 hover:border-primary/40 bg-card/80'
        }`}
      >
        <CardHeader className="p-4 pb-2 space-y-2">
          <div className="flex items-start justify-between gap-2">
            <div className="flex items-center gap-2">
              {renderShapeIcon(person.credential?.shape)}
              <div>
                <Badge
                  variant="outline"
                  className={`text-[10px] font-semibold tracking-wide uppercase px-2 py-0.5 ${person.categoryBadgeColor}`}
                >
                  {person.credential?.categoryCode || 'GP'} • {person.categoryLabel}
                </Badge>
              </div>
            </div>

            <div className="flex items-center gap-1">
              {person.durationLabel && (
                <span
                  className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold border ${
                    person.durationType === '2_hours'
                      ? 'bg-amber-500/10 text-amber-400 border-amber-500/30'
                      : person.durationType === '1_day'
                      ? 'bg-blue-500/10 text-blue-400 border-blue-500/30'
                      : person.durationType === '1_week'
                      ? 'bg-purple-500/10 text-purple-400 border-purple-500/30'
                      : person.durationType === 'recurring'
                      ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30'
                      : person.durationType === 'manual_revocation'
                      ? 'bg-teal-500/10 text-teal-400 border-teal-500/30'
                      : 'bg-zinc-500/10 text-zinc-400 border-zinc-500/30'
                  }`}
                >
                  <Clock className="w-2.5 h-2.5" />
                  {person.durationLabel}
                </span>
              )}
              {person.isEmergencyActive && (
                <Badge className="bg-red-500 text-white text-[10px] font-bold animate-pulse">
                  EMERGENCY
                </Badge>
              )}
              <span
                className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium border ${
                  isActive
                    ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30'
                    : isFormer
                    ? 'bg-zinc-500/10 text-zinc-400 border-zinc-500/30'
                    : 'bg-amber-500/10 text-amber-400 border-amber-500/30'
                }`}
              >
                <span className={`w-1.5 h-1.5 rounded-full ${isActive ? 'bg-emerald-400 animate-ping' : isFormer ? 'bg-zinc-400' : 'bg-amber-400'}`} />
                {person.status.toUpperCase()}
              </span>
            </div>
          </div>

          <div>
            <h3 className="text-base font-semibold text-foreground tracking-tight flex items-center justify-between">
              <span>{person.name}</span>
            </h3>
            <p className="text-xs text-muted-foreground font-medium">
              {person.relationship} • {person.propertyLabel}
            </p>
          </div>
        </CardHeader>

        <CardContent className="p-4 pt-2 space-y-3 flex-1 flex flex-col justify-between">
          {/* Contact details */}
          <div className="space-y-1 text-xs text-muted-foreground pt-1 border-t border-border/30">
            {person.email && (
              <div className="flex items-center gap-1.5 truncate">
                <Mail className="w-3.5 h-3.5 text-muted-foreground/70" />
                <span className="truncate">{person.email}</span>
              </div>
            )}
            {person.phone && (
              <div className="flex items-center gap-1.5">
                <Phone className="w-3.5 h-3.5 text-muted-foreground/70" />
                <span>{person.phone}</span>
              </div>
            )}
            <div className="flex items-center gap-1.5">
              <Clock className="w-3.5 h-3.5 text-muted-foreground/70" />
              <span>
                {person.accessRules?.allowed_days ? `${person.accessRules.allowed_days.join(', ')} • ` : ''}
                {person.accessRules?.entry_start_time || '00:00'} - {person.accessRules?.entry_end_time || '23:59'}
              </span>
            </div>
          </div>

          {/* Permissions & Security Flags */}
          <div className="flex flex-wrap gap-1 pt-1">
            {person.accessRules?.gate_access !== false && (
              <span className="inline-flex items-center gap-1 text-[10px] bg-muted/60 text-foreground px-2 py-0.5 rounded border border-border/40">
                <Lock className="w-3 h-3 text-primary" /> Gate Pass
              </span>
            )}
            {person.accessRules?.pool_access && (
              <span className="inline-flex items-center gap-1 text-[10px] bg-muted/60 text-foreground px-2 py-0.5 rounded border border-border/40">
                <Waves className="w-3 h-3 text-cyan-400" /> Pool
              </span>
            )}
            {person.accessRules?.gym_access && (
              <span className="inline-flex items-center gap-1 text-[10px] bg-muted/60 text-foreground px-2 py-0.5 rounded border border-border/40">
                <Dumbbell className="w-3 h-3 text-purple-400" /> Fitness
              </span>
            )}
            {person.accessRules?.parking_allocated && (
              <span className="inline-flex items-center gap-1 text-[10px] bg-muted/60 text-foreground px-2 py-0.5 rounded border border-border/40">
                <Car className="w-3 h-3 text-emerald-400" /> Parking
              </span>
            )}
            {person.accessRules?.requires_id_verification && (
              <span className="inline-flex items-center gap-1 text-[10px] bg-amber-500/10 text-amber-400 px-2 py-0.5 rounded border border-amber-500/30 font-medium">
                <ShieldAlert className="w-3 h-3" /> ID Check
              </span>
            )}
          </div>

          {/* QR Credential Snippet */}
          <div className="pt-2 border-t border-border/30 flex items-center justify-between">
            <div className="flex items-center gap-2">
              <div className="p-1.5 rounded-lg bg-primary/10 text-primary border border-primary/20">
                <QrCode className="w-4 h-4" />
              </div>
              <div>
                <p className="text-[10px] text-muted-foreground uppercase font-mono">
                  {person.credential?.passId || 'PASS-PENDING'}
                </p>
                <p className="text-[11px] font-semibold">Digital Credential</p>
              </div>
            </div>

            <Button
              size="sm"
              variant="secondary"
              onClick={() => handleOpenCredentialModal(person)}
              className="h-7 text-xs px-2.5 gap-1.5"
            >
              <span>View Pass</span>
              <ChevronRight className="w-3 h-3" />
            </Button>
          </div>

          {/* Action Controls */}
          <div className="pt-2 border-t border-border/30 flex items-center justify-between gap-1">
            {person.sourceType === 'delegation' ? (
              person.canManage === false ? (
                <div className="flex items-center gap-1.5 text-[11px] text-muted-foreground/80 py-1 font-medium">
                  <ShieldCheck className="w-3.5 h-3.5 text-indigo-400" />
                  <span>Authorized for Property</span>
                </div>
              ) : (
                <>
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => handleOpenEditRulesModal(person)}
                    className="h-7 px-2 text-[11px] text-muted-foreground hover:text-foreground gap-1"
                  >
                    <SlidersHorizontal className="w-3 h-3" />
                    <span>Edit Rules</span>
                  </Button>

                  <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                      <Button variant="ghost" size="sm" className="h-7 w-7 p-0">
                        <MoreVertical className="w-3.5 h-3.5" />
                      </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" className="w-48 text-xs">
                      <DropdownMenuLabel>Credential Actions</DropdownMenuLabel>
                      <DropdownMenuItem onClick={() => handleReissuePass(person)}>
                        <RefreshCw className="w-3.5 h-3.5 mr-2" /> Reissue / Rotate QR
                      </DropdownMenuItem>
                      <DropdownMenuItem onClick={() => handleViewAudit(person)}>
                        <History className="w-3.5 h-3.5 mr-2" /> Audit Trail & Events
                      </DropdownMenuItem>
                      <DropdownMenuSeparator />
                      {person.isEmergencyActive ? (
                        <DropdownMenuItem onClick={() => handleDeactivateEmergency(person)}>
                          <Shield className="w-3.5 h-3.5 mr-2 text-emerald-400" /> End Emergency Mode
                        </DropdownMenuItem>
                      ) : (
                        <DropdownMenuItem onClick={() => handleOpenEmergencyModal(person)}>
                          <AlertTriangle className="w-3.5 h-3.5 mr-2 text-amber-400" /> Trigger Emergency
                        </DropdownMenuItem>
                      )}
                      <DropdownMenuSeparator />
                      <DropdownMenuItem
                        onClick={() => handleOpenRevokeModal(person)}
                        className="text-red-400 focus:text-red-400"
                      >
                        <Ban className="w-3.5 h-3.5 mr-2" /> Revoke Authorization
                      </DropdownMenuItem>
                    </DropdownMenuContent>
                  </DropdownMenu>
                </>
              )
            ) : (
              <div className="text-[10px] text-muted-foreground/80 py-1">
                {person.sourceType === 'user' ? 'Managed via Primary Ownership' : person.sourceType === 'renter' ? 'Managed via Lease Agreement' : person.sourceType === 'household_member' ? 'Managed via Household Registry' : 'Managed via Estate Gate Pass'}
              </div>
            )}
          </div>
        </CardContent>
      </Card>
    );
  };

  return (
    <DashboardLayout>
      <Head title={pageTitle || `${unitLabel || 'My Property'} — People & Access | Community Hub`} />

      <div className="space-y-6 max-w-7xl mx-auto pb-16">
        {/* Breadcrumb Navigation & Top Action Bar */}
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-border/40 pb-5">
          <div>
            <div className="flex items-center gap-2 text-xs font-medium text-muted-foreground mb-1.5">
              <span>{unitLabel || 'My Property'}</span>
              <ChevronRight className="w-3.5 h-3.5 text-muted-foreground/60" />
              <span className="text-foreground font-semibold">
                {isRenter ? 'My Access Network' : 'People & Access'}
              </span>
              <Badge variant="outline" className={`ml-2 text-[10px] py-0 px-2 ${isRenter ? 'border-indigo-500/30 text-indigo-400 bg-indigo-500/10' : 'border-emerald-500/30 text-emerald-400 bg-emerald-500/10'}`}>
                {scopeBadge || permissions?.scopeBadge || (isRenter ? 'Tenant Access Scope • Bounded by Lease' : 'Zero Cross-Unit Leakage • Scoped')}
              </Badge>
            </div>
            <h1 className="text-2xl sm:text-3xl font-bold tracking-tight">
              {pageTitle || (isRenter ? `${unitLabel || 'My Property'} — My Access Network` : `${unitLabel || 'My Property'} — People & Access`)}
            </h1>
            <p className="text-sm text-muted-foreground mt-0.5">
              {pageSubtitle || (isRenter
                ? `Authorized occupants, visitors, staff, and contractors for your tenancy at ${unitLabel || 'your property'}.`
                : `Authorized occupants, guests, staff, contractors, and legacy delegates strictly associated with ${unitLabel || 'your property'}.`
              )}
            </p>
          </div>

          <div className="flex flex-wrap items-center gap-2.5">
            {!isRenter && !isShortTerm && (permissions?.isHomeowner || permissions?.isAdmin) && (
              <Button
                variant="outline"
                size="sm"
                onClick={() => setIsContinuityModalOpen(true)}
                className="gap-2 border-amber-500/30 hover:border-amber-500/60 text-amber-400 hover:text-amber-300"
              >
                <ShieldAlert className="w-4 h-4 text-amber-500" />
                <span>Continuity Plan</span>
              </Button>
            )}

            <Button
              size="sm"
              onClick={() => handleOpenAuthorizeModal(selectedCategory !== 'all' ? selectedCategory : (isShortTerm ? 'visitors' : 'long_term_occupants'))}
              className="gap-2 bg-primary hover:bg-primary/90 shadow-sm"
            >
              <Plus className="w-4 h-4" />
              <span>{isShortTerm ? 'Pre-Clear Visitor' : 'Authorize Person'}</span>
            </Button>
          </div>
        </div>

        {/* Short-Term Rental / Airbnb Guest Scope Banner */}
        {isShortTerm && (
          <div className="space-y-3">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-4 rounded-xl border border-pink-500/30 bg-pink-500/10 text-pink-300 shadow-xs">
              <div className="flex items-center gap-3">
                <div className="p-2 rounded-lg bg-pink-500/20 text-pink-400 shrink-0">
                  <Sparkles className="w-5 h-5" />
                </div>
                <div>
                  <p className="text-xs sm:text-sm font-semibold text-foreground">Short-Term Guest Access Network Active</p>
                  <p className="text-[11px] sm:text-xs text-pink-300/80">
                    Scoped strictly for your stay at {unitLabel || 'your unit'}. Bounded by reservation departure {permissions?.checkoutDate ? `(checkout: ${permissions.checkoutDate})` : ''}. Only your stay details, pre-cleared visitors, QR gate credentials, and authorized property services are visible.
                  </p>
                </div>
              </div>
              <div className="flex items-center gap-2 self-start sm:self-auto shrink-0">
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={() => setShowScopeMatrix(!showScopeMatrix)}
                  className="h-7 text-xs px-2 text-pink-300 hover:text-white hover:bg-pink-500/20 gap-1 border border-pink-500/20"
                >
                  <Lock className="w-3 h-3 text-pink-400" />
                  <span>{showScopeMatrix ? 'Hide Scope Matrix' : 'Permissions & Scope'}</span>
                </Button>
                <Badge variant="outline" className="border-pink-500/30 text-pink-400 bg-pink-500/10 text-[10px]">
                  Reservation Bounded
                </Badge>
                <Badge variant="outline" className="border-emerald-500/30 text-emerald-400 bg-emerald-500/10 text-[10px]">
                  Active Stay
                </Badge>
              </div>
            </div>

            {/* Expandable Permissions & Security Boundaries Matrix for Short-Term */}
            {showScopeMatrix && (
              <Card className="border border-pink-500/20 bg-card/95 backdrop-blur-sm overflow-hidden animate-in fade-in slide-in-from-top-2 duration-200">
                <CardHeader className="p-4 pb-2 border-b border-border/30">
                  <div className="flex items-center justify-between">
                    <div>
                      <CardTitle className="text-sm font-bold flex items-center gap-2 text-foreground">
                        <Shield className="w-4 h-4 text-pink-400" />
                        <span>Short-Term Rental Scope & Isolated Access Boundaries</span>
                      </CardTitle>
                      <CardDescription className="text-xs text-muted-foreground mt-0.5">
                        Strict data minimization: Guests see only what is needed for their stay window without exposing the homeowner's personal network.
                      </CardDescription>
                    </div>
                    <Badge variant="secondary" className="text-[10px] font-mono">
                      ROLE: SHORT-TERM GUEST
                    </Badge>
                  </div>
                </CardHeader>
                <CardContent className="p-4 pt-3 grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                  {/* Allowed Scope */}
                  <div className="space-y-2 p-3 rounded-lg border border-emerald-500/20 bg-emerald-500/5">
                    <div className="flex items-center gap-1.5 text-emerald-400 font-semibold text-xs">
                      <CheckCircle2 className="w-4 h-4" />
                      <span>Authorized Visibility & Stay Privileges</span>
                    </div>
                    <ul className="space-y-1.5 text-muted-foreground">
                      <li className="flex items-center gap-2">
                        <Check className="w-3.5 h-3.5 text-emerald-400 shrink-0" />
                        <span><strong>My Stay:</strong> Reservation check-in / check-out window & {unitLabel} host info</span>
                      </li>
                      <li className="flex items-center gap-2">
                        <Check className="w-3.5 h-3.5 text-emerald-400 shrink-0" />
                        <span><strong>My Visitors:</strong> Pre-clear social visitors & guests registered for your stay</span>
                      </li>
                      <li className="flex items-center gap-2">
                        <Check className="w-3.5 h-3.5 text-emerald-400 shrink-0" />
                        <span><strong>My Access:</strong> Dynamic QR gate credential (60s rotation) & Main Gate entry</span>
                      </li>
                      <li className="flex items-center gap-2">
                        <Check className="w-3.5 h-3.5 text-emerald-400 shrink-0" />
                        <span><strong>Property Services:</strong> Authorized housekeeping & maintenance for {unitLabel} (read-only)</span>
                      </li>
                    </ul>
                  </div>

                  {/* Restricted Scope */}
                  <div className="space-y-2 p-3 rounded-lg border border-rose-500/20 bg-rose-500/5">
                    <div className="flex items-center gap-1.5 text-rose-400 font-semibold text-xs">
                      <Lock className="w-4 h-4" />
                      <span>Strictly Excluded & Restricted Boundaries</span>
                    </div>
                    <ul className="space-y-1.5 text-muted-foreground">
                      <li className="flex items-center gap-2">
                        <Ban className="w-3.5 h-3.5 text-rose-400 shrink-0" />
                        <span><strong>Homeowner Legacy Contacts:</strong> Excluded • Zero exposure to homeowner estate network</span>
                      </li>
                      <li className="flex items-center gap-2">
                        <Ban className="w-3.5 h-3.5 text-rose-400 shrink-0" />
                        <span><strong>Unrelated Residents & Co-Owners:</strong> Excluded • Private to permanent occupants</span>
                      </li>
                      <li className="flex items-center gap-2">
                        <Ban className="w-3.5 h-3.5 text-rose-400 shrink-0" />
                        <span><strong>Community-Wide Staff & Vendors:</strong> Excluded • Only {unitLabel} assigned services</span>
                      </li>
                      <li className="flex items-center gap-2">
                        <Ban className="w-3.5 h-3.5 text-rose-400 shrink-0" />
                        <span><strong>Unrestricted Credentials:</strong> Restricted • All passes clamped to checkout date</span>
                      </li>
                    </ul>
                  </div>
                </CardContent>
              </Card>
            )}
          </div>
        )}

        {/* Tenant Guardrails Info Banner for Long-Term Renter */}
        {isRenter && (
          <div className="space-y-3">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-4 rounded-xl border border-indigo-500/30 bg-indigo-500/10 text-indigo-300 shadow-xs">
              <div className="flex items-center gap-3">
                <div className="p-2 rounded-lg bg-indigo-500/20 text-indigo-400 shrink-0">
                  <ShieldCheck className="w-5 h-5" />
                </div>
                <div>
                  <p className="text-xs sm:text-sm font-semibold text-foreground">Tenant Access Network Active</p>
                  <p className="text-[11px] sm:text-xs text-indigo-300/80">
                    Scoped strictly for your tenancy at {unitLabel || 'your property'}. Authorized visitors and service passes are bounded by your lease {permissions?.leaseEndDate ? `(expires ${permissions.leaseEndDate})` : 'term'}. Permanent passes and property ownership controls remain reserved for homeowners.
                  </p>
                </div>
              </div>
              <div className="flex items-center gap-2 self-start sm:self-auto shrink-0">
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={() => setShowScopeMatrix(!showScopeMatrix)}
                  className="h-7 text-xs px-2 text-indigo-300 hover:text-white hover:bg-indigo-500/20 gap-1 border border-indigo-500/20"
                >
                  <Lock className="w-3 h-3 text-indigo-400" />
                  <span>{showScopeMatrix ? 'Hide Scope Matrix' : 'Permissions & Scope'}</span>
                </Button>
                <Badge variant="outline" className="border-indigo-500/30 text-indigo-400 bg-indigo-500/10 text-[10px]">
                  Lease Bounded
                </Badge>
                <Badge variant="outline" className="border-emerald-500/30 text-emerald-400 bg-emerald-500/10 text-[10px]">
                  Active Tenancy
                </Badge>
              </div>
            </div>

            {/* Expandable Permissions & Security Boundaries Matrix */}
            {showScopeMatrix && (
              <Card className="border border-indigo-500/20 bg-card/95 backdrop-blur-sm overflow-hidden animate-in fade-in slide-in-from-top-2 duration-200">
                <CardHeader className="p-4 pb-2 border-b border-border/30">
                  <div className="flex items-center justify-between">
                    <div>
                      <CardTitle className="text-sm font-bold flex items-center gap-2 text-foreground">
                        <Shield className="w-4 h-4 text-indigo-400" />
                        <span>Tenant Access Scope & Enforced Privilege Separation</span>
                      </CardTitle>
                      <CardDescription className="text-xs text-muted-foreground mt-0.5">
                        Separation of capabilities: Long-term renters receive property access without inheriting homeowner administrative or financial authority.
                      </CardDescription>
                    </div>
                    <Badge variant="secondary" className="text-[10px] font-mono">
                      ROLE: LONG-TERM RENTER
                    </Badge>
                  </div>
                </CardHeader>
                <CardContent className="p-4 pt-3 grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                  {/* Allowed Scope */}
                  <div className="space-y-2 p-3 rounded-lg border border-emerald-500/20 bg-emerald-500/5">
                    <div className="flex items-center gap-1.5 text-emerald-400 font-semibold text-xs">
                      <CheckCircle2 className="w-4 h-4" />
                      <span>Authorized Visibility & Tenancy Privileges</span>
                    </div>
                    <ul className="space-y-1.5 text-muted-foreground">
                      <li className="flex items-center gap-2">
                        <Check className="w-3.5 h-3.5 text-emerald-400 shrink-0" />
                        <span><strong>Own Household & Occupants:</strong> Co-occupants residing under lease</span>
                      </li>
                      <li className="flex items-center gap-2">
                        <Check className="w-3.5 h-3.5 text-emerald-400 shrink-0" />
                        <span><strong>Invited Visitors:</strong> Day visitors, delivery passes, and scheduled guests</span>
                      </li>
                      <li className="flex items-center gap-2">
                        <Check className="w-3.5 h-3.5 text-emerald-400 shrink-0" />
                        <span><strong>Staff for Property:</strong> Domestic staff and cleaners authorized for {unitLabel}</span>
                      </li>
                      <li className="flex items-center gap-2">
                        <Check className="w-3.5 h-3.5 text-emerald-400 shrink-0" />
                        <span><strong>Contractors for Property:</strong> Maintenance technicians cleared for {unitLabel}</span>
                      </li>
                      <li className="flex items-center gap-2">
                        <Check className="w-3.5 h-3.5 text-emerald-400 shrink-0" />
                        <span><strong>Personal Legacy Contacts:</strong> Designated tenant emergency contacts</span>
                      </li>
                    </ul>
                  </div>

                  {/* Restricted Scope */}
                  <div className="space-y-2 p-3 rounded-lg border border-amber-500/20 bg-amber-500/5">
                    <div className="flex items-center gap-1.5 text-amber-400 font-semibold text-xs">
                      <Lock className="w-4 h-4" />
                      <span>Separate Homeowner Capabilities (Restricted)</span>
                    </div>
                    <ul className="space-y-1.5 text-muted-foreground">
                      <li className="flex items-center gap-2">
                        <Ban className="w-3.5 h-3.5 text-amber-400 shrink-0" />
                        <span><strong>Remove Homeowner:</strong> Restricted • Cannot remove property owner</span>
                      </li>
                      <li className="flex items-center gap-2">
                        <Ban className="w-3.5 h-3.5 text-amber-400 shrink-0" />
                        <span><strong>Property Ownership:</strong> Restricted • Deed and titles are immutable</span>
                      </li>
                      <li className="flex items-center gap-2">
                        <Ban className="w-3.5 h-3.5 text-amber-400 shrink-0" />
                        <span><strong>Homeowner Financials:</strong> Restricted • Billing ledgers private to owner</span>
                      </li>
                      <li className="flex items-center gap-2">
                        <Ban className="w-3.5 h-3.5 text-amber-400 shrink-0" />
                        <span><strong>Unrestricted Credentials:</strong> Restricted • Bounded by lease term {permissions?.leaseEndDate ? `(${permissions.leaseEndDate})` : ''}</span>
                      </li>
                      <li className="flex items-center gap-2">
                        <Ban className="w-3.5 h-3.5 text-amber-400 shrink-0" />
                        <span><strong>Community-Level Settings:</strong> Restricted • Admin policy enforced</span>
                      </li>
                    </ul>
                  </div>
                </CardContent>
              </Card>
            )}
          </div>
        )}

        {/* Category Quick-Filter Navigation Bar */}
        <div className="overflow-x-auto pb-2 -mx-4 px-4 sm:mx-0 sm:px-0">
          <div className="flex items-center gap-1.5 min-w-max p-1 bg-muted/30 rounded-xl border border-border/40">
            {activeCategoryTabs.map((tab) => {
              const Icon = tab.icon;
              const count = getTabCount(tab.key);
              const isSelected = selectedCategory === tab.key;

              return (
                <button
                  key={tab.key}
                  onClick={() => {
                    setSelectedCategory(tab.key);
                    if (tab.key !== 'all' && viewMode === 'sections') {
                      setViewMode('cards');
                    }
                  }}
                  className={`flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-medium transition-all ${
                    isSelected
                      ? 'bg-background text-foreground shadow-sm font-semibold border border-border/80'
                      : 'text-muted-foreground hover:text-foreground hover:bg-background/40'
                  }`}
                >
                  <Icon className={`w-3.5 h-3.5 ${isSelected ? 'text-primary' : 'text-muted-foreground'}`} />
                  <span>{tab.label}</span>
                  <span
                    className={`ml-1 px-1.5 py-0.2 rounded-full text-[10px] font-bold ${
                      isSelected
                        ? 'bg-primary/15 text-primary'
                        : 'bg-muted text-muted-foreground'
                    }`}
                  >
                    {count}
                  </span>
                </button>
              );
            })}
          </div>
        </div>

        {/* Search, Status & View Toggle Control Bar */}
        <div className="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 bg-card/60 p-3 rounded-xl border border-border/40">
          <div className="relative flex-1 min-w-[240px]">
            <Search className="absolute left-3 top-2.5 h-4 w-4 text-muted-foreground" />
            <Input
              type="text"
              placeholder="Search by name, pass ID (GP-...), email, or role..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="pl-9 h-9 text-xs bg-background/60"
            />
          </div>

          <div className="flex items-center gap-2">
            <Select value={statusFilter} onValueChange={setStatusFilter}>
              <SelectTrigger className="w-[140px] h-9 text-xs bg-background/60">
                <SelectValue placeholder="All Statuses" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All Statuses</SelectItem>
                <SelectItem value="active">Active Only</SelectItem>
                <SelectItem value="pending">Pending Approval</SelectItem>
                <SelectItem value="revoked_expired">Expired / Revoked</SelectItem>
              </SelectContent>
            </Select>

            <div className="flex items-center rounded-lg border border-border/40 p-0.5 bg-muted/20">
              {selectedCategory === 'all' && (
                <Button
                  variant={viewMode === 'sections' ? 'secondary' : 'ghost'}
                  size="sm"
                  onClick={() => setViewMode('sections')}
                  className="h-8 px-2.5 text-xs gap-1.5"
                >
                  <Layers className="w-3.5 h-3.5 text-primary" />
                  <span>{isRenter ? '5 Sections' : '7 Sections'}</span>
                </Button>
              )}
              <Button
                variant={viewMode === 'cards' ? 'secondary' : 'ghost'}
                size="sm"
                onClick={() => setViewMode('cards')}
                className="h-8 px-2.5 text-xs"
              >
                Cards
              </Button>
              <Button
                variant={viewMode === 'table' ? 'secondary' : 'ghost'}
                size="sm"
                onClick={() => setViewMode('table')}
                className="h-8 px-2.5 text-xs"
              >
                Table
              </Button>
            </div>
          </div>
        </div>

        {/* Emergency Mode Warning Banner if Active */}
        {authorizedPeople.some((p) => p.isEmergencyActive) && (
          <div className="flex items-center justify-between p-4 rounded-xl border border-red-500/40 bg-red-500/10 text-red-400">
            <div className="flex items-center gap-3">
              <div className="p-2 rounded-lg bg-red-500/20 text-red-500">
                <AlertTriangle className="w-5 h-5 animate-pulse" />
              </div>
              <div>
                <p className="text-sm font-semibold">Active Emergency Access Override Declared</p>
                <p className="text-xs text-red-300/80">
                  Elevated security clearance is actively unlocked for designated emergency delegates. Estate gate guards are notified.
                </p>
              </div>
            </div>
            <Button
              size="sm"
              variant="outline"
              onClick={() => {
                const active = authorizedPeople.find((p) => p.isEmergencyActive);
                if (active) handleDeactivateEmergency(active);
              }}
              className="border-red-500/40 hover:bg-red-500/20 text-red-300 text-xs"
            >
              Deactivate Emergency
            </Button>
          </div>
        )}

        {/* Main Render Views: Grouped Sections, Cards Grid, or Table View */}
        {viewMode === 'sections' && selectedCategory === 'all' ? (
          <div className="space-y-6">
            {activeSectionsConfig.map((sec) => {
              const SectionIcon = sec.icon;
              let secItems: AuthorizedPerson[] = [];
              if (sec.key === 'residents') secItems = filteredPeople.filter((p) => p.categoryKey === 'residents');
              else if (sec.key === 'long_term_occupants') secItems = filteredPeople.filter((p) => p.categoryKey === 'long_term_occupants');
              else if (sec.key === 'short_term_rentals') secItems = filteredPeople.filter((p) => p.categoryKey === 'short_term_rentals');
              else if (sec.key === 'visitors') secItems = filteredPeople.filter((p) => p.categoryKey === 'visitors');
              else if (sec.key === 'staff') secItems = filteredPeople.filter((p) => ['staff', 'domestic_staff', 'caregivers', 'property_managers'].includes(p.categoryKey));
              else if (sec.key === 'contractors') secItems = filteredPeople.filter((p) => p.categoryKey === 'contractors');
              else if (sec.key === 'legacy_contacts') secItems = filteredPeople.filter((p) => ['legacy_contacts', 'family_members'].includes(p.categoryKey));
              else if (sec.key === 'my_stay') secItems = filteredPeople.filter((p) => p.categoryKey === 'my_stay');
              else if (sec.key === 'my_visitors') secItems = filteredPeople.filter((p) => p.categoryKey === 'my_visitors' || p.categoryKey === 'visitors');
              else if (sec.key === 'my_access') secItems = filteredPeople.filter((p) => p.categoryKey === 'my_access');
              else if (sec.key === 'property_services') secItems = filteredPeople.filter((p) => p.categoryKey === 'property_services' || ['staff', 'domestic_staff', 'caregivers', 'property_managers', 'contractors'].includes(p.categoryKey));

              return (
                <div key={sec.key} className="space-y-3.5 border border-border/50 bg-card/40 rounded-2xl p-5 shadow-xs transition-all">
                  <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-border/30 pb-3">
                    <div className="flex items-center gap-3">
                      <div className={`p-2 rounded-xl border ${sec.badgeColor}`}>
                        <SectionIcon className="w-5 h-5" />
                      </div>
                      <div>
                        <div className="flex items-center gap-2">
                          <h2 className="text-base sm:text-lg font-bold tracking-tight text-foreground">{sec.title}</h2>
                          <Badge variant="outline" className={`text-[10px] font-semibold ${sec.badgeColor}`}>
                            {secItems.length} {secItems.length === 1 ? 'Person' : 'People'}
                          </Badge>
                        </div>
                        <p className="text-xs text-muted-foreground font-medium">{sec.subtitle}</p>
                      </div>
                    </div>

                    {sec.actionLabel && (
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => handleOpenAuthorizeModal(sec.actionCategory)}
                        className="h-8 text-xs gap-1.5 self-start sm:self-auto border-border/60 hover:bg-muted/30"
                      >
                        <Plus className="w-3.5 h-3.5" />
                        <span>{sec.actionLabel}</span>
                      </Button>
                    )}
                  </div>

                  {secItems.length > 0 ? (
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 pt-1">
                      {secItems.map((person) => renderPersonCard(person))}
                    </div>
                  ) : (
                    <div className="flex flex-col sm:flex-row items-center justify-between gap-3 p-4 rounded-xl border border-dashed border-border/60 bg-muted/10 text-xs text-muted-foreground">
                      <div className="flex items-center gap-2.5">
                        <Info className="w-4 h-4 text-muted-foreground/70 shrink-0" />
                        <span>{sec.emptyText}</span>
                      </div>
                      {sec.actionLabel && (
                        <Button
                          variant="ghost"
                          size="sm"
                          onClick={() => handleOpenAuthorizeModal(sec.actionCategory)}
                          className="h-7 text-xs text-primary hover:text-primary/80 gap-1"
                        >
                          <span>{sec.actionLabel}</span>
                          <ChevronRight className="w-3 h-3" />
                        </Button>
                      )}
                    </div>
                  )}
                </div>
              );
            })}
          </div>
        ) : viewMode === 'cards' ? (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {filteredPeople.map((person) => renderPersonCard(person))}
          </div>
        ) : (
          /* Table View */
          <div className="overflow-x-auto rounded-xl border border-border/40 bg-card/60">
            <table className="w-full text-left text-xs">
              <thead className="bg-muted/40 border-b border-border/40 text-muted-foreground uppercase tracking-wider text-[10px]">
                <tr>
                  <th className="py-3 px-4">Person & Role</th>
                  <th className="py-3 px-4">Category</th>
                  <th className="py-3 px-4">Schedule & Rules</th>
                  <th className="py-3 px-4">Credential</th>
                  <th className="py-3 px-4">Status</th>
                  <th className="py-3 px-4 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border/20">
                {filteredPeople.map((person) => {
                  const isActive = person.status === 'active' && person.credential?.isActive;
                  return (
                    <tr key={person.id} className="hover:bg-muted/20 transition-colors">
                      <td className="py-3 px-4">
                        <div className="font-semibold text-foreground">{person.name}</div>
                        <div className="text-[11px] text-muted-foreground">{person.email || person.phone || person.relationship}</div>
                      </td>
                      <td className="py-3 px-4">
                        <Badge variant="outline" className={`text-[10px] ${person.categoryBadgeColor}`}>
                          {person.categoryLabel}
                        </Badge>
                      </td>
                      <td className="py-3 px-4">
                        <div className="flex items-center gap-1.5 mb-1">
                          {person.durationLabel && (
                            <span
                              className={`inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[9px] font-semibold border ${
                                person.durationType === '2_hours'
                                  ? 'bg-amber-500/10 text-amber-400 border-amber-500/30'
                                  : person.durationType === '1_day'
                                  ? 'bg-blue-500/10 text-blue-400 border-blue-500/30'
                                  : person.durationType === '1_week'
                                  ? 'bg-purple-500/10 text-purple-400 border-purple-500/30'
                                  : person.durationType === 'recurring'
                                  ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30'
                                  : person.durationType === 'manual_revocation'
                                  ? 'bg-teal-500/10 text-teal-400 border-teal-500/30'
                                  : 'bg-zinc-500/10 text-zinc-400 border-zinc-500/30'
                              }`}
                            >
                              <Clock className="w-2.5 h-2.5" />
                              {person.durationLabel}
                            </span>
                          )}
                          <span className="font-medium text-foreground">{person.accessRules?.allowed_days?.join(', ') || 'Everyday'}</span>
                        </div>
                        <div className="text-[10px] text-muted-foreground">
                          {person.accessRules?.entry_start_time || '00:00'} - {person.accessRules?.entry_end_time || '23:59'}
                        </div>
                      </td>
                      <td className="py-3 px-4">
                        <div className="flex items-center gap-1.5 font-mono text-[11px]">
                          {renderShapeIcon(person.credential?.shape)}
                          <span>{person.credential?.passId || 'GP-PENDING'}</span>
                        </div>
                      </td>
                      <td className="py-3 px-4">
                        <span
                          className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium border ${
                            isActive
                              ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30'
                              : 'bg-zinc-500/10 text-zinc-400 border-zinc-500/30'
                          }`}
                        >
                          {person.status.toUpperCase()}
                        </span>
                      </td>
                      <td className="py-3 px-4 text-right">
                        <div className="flex items-center justify-end gap-1.5">
                          <Button
                            size="sm"
                            variant="secondary"
                            onClick={() => handleOpenCredentialModal(person)}
                            className="h-7 text-xs px-2"
                          >
                            <QrCode className="w-3.5 h-3.5 mr-1" /> View Pass
                          </Button>
                          {person.sourceType === 'delegation' && (
                            <Button
                              size="sm"
                              variant="ghost"
                              onClick={() => handleOpenEditRulesModal(person)}
                              className="h-7 text-xs px-2"
                            >
                              <SlidersHorizontal className="w-3.5 h-3.5" />
                            </Button>
                          )}
                        </div>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}


        {filteredPeople.length === 0 && (
          <div className="text-center py-16 border border-dashed border-border/60 rounded-xl bg-card/20 space-y-3">
            <Users className="w-10 h-10 text-muted-foreground/40 mx-auto" />
            <h3 className="text-base font-semibold">No authorized people found</h3>
            <p className="text-xs text-muted-foreground max-w-sm mx-auto">
              There are currently no authorized entries matching your filter criteria. Use the Authorize Person button to issue credentials.
            </p>
            <Button
              size="sm"
              onClick={() => handleOpenAuthorizeModal(selectedCategory !== 'all' ? selectedCategory : 'long_term_occupants')}
              className="gap-2"
            >
              <Plus className="w-4 h-4" /> Authorize New Person
            </Button>
          </div>
        )}

        {/* ─────────────────────────────────────────────────────────────
            MODAL 1: QR Credential Viewer with Dynamic Live Slot
        ───────────────────────────────────────────────────────────── */}
        <Dialog open={isCredentialModalOpen} onOpenChange={setIsCredentialModalOpen}>
          <DialogContent className="max-w-md bg-card/95 border-border/60 backdrop-blur-md">
            <DialogHeader>
              <DialogTitle className="flex items-center justify-between text-base">
                <span className="flex items-center gap-2">
                  <ShieldCheck className="w-5 h-5 text-primary" />
                  <span>Authorized Gate Credential</span>
                </span>
                <Badge variant="outline" className="text-[10px] font-mono border-primary/30 text-primary">
                  HMAC-SHA256
                </Badge>
              </DialogTitle>
              <DialogDescription className="text-xs">
                Official encrypted gate credential for physical scanner clearance.
              </DialogDescription>
            </DialogHeader>

            {activePerson && (
              <div className="space-y-4 pt-2">
                {/* Visual Credential Ticket */}
                <div className="relative rounded-2xl border-2 border-primary/30 bg-gradient-to-b from-primary/10 via-background to-background p-5 text-center shadow-lg overflow-hidden">
                  <div className="flex items-center justify-between border-b border-border/40 pb-3 mb-4">
                    <div className="flex items-center gap-2 text-left">
                      {renderShapeIcon(activePerson.credential?.shape)}
                      <div>
                        <p className="text-[10px] font-mono uppercase text-muted-foreground tracking-wider">
                          {activePerson.credential?.categoryCode || 'GP-PASS'} • {activePerson.credential?.shape || 'Standard'}
                        </p>
                        <p className="text-xs font-bold text-foreground">{activePerson.categoryLabel}</p>
                      </div>
                    </div>
                    <Badge variant="secondary" className="text-[10px] font-mono">
                      GPE V1
                    </Badge>
                  </div>

                  {/* QR Code Presentation */}
                  <div className="flex flex-col items-center justify-center p-3 bg-white rounded-xl shadow-inner mx-auto w-48 h-48 border border-zinc-200">
                    {/* Simulated High-Res Vector QR Code */}
                    <div className="relative w-40 h-40 bg-zinc-950 rounded-lg p-2 flex flex-col justify-between items-center text-white font-mono text-[9px]">
                      <div className="flex justify-between w-full">
                        <div className="w-8 h-8 border-4 border-white rounded-sm flex items-center justify-center">
                          <div className="w-3 h-3 bg-white" />
                        </div>
                        <div className="w-8 h-8 border-4 border-white rounded-sm flex items-center justify-center">
                          <div className="w-3 h-3 bg-white" />
                        </div>
                      </div>

                      <div className="flex items-center justify-center gap-1 py-1">
                        <QrCode className="w-10 h-10 text-white animate-pulse" />
                      </div>

                      <div className="flex justify-between w-full">
                        <div className="w-8 h-8 border-4 border-white rounded-sm flex items-center justify-center">
                          <div className="w-3 h-3 bg-white" />
                        </div>
                        <div className="text-[8px] text-zinc-400 self-end">SEC-OK</div>
                      </div>
                    </div>
                  </div>

                  {/* Dynamic Slot Countdown */}
                  <div className="mt-4 flex items-center justify-center gap-2 text-xs text-muted-foreground">
                    <Clock className="w-3.5 h-3.5 text-primary animate-spin" />
                    <span>
                      Token refreshes in <strong className="text-foreground font-mono">{secondsRemaining}s</strong>
                    </span>
                  </div>

                  {/* Pass Holder Details */}
                  <div className="mt-4 pt-3 border-t border-border/40 text-left space-y-1.5 text-xs">
                    <div className="flex justify-between">
                      <span className="text-muted-foreground">Holder Name:</span>
                      <span className="font-semibold text-foreground">{activePerson.name}</span>
                    </div>
                    <div className="flex justify-between">
                      <span className="text-muted-foreground">Pass Identifier:</span>
                      <span className="font-mono text-primary font-bold">{activePerson.credential?.passId}</span>
                    </div>
                    <div className="flex justify-between">
                      <span className="text-muted-foreground">Designated Unit:</span>
                      <span className="font-medium text-foreground">{activePerson.propertyLabel}</span>
                    </div>
                    <div className="flex justify-between">
                      <span className="text-muted-foreground">Operational Hours:</span>
                      <span className="font-medium text-foreground">
                        {activePerson.accessRules?.entry_start_time || '00:00'} - {activePerson.accessRules?.entry_end_time || '23:59'}
                      </span>
                    </div>
                    {activePerson.accessRules?.requires_id_verification && (
                      <div className="mt-2 p-2 rounded bg-amber-500/10 border border-amber-500/30 text-amber-400 text-[11px] flex items-center gap-1.5">
                        <ShieldAlert className="w-3.5 h-3.5 shrink-0" />
                        <span>Security Notice: Guard must inspect physical photo ID prior to admission.</span>
                      </div>
                    )}
                  </div>
                </div>

                {/* Share Options */}
                <div className="flex items-center justify-between gap-2 pt-1">
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => {
                      navigator.clipboard.writeText(
                        `Access Credential for ${activePerson.name} (${activePerson.credential?.passId}): ${window.location.origin}/dashboard/gate-pass`
                      );
                      toast({ title: 'Copied', description: 'Credential link copied to clipboard.' });
                    }}
                    className="flex-1 text-xs gap-1.5"
                  >
                    <Copy className="w-3.5 h-3.5" />
                    <span>Copy Link</span>
                  </Button>

                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => {
                      const text = encodeURIComponent(
                        `Your gate access pass for ${activePerson.propertyLabel} is ready: ${window.location.origin}/dashboard/gate-pass`
                      );
                      window.open(`https://wa.me/?text=${text}`, '_blank');
                    }}
                    className="flex-1 text-xs gap-1.5 text-emerald-400 border-emerald-500/30"
                  >
                    <Share2 className="w-3.5 h-3.5" />
                    <span>WhatsApp</span>
                  </Button>
                </div>
              </div>
            )}
          </DialogContent>
        </Dialog>

        {/* ─────────────────────────────────────────────────────────────
            MODAL 2: Authorize Person Across All 10 Categories
        ───────────────────────────────────────────────────────────── */}
        <Dialog open={isAuthorizeModalOpen} onOpenChange={setIsAuthorizeModalOpen}>
          <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto bg-card/95 border-border/60">
            <DialogHeader>
              <DialogTitle className="flex items-center gap-2 text-lg">
                <UserCheck className="w-5 h-5 text-primary" />
                <span>Authorize Person around Property</span>
              </DialogTitle>
              <DialogDescription className="text-xs">
                Grant dedicated credentials, amenity access, and security rules for individuals around your residence.
              </DialogDescription>
            </DialogHeader>

            <form onSubmit={handleCreateAuthorization} className="space-y-4 pt-2">
              {/* Category Selector Tabs */}
              <div className="space-y-1.5">
                <Label className="text-xs font-semibold">Select Access Category</Label>
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
                  {(isShortTerm ? [
                    { key: 'visitors', label: 'Pre-Cleared Visitor', code: 'GP-VIS' },
                  ] : isRenter ? [
                    { key: 'long_term_occupants', label: 'Co-Occupant / Family', code: 'GP-LTO' },
                    { key: 'visitors', label: 'Pre-Cleared Visitor', code: 'GP-VIS' },
                    { key: 'domestic_staff', label: 'Staff Member', code: 'GP-HST' },
                    { key: 'contractors', label: 'Contractor', code: 'GP-CON' },
                    { key: 'legacy_contacts', label: 'Emergency Contact', code: 'GP-DEL' },
                  ] : [
                    { key: 'long_term_occupants', label: 'Long-term occupant', code: 'GP-LTO' },
                    { key: 'legacy_contacts', label: 'Legacy contact', code: 'GP-DEL' },
                    { key: 'family_members', label: 'Family member', code: 'GP-DEL' },
                    { key: 'caregivers', label: 'Caregiver', code: 'GP-DEL' },
                    { key: 'property_managers', label: 'Property manager', code: 'GP-DEL' },
                    { key: 'domestic_staff', label: 'Domestic staff', code: 'GP-HST' },
                    { key: 'contractors', label: 'Contractor', code: 'GP-CON' },
                  ]).map((cat) => (
                    <button
                      key={cat.key}
                      type="button"
                      onClick={() => applyCategoryDefaults(cat.key)}
                      className={`p-2.5 rounded-lg border text-left transition-all ${
                        formData.categoryKey === cat.key
                          ? 'border-primary bg-primary/10 text-foreground ring-1 ring-primary'
                          : 'border-border/40 hover:border-border text-muted-foreground bg-muted/20'
                      }`}
                    >
                      <div className="text-[10px] font-mono font-bold text-primary">{cat.code}</div>
                      <div className="text-xs font-semibold mt-0.5">{cat.label}</div>
                    </button>
                  ))}
                </div>
              </div>

              {/* Recurring Provider Quick Presets (Mon-Fri 8am-5pm / Valid through Dec 31) */}
              {!isShortTerm && (
                <div className="space-y-1.5 p-3 rounded-xl border border-primary/20 bg-gradient-to-r from-primary/5 via-background to-muted/20">
                  <div className="flex items-center justify-between">
                    <Label className="text-xs font-semibold flex items-center gap-1.5 text-foreground">
                      <RefreshCw className="w-3.5 h-3.5 text-primary" />
                      <span>Recurring Provider Presets</span>
                    </Label>
                    <span className="text-[10px] text-muted-foreground">
                      Mon–Fri (8am–5pm) • Valid until Dec 31 • Reusable QR
                    </span>
                  </div>

                  <div className="grid grid-cols-2 sm:grid-cols-3 gap-2 pt-1">
                    {RECURRING_WORKER_TEMPLATES.map((tmpl) => {
                      const Icon = tmpl.icon;
                      const isCurrent = formData.relationship === tmpl.relationship;
                      return (
                        <button
                          key={tmpl.name}
                          type="button"
                          onClick={() => handleApplyRecurringWorkerTemplate(tmpl)}
                          className={`flex items-start gap-2 p-2 rounded-lg border text-left transition-all text-xs ${
                            isCurrent
                              ? 'border-primary bg-primary/10 text-foreground ring-1 ring-primary shadow-sm'
                              : 'border-border/40 hover:border-border text-muted-foreground bg-card/60'
                          }`}
                        >
                          <div className="p-1 rounded bg-muted text-primary mt-0.5 shrink-0">
                            <Icon className="w-3.5 h-3.5" />
                          </div>
                          <div className="min-w-0 flex-1">
                            <div className="font-semibold text-foreground truncate text-[11px]">{tmpl.name}</div>
                            <div className="text-[9px] text-muted-foreground font-mono">{tmpl.badge}</div>
                          </div>
                        </button>
                      );
                    })}
                  </div>
                </div>
              )}

              {/* Identity Information */}
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2">
                <div className="space-y-1">
                  <Label htmlFor="name" className="text-xs font-medium">Full Name *</Label>
                  <Input
                    id="name"
                    required
                    value={formData.name}
                    onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                    placeholder="e.g. Michael Brown"
                    className="h-9 text-xs"
                  />
                </div>

                <div className="space-y-1">
                  <Label htmlFor="email" className="text-xs font-medium">Email Address *</Label>
                  <Input
                    id="email"
                    type="email"
                    required
                    value={formData.email}
                    onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                    placeholder="e.g. michael@example.com"
                    className="h-9 text-xs"
                  />
                </div>

                <div className="space-y-1">
                  <Label htmlFor="phone" className="text-xs font-medium">Phone Number</Label>
                  <Input
                    id="phone"
                    value={formData.phone}
                    onChange={(e) => setFormData({ ...formData, phone: e.target.value })}
                    placeholder="+1 (876) 555-0144"
                    className="h-9 text-xs"
                  />
                </div>

                <div className="space-y-1">
                  <Label htmlFor="relationship" className="text-xs font-medium">Relationship *</Label>
                  <Input
                    id="relationship"
                    required
                    value={formData.relationship}
                    onChange={(e) => setFormData({ ...formData, relationship: e.target.value })}
                    placeholder="e.g. Adult Son, Nurse, Gardener"
                    className="h-9 text-xs"
                  />
                </div>
              </div>

              {/* Access Duration Presets */}
              <div className="space-y-2 pt-1">
                <div className="flex items-center justify-between">
                  <Label className="text-xs font-semibold flex items-center gap-1.5">
                    <Clock className="w-3.5 h-3.5 text-primary" />
                    <span>Access Duration Preset</span>
                  </Label>
                  <span className="text-[10px] text-muted-foreground">
                    Enforces automatic gate pass expiration
                  </span>
                </div>

                <div className="grid grid-cols-2 sm:grid-cols-3 gap-2">
                  {DURATION_PRESETS.filter((preset) => {
                    if (isShortTerm) {
                      return !['manual_revocation', 'recurring'].includes(preset.key);
                    }
                    if (isRenter) {
                      return preset.key !== 'manual_revocation';
                    }
                    return true;
                  }).map((preset) => {
                    const isSelected = formData.duration_type === preset.key;
                    const Icon = preset.icon;
                    return (
                      <button
                        key={preset.key}
                        type="button"
                        onClick={() => handleSelectDurationPreset(preset.key)}
                        className={`flex flex-col text-left p-2.5 rounded-lg border transition-all text-xs ${
                          isSelected
                            ? 'border-primary bg-primary/10 text-foreground ring-1 ring-primary shadow-sm'
                            : 'border-border/40 hover:border-border text-muted-foreground bg-muted/20'
                        }`}
                      >
                        <div className="flex items-center justify-between w-full mb-1">
                          <span className="font-semibold text-foreground flex items-center gap-1.5">
                            <Icon className={`w-3.5 h-3.5 ${isSelected ? 'text-primary' : 'text-muted-foreground'}`} />
                            {preset.label}
                          </span>
                          {isSelected && <Check className="w-3.5 h-3.5 text-primary" />}
                        </div>
                        <span className="text-[10px] text-muted-foreground leading-snug">
                          {preset.description}
                        </span>
                      </button>
                    );
                  })}
                </div>

                {/* Duration Guidance Banner */}
                <div className="p-2.5 rounded-lg border border-primary/20 bg-primary/5 text-[11px] flex items-start gap-2 text-muted-foreground">
                  <Info className="w-3.5 h-3.5 text-primary shrink-0 mt-0.5" />
                  <span>
                    {formData.duration_type === '2_hours' && (
                      <span><strong className="text-foreground">2-Hour Rapid Pass:</strong> Pass valid for 2 hours from issue time. Ideal for cleaners, deliveries, and quick repairs.</span>
                    )}
                    {formData.duration_type === '1_day' && (
                      <span><strong className="text-foreground">1-Day Day Pass:</strong> Pass valid for today until 23:59:59. Ideal for day cleaners, babysitters, and party guests.</span>
                    )}
                    {formData.duration_type === '1_week' && (
                      <span><strong className="text-foreground">1-Week Short Term:</strong> Pass valid for 7 consecutive days through midnight. Ideal for visiting relatives, painting crews, or contractors.</span>
                    )}
                    {formData.duration_type === 'recurring' && (
                      <span><strong className="text-foreground">Recurring Service Provider Shift:</strong> Instead of repeatedly creating QR codes, the person receives a single persistent credential valid until <strong>December 31</strong>. Gate entry is strictly enforced on permitted days within shift hours.</span>
                    )}
                    {formData.duration_type === 'custom' && (
                      <span><strong className="text-foreground">Custom Date Range:</strong> Access active between the selected start and expiration dates.</span>
                    )}
                    {formData.duration_type === 'manual_revocation' && (
                      <span><strong className="text-foreground">Ongoing Until Revoked:</strong> Credential remains valid indefinitely without auto-expiration until you manually click Revoke.</span>
                    )}
                  </span>
                </div>

                {/* 1-Click Recurring Worker Templates Grid */}
                {formData.duration_type === 'recurring' && (
                  <div className="p-3 rounded-lg border border-emerald-500/30 bg-emerald-500/5 space-y-2.5">
                    <div className="flex items-center justify-between">
                      <div className="flex items-center gap-1.5 text-xs font-semibold text-emerald-400">
                        <Sparkles className="w-3.5 h-3.5" />
                        <span>Recurring Service Provider Quick Presets</span>
                      </div>
                      <span className="text-[10px] text-muted-foreground">Persistent QR valid until Dec 31</span>
                    </div>
                    <div className="grid grid-cols-2 sm:grid-cols-3 gap-2">
                      {RECURRING_WORKER_TEMPLATES.map((tmpl) => {
                        const Icon = tmpl.icon;
                        const isMatched = formData.relationship === tmpl.relationship;
                        return (
                          <button
                            key={tmpl.name}
                            type="button"
                            onClick={() => handleApplyRecurringWorkerTemplate(tmpl)}
                            className={`p-2 rounded-lg border text-left transition-all flex flex-col justify-between ${
                              isMatched
                                ? 'border-emerald-500 bg-emerald-500/15 text-foreground ring-1 ring-emerald-500'
                                : 'border-border/40 hover:border-emerald-500/40 bg-card/60 text-muted-foreground'
                            }`}
                          >
                            <div>
                              <div className="flex items-center justify-between mb-1">
                                <span className="text-xs font-semibold text-foreground flex items-center gap-1.5 truncate">
                                  <Icon className="w-3.5 h-3.5 text-emerald-400 shrink-0" />
                                  <span className="truncate">{tmpl.name}</span>
                                </span>
                                {isMatched && <Check className="w-3 h-3 text-emerald-400 shrink-0" />}
                              </div>
                              <p className="text-[10px] text-muted-foreground line-clamp-1">{tmpl.desc}</p>
                            </div>
                            <div className="mt-1.5 inline-flex items-center text-[9px] font-medium text-emerald-400 bg-emerald-500/10 px-1.5 py-0.5 rounded border border-emerald-500/20 w-fit">
                              {tmpl.badge}
                            </div>
                          </button>
                        );
                      })}
                    </div>
                  </div>
                )}
              </div>

              {/* Timeframe & Property */}
              <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-1">
                <div className="space-y-1">
                  <Label className="text-xs font-medium">Property</Label>
                  <Select
                    value={formData.property_id}
                    onValueChange={(val) => setFormData({ ...formData, property_id: val })}
                  >
                    <SelectTrigger className="h-9 text-xs">
                      <SelectValue placeholder="Select Property" />
                    </SelectTrigger>
                    <SelectContent>
                      {properties.map((p) => (
                        <SelectItem key={p.id} value={String(p.id)}>
                          Lot {p.lot_number} ({p.street_address})
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>

                <div className="space-y-1">
                  <Label htmlFor="starts_at" className="text-xs font-medium">Effective Start Date</Label>
                  <Input
                    id="starts_at"
                    type="date"
                    value={formData.starts_at}
                    onChange={(e) => setFormData({ ...formData, starts_at: e.target.value })}
                    className="h-9 text-xs"
                  />
                </div>

                <div className="space-y-1">
                  <div className="flex items-center justify-between">
                    <Label htmlFor="expires_at" className="text-xs font-medium">Expiration Date</Label>
                    {formData.duration_type !== 'manual_revocation' && (
                      <button
                        type="button"
                        onClick={() => setEndOfYearExpiration('form')}
                        className="text-[10px] text-emerald-400 hover:text-emerald-300 font-medium flex items-center gap-0.5 hover:underline"
                        title="Set expiration to December 31"
                      >
                        <Calendar className="w-2.5 h-2.5" />
                        Set Dec 31
                      </button>
                    )}
                  </div>
                  {formData.duration_type === 'manual_revocation' ? (
                    <div className="h-9 flex items-center px-3 rounded-md border border-border/40 bg-muted/40 text-xs text-muted-foreground">
                      <Lock className="w-3 h-3 mr-1.5 text-teal-400" />
                      Until Manually Revoked
                    </div>
                  ) : formData.duration_type === '2_hours' ? (
                    <div className="h-9 flex items-center px-3 rounded-md border border-border/40 bg-muted/40 text-xs text-muted-foreground">
                      <Clock className="w-3 h-3 mr-1.5 text-amber-500" />
                      +2 Hours from issuance
                    </div>
                  ) : (
                    <Input
                      id="expires_at"
                      type="date"
                      value={formData.expires_at}
                      max={isShortTerm && permissions?.checkoutDate ? permissions.checkoutDate : (isRenter && permissions?.leaseEndDate ? permissions.leaseEndDate : undefined)}
                      onChange={(e) => setFormData({ ...formData, expires_at: e.target.value })}
                      className="h-9 text-xs"
                    />
                  )}
                </div>
              </div>

              {/* Operational Schedule & Access Hours */}
              <div className="p-3 rounded-lg border border-border/40 bg-muted/20 space-y-2.5">
                <div className="flex items-center justify-between">
                  <Label className="text-xs font-semibold">Operational Schedule Rules</Label>
                  <span className="text-[10px] text-muted-foreground font-normal">Enforced at Gate Scanner</span>
                </div>

                <div className="flex items-center justify-between gap-1 flex-wrap pb-1.5 border-b border-border/20">
                  <span className="text-[11px] text-muted-foreground font-medium">Quick Shifts:</span>
                  <div className="flex flex-wrap gap-1">
                    <button
                      type="button"
                      onClick={() => applyQuickSchedule('mon_fri', 'form')}
                      className="text-[10px] px-2 py-0.5 rounded bg-muted hover:bg-muted/80 text-foreground border border-border/50 transition-colors"
                    >
                      Mon–Fri (8am–5pm)
                    </button>
                    <button
                      type="button"
                      onClick={() => applyQuickSchedule('mon_sat', 'form')}
                      className="text-[10px] px-2 py-0.5 rounded bg-muted hover:bg-muted/80 text-foreground border border-border/50 transition-colors"
                    >
                      Mon–Sat (6am–8pm)
                    </button>
                    <button
                      type="button"
                      onClick={() => applyQuickSchedule('tue_thu', 'form')}
                      className="text-[10px] px-2 py-0.5 rounded bg-muted hover:bg-muted/80 text-foreground border border-border/50 transition-colors"
                    >
                      Tue & Thu
                    </button>
                    <button
                      type="button"
                      onClick={() => applyQuickSchedule('weekends', 'form')}
                      className="text-[10px] px-2 py-0.5 rounded bg-muted hover:bg-muted/80 text-foreground border border-border/50 transition-colors"
                    >
                      Weekends
                    </button>
                    <button
                      type="button"
                      onClick={() => applyQuickSchedule('everyday', 'form')}
                      className="text-[10px] px-2 py-0.5 rounded bg-muted hover:bg-muted/80 text-foreground border border-border/50 transition-colors"
                    >
                      24/7 Everyday
                    </button>
                  </div>
                </div>

                <div className="flex flex-wrap gap-2">
                  {['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'].map((day) => {
                    const isChecked = formData.access_rules.allowed_days.includes(day);
                    return (
                      <label
                        key={day}
                        className={`flex items-center gap-1.5 px-2.5 py-1 rounded text-xs border cursor-pointer ${
                          isChecked
                            ? 'bg-primary/10 border-primary text-foreground font-medium'
                            : 'bg-background border-border text-muted-foreground'
                        }`}
                      >
                        <input
                          type="checkbox"
                          className="hidden"
                          checked={isChecked}
                          onChange={(e) => {
                            const days = e.target.checked
                              ? [...formData.access_rules.allowed_days, day]
                              : formData.access_rules.allowed_days.filter((d) => d !== day);
                            setFormData({
                              ...formData,
                              access_rules: { ...formData.access_rules, allowed_days: days },
                            });
                          }}
                        />
                        <span>{day}</span>
                      </label>
                    );
                  })}
                </div>

                <div className="grid grid-cols-2 gap-3 pt-1">
                  <div className="space-y-1">
                    <Label className="text-[11px] text-muted-foreground">Entry Start Time</Label>
                    <Input
                      type="time"
                      value={formData.access_rules.entry_start_time}
                      onChange={(e) =>
                        setFormData({
                          ...formData,
                          access_rules: { ...formData.access_rules, entry_start_time: e.target.value },
                        })
                      }
                      className="h-8 text-xs"
                    />
                  </div>
                  <div className="space-y-1">
                    <Label className="text-[11px] text-muted-foreground">Entry End Time</Label>
                    <Input
                      type="time"
                      value={formData.access_rules.entry_end_time}
                      onChange={(e) =>
                        setFormData({
                          ...formData,
                          access_rules: { ...formData.access_rules, entry_end_time: e.target.value },
                        })
                      }
                      className="h-8 text-xs"
                    />
                  </div>
                </div>

                <div className="pt-2">
                  <label className="flex items-center gap-2 cursor-pointer">
                    <Checkbox
                      checked={formData.access_rules.requires_id_verification}
                      onCheckedChange={(checked) =>
                        setFormData({
                          ...formData,
                          access_rules: {
                            ...formData.access_rules,
                            requires_id_verification: !!checked,
                          },
                        })
                      }
                    />
                    <span className="text-xs text-foreground font-medium">
                      Require security guard to inspect physical photo ID on scanner check-in
                    </span>
                  </label>
                </div>
              </div>

              {/* Amenity & Privilege Checkboxes */}
              <div className="space-y-1.5 pt-1">
                <Label className="text-xs font-semibold">Amenity & Property Privileges</Label>
                <div className="grid grid-cols-2 sm:grid-cols-3 gap-2">
                  <label className="flex items-center gap-2 p-2 rounded border border-border/30 bg-muted/10 text-xs cursor-pointer">
                    <Checkbox
                      checked={formData.access_rules.pool_access}
                      onCheckedChange={(checked) =>
                        setFormData({
                          ...formData,
                          access_rules: { ...formData.access_rules, pool_access: !!checked },
                        })
                      }
                    />
                    <span>Swimming Pool</span>
                  </label>
                  <label className="flex items-center gap-2 p-2 rounded border border-border/30 bg-muted/10 text-xs cursor-pointer">
                    <Checkbox
                      checked={formData.access_rules.gym_access}
                      onCheckedChange={(checked) =>
                        setFormData({
                          ...formData,
                          access_rules: { ...formData.access_rules, gym_access: !!checked },
                        })
                      }
                    />
                    <span>Fitness Gym</span>
                  </label>
                  <label className="flex items-center gap-2 p-2 rounded border border-border/30 bg-muted/10 text-xs cursor-pointer">
                    <Checkbox
                      checked={formData.access_rules.clubhouse_access}
                      onCheckedChange={(checked) =>
                        setFormData({
                          ...formData,
                          access_rules: { ...formData.access_rules, clubhouse_access: !!checked },
                        })
                      }
                    />
                    <span>Clubhouse</span>
                  </label>
                  <label className="flex items-center gap-2 p-2 rounded border border-border/30 bg-muted/10 text-xs cursor-pointer">
                    <Checkbox
                      checked={formData.access_rules.parking_allocated}
                      onCheckedChange={(checked) =>
                        setFormData({
                          ...formData,
                          access_rules: { ...formData.access_rules, parking_allocated: !!checked },
                        })
                      }
                    />
                    <span>Allocated Parking</span>
                  </label>
                  <label className="flex items-center gap-2 p-2 rounded border border-border/30 bg-muted/10 text-xs cursor-pointer">
                    <Checkbox
                      checked={formData.access_rules.can_invite_visitors}
                      onCheckedChange={(checked) =>
                        setFormData({
                          ...formData,
                          access_rules: { ...formData.access_rules, can_invite_visitors: !!checked },
                        })
                      }
                    />
                    <span>Can Pre-Clear Guests</span>
                  </label>
                </div>
              </div>

              {/* Security Notes */}
              <div className="space-y-1 pt-1">
                <Label htmlFor="notes" className="text-xs font-medium">Security & Gate Instructions</Label>
                <Textarea
                  id="notes"
                  rows={2}
                  value={formData.security_notes}
                  onChange={(e) => setFormData({ ...formData, security_notes: e.target.value })}
                  placeholder="Optional guidance for estate guard and security post..."
                  className="text-xs"
                />
              </div>

              <DialogFooter className="pt-2">
                <Button type="button" variant="outline" size="sm" onClick={() => setIsAuthorizeModalOpen(false)}>
                  Cancel
                </Button>
                <Button type="submit" size="sm" className="gap-2">
                  <ShieldCheck className="w-4 h-4" />
                  <span>Issue Authorization & QR</span>
                </Button>
              </DialogFooter>
            </form>
          </DialogContent>
        </Dialog>

        {/* ─────────────────────────────────────────────────────────────
            MODAL 3: Edit Permissions & Rules Modal
        ───────────────────────────────────────────────────────────── */}
        <Dialog open={isEditRulesModalOpen} onOpenChange={setIsEditRulesModalOpen}>
          <DialogContent className="max-w-lg bg-card/95 border-border/60">
            <DialogHeader>
              <DialogTitle className="flex items-center gap-2 text-base">
                <SlidersHorizontal className="w-5 h-5 text-primary" />
                <span>Edit Access Rules: {activePerson?.name}</span>
              </DialogTitle>
              <DialogDescription className="text-xs">
                Update allowed entry days, operational hours, and security parameters.
              </DialogDescription>
            </DialogHeader>

            <form onSubmit={handleSaveRules} className="space-y-4 pt-2">
              {/* Duration Presets Selector */}
              <div className="space-y-2">
                <div className="flex items-center justify-between">
                  <Label className="text-xs font-semibold flex items-center gap-1.5">
                    <Clock className="w-3.5 h-3.5 text-primary" />
                    <span>Duration Preset</span>
                  </Label>
                  <span className="text-[10px] text-muted-foreground">
                    Modifies gate validity period
                  </span>
                </div>

                <div className="grid grid-cols-2 sm:grid-cols-3 gap-1.5">
                  {DURATION_PRESETS.filter((preset) => !isRenter || preset.key !== 'manual_revocation').map((preset) => {
                    const isSelected = editRulesData.duration_type === preset.key;
                    const Icon = preset.icon;
                    return (
                      <button
                        key={preset.key}
                        type="button"
                        onClick={() => handleSelectEditRulesDurationPreset(preset.key)}
                        className={`flex items-center justify-between p-2 rounded-lg border transition-all text-xs ${
                          isSelected
                            ? 'border-primary bg-primary/10 text-foreground ring-1 ring-primary font-semibold'
                            : 'border-border/40 hover:border-border text-muted-foreground bg-muted/20'
                        }`}
                      >
                        <span className="flex items-center gap-1.5 truncate">
                          <Icon className={`w-3.5 h-3.5 ${isSelected ? 'text-primary' : 'text-muted-foreground'}`} />
                          <span className="truncate">{preset.shortLabel}</span>
                        </span>
                        {isSelected && <Check className="w-3 h-3 text-primary shrink-0" />}
                      </button>
                    );
                  })}
                </div>

                {/* Duration Expiration Inputs */}
                <div className="grid grid-cols-2 gap-3 pt-1">
                  <div className="space-y-1">
                    <Label className="text-[11px] text-muted-foreground">Start Date</Label>
                    <Input
                      type="date"
                      value={editRulesData.starts_at}
                      onChange={(e) => setEditRulesData({ ...editRulesData, starts_at: e.target.value })}
                      className="h-8 text-xs"
                    />
                  </div>
                  <div className="space-y-1">
                    <div className="flex items-center justify-between">
                      <Label className="text-[11px] text-muted-foreground">Expiration Date</Label>
                      {editRulesData.duration_type !== 'manual_revocation' && (
                        <button
                          type="button"
                          onClick={() => setEndOfYearExpiration('edit')}
                          className="text-[10px] text-emerald-400 hover:text-emerald-300 font-medium flex items-center gap-0.5 hover:underline"
                          title="Set expiration to December 31"
                        >
                          <Calendar className="w-2.5 h-2.5" />
                          Set Dec 31
                        </button>
                      )}
                    </div>
                    {editRulesData.duration_type === 'manual_revocation' ? (
                      <div className="h-8 flex items-center px-2.5 rounded-md border border-border/40 bg-muted/40 text-[11px] text-muted-foreground">
                        <Lock className="w-3 h-3 mr-1 text-teal-400" />
                        Until Revoked
                      </div>
                    ) : editRulesData.duration_type === '2_hours' ? (
                      <div className="h-8 flex items-center px-2.5 rounded-md border border-border/40 bg-muted/40 text-[11px] text-muted-foreground">
                        <Clock className="w-3 h-3 mr-1 text-amber-500" />
                        +2 Hours
                      </div>
                    ) : (
                      <Input
                        type="date"
                        value={editRulesData.expires_at}
                        max={isRenter && permissions?.leaseEndDate ? permissions.leaseEndDate : undefined}
                        onChange={(e) => setEditRulesData({ ...editRulesData, expires_at: e.target.value })}
                        className="h-8 text-xs"
                      />
                    )}
                  </div>
                </div>
              </div>

              <div className="space-y-2">
                <div className="flex items-center justify-between">
                  <Label className="text-xs font-semibold">Allowed Entry Days</Label>
                  <div className="flex flex-wrap gap-1">
                    <button
                      type="button"
                      onClick={() => applyQuickSchedule('mon_fri', 'edit')}
                      className="text-[9px] px-1.5 py-0.5 rounded bg-muted hover:bg-muted/80 text-foreground border border-border/50 transition-colors"
                    >
                      Mon–Fri
                    </button>
                    <button
                      type="button"
                      onClick={() => applyQuickSchedule('mon_sat', 'edit')}
                      className="text-[9px] px-1.5 py-0.5 rounded bg-muted hover:bg-muted/80 text-foreground border border-border/50 transition-colors"
                    >
                      Mon–Sat
                    </button>
                    <button
                      type="button"
                      onClick={() => applyQuickSchedule('tue_thu', 'edit')}
                      className="text-[9px] px-1.5 py-0.5 rounded bg-muted hover:bg-muted/80 text-foreground border border-border/50 transition-colors"
                    >
                      Tue/Thu
                    </button>
                    <button
                      type="button"
                      onClick={() => applyQuickSchedule('weekends', 'edit')}
                      className="text-[9px] px-1.5 py-0.5 rounded bg-muted hover:bg-muted/80 text-foreground border border-border/50 transition-colors"
                    >
                      Weekends
                    </button>
                    <button
                      type="button"
                      onClick={() => applyQuickSchedule('everyday', 'edit')}
                      className="text-[9px] px-1.5 py-0.5 rounded bg-muted hover:bg-muted/80 text-foreground border border-border/50 transition-colors"
                    >
                      All Days
                    </button>
                  </div>
                </div>
                <div className="flex flex-wrap gap-1.5">
                  {['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'].map((day) => {
                    const isChecked = editRulesData.access_rules.allowed_days.includes(day);
                    return (
                      <label
                        key={day}
                        className={`flex items-center gap-1 px-2.5 py-1 rounded text-xs border cursor-pointer ${
                          isChecked
                            ? 'bg-primary/10 border-primary text-foreground font-medium'
                            : 'bg-background border-border text-muted-foreground'
                        }`}
                      >
                        <input
                          type="checkbox"
                          className="hidden"
                          checked={isChecked}
                          onChange={(e) => {
                            const days = e.target.checked
                              ? [...editRulesData.access_rules.allowed_days, day]
                              : editRulesData.access_rules.allowed_days.filter((d) => d !== day);
                            setEditRulesData({
                              ...editRulesData,
                              access_rules: { ...editRulesData.access_rules, allowed_days: days },
                            });
                          }}
                        />
                        <span>{day}</span>
                      </label>
                    );
                  })}
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div className="space-y-1">
                  <Label className="text-xs">Start Time</Label>
                  <Input
                    type="time"
                    value={editRulesData.access_rules.entry_start_time}
                    onChange={(e) =>
                      setEditRulesData({
                        ...editRulesData,
                        access_rules: { ...editRulesData.access_rules, entry_start_time: e.target.value },
                      })
                    }
                    className="h-8 text-xs"
                  />
                </div>
                <div className="space-y-1">
                  <Label className="text-xs">End Time</Label>
                  <Input
                    type="time"
                    value={editRulesData.access_rules.entry_end_time}
                    onChange={(e) =>
                      setEditRulesData({
                        ...editRulesData,
                        access_rules: { ...editRulesData.access_rules, entry_end_time: e.target.value },
                      })
                    }
                    className="h-8 text-xs"
                  />
                </div>
              </div>

              <div className="space-y-2 pt-2">
                <Label className="text-xs font-semibold">Security & Amenity Permissions</Label>
                <div className="space-y-2">
                  <label className="flex items-center gap-2 cursor-pointer text-xs">
                    <Checkbox
                      checked={editRulesData.access_rules.gate_access}
                      onCheckedChange={(checked) =>
                        setEditRulesData({
                          ...editRulesData,
                          access_rules: { ...editRulesData.access_rules, gate_access: !!checked },
                        })
                      }
                    />
                    <span>Digital Gate Scanner Access</span>
                  </label>
                  <label className="flex items-center gap-2 cursor-pointer text-xs">
                    <Checkbox
                      checked={editRulesData.access_rules.requires_id_verification}
                      onCheckedChange={(checked) =>
                        setEditRulesData({
                          ...editRulesData,
                          access_rules: { ...editRulesData.access_rules, requires_id_verification: !!checked },
                        })
                      }
                    />
                    <span>Mandatory Physical Photo ID Check on scan</span>
                  </label>
                  <label className="flex items-center gap-2 cursor-pointer text-xs">
                    <Checkbox
                      checked={editRulesData.access_rules.pool_access}
                      onCheckedChange={(checked) =>
                        setEditRulesData({
                          ...editRulesData,
                          access_rules: { ...editRulesData.access_rules, pool_access: !!checked },
                        })
                      }
                    />
                    <span>Pool & Wet Areas Access</span>
                  </label>
                  <label className="flex items-center gap-2 cursor-pointer text-xs">
                    <Checkbox
                      checked={editRulesData.access_rules.gym_access}
                      onCheckedChange={(checked) =>
                        setEditRulesData({
                          ...editRulesData,
                          access_rules: { ...editRulesData.access_rules, gym_access: !!checked },
                        })
                      }
                    />
                    <span>Fitness Center Access</span>
                  </label>
                  <label className="flex items-center gap-2 cursor-pointer text-xs">
                    <Checkbox
                      checked={editRulesData.access_rules.parking_allocated}
                      onCheckedChange={(checked) =>
                        setEditRulesData({
                          ...editRulesData,
                          access_rules: { ...editRulesData.access_rules, parking_allocated: !!checked },
                        })
                      }
                    />
                    <span>Resident / Visitor Allocated Parking</span>
                  </label>
                </div>
              </div>

              <DialogFooter className="pt-2">
                <Button type="button" variant="outline" size="sm" onClick={() => setIsEditRulesModalOpen(false)}>
                  Cancel
                </Button>
                <Button type="submit" size="sm">
                  Save Changes
                </Button>
              </DialogFooter>
            </form>
          </DialogContent>
        </Dialog>

        {/* ─────────────────────────────────────────────────────────────
            MODAL 4: Emergency Trigger Modal
        ───────────────────────────────────────────────────────────── */}
        <Dialog open={isEmergencyModalOpen} onOpenChange={setIsEmergencyModalOpen}>
          <DialogContent className="max-w-md bg-card/95 border-red-500/40">
            <DialogHeader>
              <DialogTitle className="flex items-center gap-2 text-base text-red-400">
                <AlertTriangle className="w-5 h-5 text-red-500" />
                <span>Declare Emergency Access: {activePerson?.name}</span>
              </DialogTitle>
              <DialogDescription className="text-xs">
                Temporarily unlocks elevated property privileges and sends real-time emergency broadcast to community security.
              </DialogDescription>
            </DialogHeader>

            <form onSubmit={handleActivateEmergency} className="space-y-3 pt-2">
              <div className="space-y-1">
                <Label className="text-xs">Emergency Reason *</Label>
                <Input
                  required
                  value={emergencyReason}
                  onChange={(e) => setEmergencyReason(e.target.value)}
                  placeholder="e.g. Medical emergency requiring caregiver access"
                  className="h-8 text-xs"
                />
              </div>

              <div className="space-y-1">
                <Label className="text-xs">Override Duration (Days)</Label>
                <Select
                  value={String(emergencyDurationDays)}
                  onValueChange={(val) => setEmergencyDurationDays(Number(val))}
                >
                  <SelectTrigger className="h-8 text-xs">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="3">3 Days</SelectItem>
                    <SelectItem value="7">7 Days (Standard)</SelectItem>
                    <SelectItem value="14">14 Days</SelectItem>
                    <SelectItem value="30">30 Days</SelectItem>
                  </SelectContent>
                </Select>
              </div>

              <div className="space-y-1">
                <Label className="text-xs">Notes for Estate Security</Label>
                <Textarea
                  rows={2}
                  value={emergencyNotes}
                  onChange={(e) => setEmergencyNotes(e.target.value)}
                  placeholder="Brief details to display on guard gate scanner..."
                  className="text-xs"
                />
              </div>

              <DialogFooter className="pt-2">
                <Button type="button" variant="outline" size="sm" onClick={() => setIsEmergencyModalOpen(false)}>
                  Cancel
                </Button>
                <Button type="submit" size="sm" className="bg-red-600 hover:bg-red-700 text-white">
                  Declare Emergency
                </Button>
              </DialogFooter>
            </form>
          </DialogContent>
        </Dialog>

        {/* ─────────────────────────────────────────────────────────────
            MODAL 5: Revoke Access Modal
        ───────────────────────────────────────────────────────────── */}
        <Dialog open={isRevokeModalOpen} onOpenChange={setIsRevokeModalOpen}>
          <DialogContent className="max-w-md bg-card/95 border-red-500/40">
            <DialogHeader>
              <DialogTitle className="flex items-center gap-2 text-base text-red-400">
                <Ban className="w-5 h-5 text-red-500" />
                <span>Revoke Access for {activePerson?.name}</span>
              </DialogTitle>
              <DialogDescription className="text-xs">
                Immediately revokes all QR credentials, blocks scanner admission, and archives record under Former Occupants.
              </DialogDescription>
            </DialogHeader>

            <form onSubmit={handleRevoke} className="space-y-3 pt-2">
              <div className="space-y-1">
                <Label className="text-xs">Revocation Reason</Label>
                <Input
                  value={revokeReason}
                  onChange={(e) => setRevokeReason(e.target.value)}
                  placeholder="e.g. Tenancy ended, Service completed, Permission removed"
                  className="h-8 text-xs"
                />
              </div>

              <DialogFooter className="pt-2">
                <Button type="button" variant="outline" size="sm" onClick={() => setIsRevokeModalOpen(false)}>
                  Keep Access
                </Button>
                <Button type="submit" size="sm" className="bg-red-600 hover:bg-red-700 text-white">
                  Confirm Revocation
                </Button>
              </DialogFooter>
            </form>
          </DialogContent>
        </Dialog>

        {/* ─────────────────────────────────────────────────────────────
            MODAL 6: Audit Trail Modal
        ───────────────────────────────────────────────────────────── */}
        <Dialog open={isAuditModalOpen} onOpenChange={setIsAuditModalOpen}>
          <DialogContent className="max-w-lg bg-card/95 border-border/60">
            <DialogHeader>
              <DialogTitle className="flex items-center gap-2 text-base">
                <History className="w-5 h-5 text-primary" />
                <span>Audit Trail: {activePerson?.name}</span>
              </DialogTitle>
              <DialogDescription className="text-xs">
                Complete tamper-proof timeline of access authorization, scanner events, and modifications.
              </DialogDescription>
            </DialogHeader>

            <div className="max-h-[60vh] overflow-y-auto space-y-3 pt-2 pr-1">
              {loadingAudit ? (
                <div className="text-center py-8 text-xs text-muted-foreground flex items-center justify-center gap-2">
                  <RefreshCw className="w-4 h-4 animate-spin text-primary" />
                  <span>Loading audit log...</span>
                </div>
              ) : auditEvents.length === 0 ? (
                <p className="text-xs text-center py-6 text-muted-foreground">
                  No recorded events yet for this authorization.
                </p>
              ) : (
                auditEvents.map((evt) => (
                  <div key={evt.id} className="p-3 rounded-lg border border-border/30 bg-muted/20 space-y-1 text-xs">
                    <div className="flex items-center justify-between">
                      <span className="font-semibold text-foreground uppercase text-[10px] tracking-wider text-primary">
                        {evt.event_type.replace('_', ' ')}
                      </span>
                      <span className="text-[10px] text-muted-foreground">
                        {new Date(evt.occurred_at).toLocaleString()}
                      </span>
                    </div>
                    <p className="text-muted-foreground">{evt.description}</p>
                    {evt.actor && (
                      <p className="text-[10px] text-muted-foreground/70">
                        Actor: {evt.actor.name} ({evt.actor.email})
                      </p>
                    )}
                  </div>
                ))
              )}
            </div>
          </DialogContent>
        </Dialog>

        {/* ─────────────────────────────────────────────────────────────
            MODAL 7: Emergency & Incapacity Continuity Plan
        ───────────────────────────────────────────────────────────── */}
        <Dialog open={isContinuityModalOpen} onOpenChange={setIsContinuityModalOpen}>
          <DialogContent className="max-w-xl max-h-[90vh] overflow-y-auto bg-card/95 border-border/60">
            <DialogHeader>
              <DialogTitle className="flex items-center gap-2 text-base">
                <ShieldAlert className="w-5 h-5 text-amber-500" />
                <span>Emergency & Incapacity Continuity Plan</span>
              </DialogTitle>
              <DialogDescription className="text-xs">
                Configure emergency succession triggers and delegate authorities if you become incapacitated or unreachable.
              </DialogDescription>
            </DialogHeader>

            <form onSubmit={handleSaveContinuityPlan} className="space-y-4 pt-2">
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div className="space-y-1">
                  <Label className="text-xs font-medium">Primary Succession Delegate</Label>
                  <Select
                    value={planForm.primary_delegate_id}
                    onValueChange={(val) => setPlanForm({ ...planForm, primary_delegate_id: val })}
                  >
                    <SelectTrigger className="h-9 text-xs">
                      <SelectValue placeholder="Select Delegate" />
                    </SelectTrigger>
                    <SelectContent>
                      {authorizedPeople
                        .filter((p) => p.sourceType === 'delegation')
                        .map((p) => (
                          <SelectItem key={p.id} value={String(p.sourceId)}>
                            {p.name} ({p.relationship})
                          </SelectItem>
                        ))}
                    </SelectContent>
                  </Select>
                </div>

                <div className="space-y-1">
                  <Label className="text-xs font-medium">Secondary Backup Delegate</Label>
                  <Select
                    value={planForm.secondary_delegate_id}
                    onValueChange={(val) => setPlanForm({ ...planForm, secondary_delegate_id: val })}
                  >
                    <SelectTrigger className="h-9 text-xs">
                      <SelectValue placeholder="Select Backup" />
                    </SelectTrigger>
                    <SelectContent>
                      {authorizedPeople
                        .filter((p) => p.sourceType === 'delegation')
                        .map((p) => (
                          <SelectItem key={p.id} value={String(p.sourceId)}>
                            {p.name} ({p.relationship})
                          </SelectItem>
                        ))}
                    </SelectContent>
                  </Select>
                </div>
              </div>

              {/* Conditions & Actions */}
              <div className="space-y-2">
                <Label className="text-xs font-semibold">Activation Conditions</Label>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                  {Object.entries(continuityConditions).map(([key, label]) => (
                    <label key={key} className="flex items-center gap-2 p-2 rounded border border-border/30 bg-muted/10 cursor-pointer">
                      <Checkbox
                        checked={planForm.activation_conditions.includes(key)}
                        onCheckedChange={(checked) => {
                          const updated = checked
                            ? [...planForm.activation_conditions, key]
                            : planForm.activation_conditions.filter((k) => k !== key);
                          setPlanForm({ ...planForm, activation_conditions: updated });
                        }}
                      />
                      <span>{label}</span>
                    </label>
                  ))}
                </div>
              </div>

              <div className="space-y-2">
                <Label className="text-xs font-semibold">Authorized Representative Actions</Label>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                  {Object.entries(continuityActions).map(([key, label]) => (
                    <label key={key} className="flex items-center gap-2 p-2 rounded border border-border/30 bg-muted/10 cursor-pointer">
                      <Checkbox
                        checked={planForm.authorized_actions.includes(key)}
                        onCheckedChange={(checked) => {
                          const updated = checked
                            ? [...planForm.authorized_actions, key]
                            : planForm.authorized_actions.filter((k) => k !== key);
                          setPlanForm({ ...planForm, authorized_actions: updated });
                        }}
                      />
                      <span>{label}</span>
                    </label>
                  ))}
                </div>
              </div>

              <div className="space-y-1">
                <Label className="text-xs">Special Emergency Instructions</Label>
                <Textarea
                  rows={2}
                  value={planForm.special_instructions}
                  onChange={(e) => setPlanForm({ ...planForm, special_instructions: e.target.value })}
                  placeholder="e.g. Contact family attorney at law firm prior to property delegation..."
                  className="text-xs"
                />
              </div>

              <DialogFooter className="pt-2">
                <Button type="button" variant="outline" size="sm" onClick={() => setIsContinuityModalOpen(false)}>
                  Cancel
                </Button>
                <Button type="submit" size="sm" className="bg-amber-600 hover:bg-amber-700 text-white">
                  Save Continuity Plan
                </Button>
              </DialogFooter>
            </form>
          </DialogContent>
        </Dialog>
      </div>
    </DashboardLayout>
  );
}
