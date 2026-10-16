import { useState } from 'react';
import { categoryDefaults, endOfYearIso, presetExpiry, quickSchedule, todayIso } from './delegation-rules';
import type { AuthorizedPerson, Props, RECURRING_WORKER_TEMPLATES } from './delegation-types';

const EVERY_DAY = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

/**
 * State for the two delegation forms, authorizing a new person and editing an existing person's
 * rules, with the shortcuts both offer (duration presets, quick schedules, end of year).
 */
export function useDelegationForms(properties: Props['properties']) {
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
    starts_at: todayIso(),
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
      allowed_days: [...EVERY_DAY],
      entry_start_time: '06:00',
      entry_end_time: '22:00',
    },
  });

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
      allowed_days: [...EVERY_DAY],
      entry_start_time: '00:00',
      entry_end_time: '23:59',
    },
  });

  const withDurationPreset = <T extends { starts_at: string }>(prev: T, presetKey: string): T => ({
    ...prev,
    duration_type: presetKey,
    starts_at: prev.starts_at || todayIso(),
    expires_at: presetExpiry(presetKey),
  });

  const handleSelectDurationPreset = (presetKey: string) => {
    setFormData((prev) => withDurationPreset(prev, presetKey));
  };

  const handleSelectEditRulesDurationPreset = (presetKey: string) => {
    setEditRulesData((prev) => withDurationPreset(prev, presetKey));
  };

  const handleApplyRecurringWorkerTemplate = (template: (typeof RECURRING_WORKER_TEMPLATES)[0]) => {
    setFormData((prev) => ({
      ...prev,
      relationship: template.relationship,
      categoryKey: template.categoryKey,
      access_level: template.accessLevel,
      authorization_type: template.authType,
      duration_type: 'recurring',
      starts_at: prev.starts_at || todayIso(),
      expires_at: endOfYearIso(),
      access_rules: {
        ...prev.access_rules,
        allowed_days: template.days,
        entry_start_time: template.startTime,
        entry_end_time: template.endTime,
      },
    }));
  };

  const setEndOfYearExpiration = (target: 'form' | 'edit') => {
    const set = target === 'form' ? setFormData : setEditRulesData;
    set((prev: any) => ({ ...prev, expires_at: endOfYearIso() }));
  };

  const applyQuickSchedule = (shiftKey: string, target: 'form' | 'edit') => {
    const schedule = quickSchedule(shiftKey);
    const set = target === 'form' ? setFormData : setEditRulesData;
    set((prev: any) => ({ ...prev, access_rules: { ...prev.access_rules, ...schedule } }));
  };

  const applyCategoryDefaults = (categoryKey: string) => {
    setFormData((prev) => ({ ...prev, categoryKey, ...categoryDefaults(categoryKey) }));
  };

  /** Fill the edit form from a person's current rules. */
  const loadEditRules = (person: AuthorizedPerson) => {
    setEditRulesData({
      duration_type: person.durationType || 'custom',
      starts_at: person.startsAt ? person.startsAt.split('T')[0] : '',
      expires_at: person.expiresAt ? person.expiresAt.split('T')[0] : '',
      permissions: Array.isArray(person.permissions) ? person.permissions : Object.keys(person.permissions),
      access_rules: {
        gate_access: person.accessRules?.gate_access ?? true,
        pool_access: person.accessRules?.pool_access ?? false,
        gym_access: person.accessRules?.gym_access ?? false,
        clubhouse_access: person.accessRules?.clubhouse_access ?? false,
        parking_allocated: person.accessRules?.parking_allocated ?? false,
        can_invite_visitors: person.accessRules?.can_invite_visitors ?? false,
        requires_id_verification: person.accessRules?.requires_id_verification ?? false,
        allowed_days: person.accessRules?.allowed_days || [...EVERY_DAY],
        entry_start_time: person.accessRules?.entry_start_time || '00:00',
        entry_end_time: person.accessRules?.entry_end_time || '23:59',
      },
    });
  };

  return {
    formData,
    setFormData,
    editRulesData,
    setEditRulesData,
    handleSelectDurationPreset,
    handleSelectEditRulesDurationPreset,
    handleApplyRecurringWorkerTemplate,
    setEndOfYearExpiration,
    applyQuickSchedule,
    applyCategoryDefaults,
    loadEditRules,
  };
}
