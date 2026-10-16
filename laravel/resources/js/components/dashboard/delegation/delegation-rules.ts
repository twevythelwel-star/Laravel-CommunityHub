import type { AuthorizedPerson } from './delegation-types';

const EVERY_DAY = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

const isoDate = (date: Date) => date.toISOString().split('T')[0];

export const todayIso = () => isoDate(new Date());

export const endOfYearIso = () => `${new Date().getFullYear()}-12-31`;

/** When a duration preset ends, counted from today; empty means until revoked. */
export function presetExpiry(presetKey: string): string {
  const today = new Date();
  if (presetKey === '2_hours' || presetKey === '1_day') return isoDate(today);
  if (presetKey === '1_week') return isoDate(new Date(today.getTime() + 7 * 86400000));
  if (presetKey === 'recurring') return endOfYearIso();
  if (presetKey === 'manual_revocation') return '';
  return isoDate(new Date(today.getTime() + 30 * 86400000));
}

type Schedule = { allowed_days: string[]; entry_start_time: string; entry_end_time: string };

const QUICK_SCHEDULES: Record<string, Schedule> = {
  mon_fri: { allowed_days: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'], entry_start_time: '08:00', entry_end_time: '17:00' },
  mon_sat: { allowed_days: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'], entry_start_time: '06:00', entry_end_time: '20:00' },
  tue_thu: { allowed_days: ['Tue', 'Thu'], entry_start_time: '08:00', entry_end_time: '16:00' },
  weekends: { allowed_days: ['Sat', 'Sun'], entry_start_time: '09:00', entry_end_time: '18:00' },
  everyday: { allowed_days: EVERY_DAY, entry_start_time: '00:00', entry_end_time: '23:59' },
};

/** The days and hours of a quick-schedule shortcut. */
export function quickSchedule(shiftKey: string): Schedule {
  return QUICK_SCHEDULES[shiftKey] ?? { allowed_days: EVERY_DAY, entry_start_time: '08:00', entry_end_time: '17:00' };
}

type CategoryDefaults = {
  access_level: string;
  authorization_type: string;
  duration_type: string;
  relationship: string;
};

const CATEGORY_DEFAULTS: Record<string, CategoryDefaults> = {
  visitors: { access_level: 'Visitor', authorization_type: 'visitor', duration_type: '1_day', relationship: 'Visitor' },
  domestic_staff: { access_level: 'Domestic Staff', authorization_type: 'domestic_staff', duration_type: 'recurring', relationship: 'Domestic Worker' },
  contractors: { access_level: 'Contractor', authorization_type: 'contractor', duration_type: '1_week', relationship: 'Contractor' },
  legacy_contacts: { access_level: 'Emergency Contact', authorization_type: 'legacy_contact', duration_type: 'manual_revocation', relationship: 'Emergency Contact' },
  caregivers: { access_level: 'Caregiver', authorization_type: 'caregiver', duration_type: 'recurring', relationship: 'Caregiver' },
  property_managers: { access_level: 'Property Delegate', authorization_type: 'property_manager', duration_type: 'custom', relationship: 'Property Manager' },
};

/** The access level, authorization type, duration and relationship a new authorization starts with. */
export function categoryDefaults(categoryKey: string): CategoryDefaults {
  return CATEGORY_DEFAULTS[categoryKey] ?? {
    access_level: 'Long-Term Occupant',
    authorization_type: 'long_term_occupant',
    duration_type: 'custom',
    relationship: 'Family/Friend',
  };
}

/** Tabs and sections that gather several categories; any other key is just its own category. */
const CATEGORY_GROUPS: Record<string, string[]> = {
  staff: ['staff', 'domestic_staff', 'caregivers', 'property_managers'],
  legacy_contacts: ['legacy_contacts', 'family_members'],
  my_visitors: ['my_visitors', 'visitors'],
  property_services: ['property_services', 'staff', 'domestic_staff', 'caregivers', 'property_managers', 'contractors'],
};

export function inCategory(person: AuthorizedPerson, key: string): boolean {
  return (CATEGORY_GROUPS[key] ?? [key]).includes(person.categoryKey);
}

export function matchesFilters(person: AuthorizedPerson, category: string, status: string, search: string): boolean {
  if (category !== 'all' && !inCategory(person, category)) return false;

  if (status === 'active' && person.status !== 'active') return false;
  if (status === 'pending' && person.status !== 'pending') return false;
  if (status === 'revoked_expired' && !['expired', 'revoked'].includes(person.status)) return false;

  if (!search.trim()) return true;
  const q = search.toLowerCase();
  return [person.name, person.email, person.phone, person.credential?.passId, person.relationship, person.categoryLabel]
    .some((field) => field?.toLowerCase().includes(q));
}

/** The number shown on a category tab: the server's count where it has one, otherwise counted here. */
export function tabCount(tabKey: string, counts: Record<string, number>, people: AuthorizedPerson[]): number {
  const counted = () => people.filter((p) => inCategory(p, tabKey)).length;

  if (tabKey === 'all') return counts.all ?? people.length;
  if (tabKey === 'staff') {
    return (counts.staff ?? 0) || ((counts.domestic_staff ?? 0) + (counts.caregivers ?? 0) + (counts.property_managers ?? 0));
  }
  if (tabKey === 'legacy_contacts') return (counts.legacy_contacts ?? 0) + (counts.family_members ?? 0);
  if (tabKey === 'my_visitors') return (counts.my_visitors ?? 0) || (counts.visitors ?? 0) || counted();
  return counts[tabKey] ?? counted();
}
