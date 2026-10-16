import React, { useState, useMemo, useEffect } from 'react';
import { Head, router } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { Button } from '@/components/ui/button';
import { AlertTriangle, Plus, Users } from 'lucide-react';
import { useToast } from '@/hooks/use-toast';
import {
  AuthorizedPerson,
  CATEGORY_TABS,
  HOMEOWNER_SECTIONS_CONFIG,
  Props,
  RENTER_CATEGORY_TABS,
  RENTER_SECTIONS_CONFIG,
  SHORT_TERM_CATEGORY_TABS,
  SHORT_TERM_SECTIONS_CONFIG,
} from '@/components/dashboard/delegation/delegation-types';
import { matchesFilters, tabCount } from '@/components/dashboard/delegation/delegation-rules';
import { useDelegationForms } from '@/components/dashboard/delegation/use-delegation-forms';
import { DelegationPersonCard } from '@/components/dashboard/delegation/delegation-person-card';
import { DelegationPageHeader } from '@/components/dashboard/delegation/delegation-page-header';
import { DelegationOwnershipBanner } from '@/components/dashboard/delegation/delegation-ownership-banner';
import { DelegationScopeBanner } from '@/components/dashboard/delegation/delegation-scope-banner';
import { DelegationFilterBar, type DelegationViewMode } from '@/components/dashboard/delegation/delegation-filter-bar';
import { DelegationSectionsView, type PersonCardActions } from '@/components/dashboard/delegation/delegation-sections-view';
import { DelegationPeopleTable } from '@/components/dashboard/delegation/delegation-people-table';
import { DelegationCredentialModal } from '@/components/dashboard/delegation/delegation-credential-modal';
import { DelegationAuthorizeModal } from '@/components/dashboard/delegation/delegation-authorize-modal';
import { DelegationEditRulesModal } from '@/components/dashboard/delegation/delegation-edit-rules-modal';
import {
  DelegationAuditModal,
  DelegationContinuityModal,
  DelegationEmergencyModal,
  DelegationRevokeModal,
} from '@/components/dashboard/delegation/delegation-action-modals';

export default function DelegatedAccessCenter({
  authorizedPeople = [],
  categoryCounts = {},
  permissions,
  scopeBadge,
  pageSubtitle,
  unitLabel = '',
  pageTitle,
  continuityPlan,
  properties = [],
  continuityConditions = {},
  continuityActions = {},
  ownershipEntity = null,
}: Props) {
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

  const [selectedCategory, setSelectedCategory] = useState<string>('all');
  const [searchQuery, setSearchQuery] = useState('');
  const [statusFilter, setStatusFilter] = useState('all');
  const [viewMode, setViewMode] = useState<DelegationViewMode>('sections');

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

  const {
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
  } = useDelegationForms(properties);

  // Action states
  const [emergencyReason, setEmergencyReason] = useState('');
  const [emergencyDurationDays, setEmergencyDurationDays] = useState(7);
  const [emergencyNotes, setEmergencyNotes] = useState('');
  const [revokeReason, setRevokeReason] = useState('');

  // Continuity Plan Form State
  const [planForm, setPlanForm] = useState({
    primary_delegate_id: continuityPlan?.primary_delegate_id ? String(continuityPlan.primary_delegate_id) : '',
    secondary_delegate_id: continuityPlan?.secondary_delegate_id ? String(continuityPlan.secondary_delegate_id) : '',
    activation_conditions: continuityPlan?.activation_conditions || ['unreachable_72h', 'declared_medical_emergency'],
    required_verification: continuityPlan?.required_verification || 'two_factor_plus_admin',
    authorized_actions: continuityPlan?.authorized_actions || ['manage_gate_passes', 'authorize_visitors', 'emergency_lockdown'],
    max_duration_days: continuityPlan?.max_duration_days || 30,
    requires_admin_approval: continuityPlan?.requires_admin_approval ?? true,
    notify_homeowner_on_trigger: continuityPlan?.notify_homeowner_on_trigger ?? true,
    notify_community_security: continuityPlan?.notify_community_security ?? true,
    special_instructions: continuityPlan?.special_instructions || '',
  });

  const handleOpenAuthorizeModal = (categoryKey = 'long_term_occupants') => {
    applyCategoryDefaults(categoryKey);
    setIsAuthorizeModalOpen(true);
  };

  const handleOpenCredentialModal = (person: AuthorizedPerson) => {
    setActivePerson(person);
    setIsCredentialModalOpen(true);
  };

  const handleOpenEditRulesModal = (person: AuthorizedPerson) => {
    setActivePerson(person);
    loadEditRules(person);
    setIsEditRulesModalOpen(true);
  };

  const handleOpenEmergencyModal = (person: AuthorizedPerson) => {
    setActivePerson(person);
    setEmergencyReason('');
    setEmergencyDurationDays(7);
    setEmergencyNotes('');
    setIsEmergencyModalOpen(true);
  };

  const handleOpenRevokeModal = (person: AuthorizedPerson) => {
    setActivePerson(person);
    setRevokeReason('');
    setIsRevokeModalOpen(true);
  };

  const handleViewAudit = async (person: AuthorizedPerson) => {
    setActivePerson(person);
    setIsAuditModalOpen(true);
    setLoadingAudit(true);
    try {
      const res = await fetch(`/dashboard/delegation/${person.sourceId}/audit`);
      if (res.ok) {
        const data = await res.json();
        setAuditEvents(data.events || []);
      }
    } catch {
      toast({ title: 'Error', description: 'Could not load audit events.', variant: 'destructive' });
    } finally {
      setLoadingAudit(false);
    }
  };

  const handleReissuePass = (person: AuthorizedPerson) => {
    router.post(
      `/dashboard/delegation/${person.sourceId}/reissue-pass`,
      {},
      {
        onSuccess: () => {
          toast({
            title: 'QR Credential Reissued',
            description: `Fresh secure pass token minted for ${person.name}. Previous QR code revoked.`,
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
          description: (Object.values(err)[0] as string) || 'Please verify form values.',
          variant: 'destructive',
        });
      },
    });
  };

  const handleSaveRules = (e: React.FormEvent) => {
    e.preventDefault();
    if (!activePerson) return;
    router.patch(`/dashboard/delegation/${activePerson.sourceId}/rules`, editRulesData, {
      onSuccess: () => {
        toast({
          title: 'Rules Updated',
          description: `Access schedule and permissions updated for ${activePerson.name}.`,
        });
        setIsEditRulesModalOpen(false);
      },
      onError: () => {
        toast({
          title: 'Update Failed',
          description: 'Could not update access rules.',
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

  const filteredPeople = useMemo(
    () => authorizedPeople.filter((p) => matchesFilters(p, selectedCategory, statusFilter, searchQuery)),
    [authorizedPeople, selectedCategory, statusFilter, searchQuery],
  );

  const selectCategory = (tabKey: string) => {
    setSelectedCategory(tabKey);
    if (tabKey !== 'all' && viewMode === 'sections') {
      setViewMode('cards');
    }
  };

  const personActions: PersonCardActions = {
    onViewPass: handleOpenCredentialModal,
    onEditRules: handleOpenEditRulesModal,
    onReissuePass: handleReissuePass,
    onViewAudit: handleViewAudit,
    onTriggerEmergency: handleOpenEmergencyModal,
    onDeactivateEmergency: handleDeactivateEmergency,
    onRevoke: handleOpenRevokeModal,
  };

  return (
    <DashboardLayout>
      <Head title={pageTitle || `${unitLabel || 'My Property'} — People & Access | Community Hub`} />

      <div className="space-y-6 max-w-7xl mx-auto pb-16">
        <DelegationPageHeader
          unitLabel={unitLabel}
          pageTitle={pageTitle}
          pageSubtitle={pageSubtitle}
          scopeBadge={scopeBadge}
          permissions={permissions}
          isRenter={isRenter}
          isShortTerm={isShortTerm}
          onOpenContinuityPlan={() => setIsContinuityModalOpen(true)}
          onAuthorize={() => handleOpenAuthorizeModal(selectedCategory !== 'all' ? selectedCategory : (isShortTerm ? 'visitors' : 'long_term_occupants'))}
        />

        {ownershipEntity && <DelegationOwnershipBanner ownershipEntity={ownershipEntity} unitLabel={unitLabel} />}

        <DelegationScopeBanner isShortTerm={isShortTerm} isRenter={isRenter} unitLabel={unitLabel} permissions={permissions} />

        <DelegationFilterBar
          tabs={activeCategoryTabs}
          tabCount={(tabKey) => tabCount(tabKey, categoryCounts, authorizedPeople)}
          selectedCategory={selectedCategory}
          onSelectCategory={selectCategory}
          searchQuery={searchQuery}
          onSearchQueryChange={setSearchQuery}
          statusFilter={statusFilter}
          onStatusFilterChange={setStatusFilter}
          viewMode={viewMode}
          onViewModeChange={setViewMode}
          isRenter={isRenter}
        />

        {/* Emergency Alert */}
        {authorizedPeople.some((p) => p.isEmergencyActive) && (
          <div className="flex items-center justify-between p-4 rounded-xl border border-red-500/40 bg-red-500/10 text-red-400">
            <div className="flex items-center gap-3">
              <div className="p-2 rounded-lg bg-red-500/20 text-red-500">
                <AlertTriangle className="w-5 h-5 animate-pulse" />
              </div>
              <div>
                <p className="text-sm font-semibold">Active Emergency Access Override Declared</p>
                <p className="text-xs text-red-300/80">
                  Elevated security clearance is actively unlocked for designated emergency delegates.
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

        {/* Main Render Views */}
        {viewMode === 'sections' && selectedCategory === 'all' ? (
          <DelegationSectionsView
            sections={activeSectionsConfig}
            people={filteredPeople}
            actions={personActions}
            onAuthorize={handleOpenAuthorizeModal}
          />
        ) : viewMode === 'cards' ? (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {filteredPeople.map((person) => (
              <DelegationPersonCard key={person.id} person={person} {...personActions} />
            ))}
          </div>
        ) : (
          <DelegationPeopleTable
            people={filteredPeople}
            onViewPass={handleOpenCredentialModal}
            onEditRules={handleOpenEditRulesModal}
          />
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

        {/* Subcomponent Modals */}
        <DelegationCredentialModal
          isOpen={isCredentialModalOpen}
          onOpenChange={setIsCredentialModalOpen}
          activePerson={activePerson}
          secondsRemaining={secondsRemaining}
          onCopyLink={(text) => {
            navigator.clipboard.writeText(text);
            toast({ title: 'Copied', description: 'Credential link copied to clipboard.' });
          }}
        />

        <DelegationAuthorizeModal
          isOpen={isAuthorizeModalOpen}
          onOpenChange={setIsAuthorizeModalOpen}
          formData={formData}
          setFormData={setFormData}
          onSubmit={handleCreateAuthorization}
          properties={properties}
          isShortTerm={isShortTerm}
          isRenter={isRenter}
          permissions={permissions}
          applyCategoryDefaults={applyCategoryDefaults}
          handleSelectDurationPreset={handleSelectDurationPreset}
          handleApplyRecurringWorkerTemplate={handleApplyRecurringWorkerTemplate}
          setEndOfYearExpiration={setEndOfYearExpiration}
          applyQuickSchedule={applyQuickSchedule}
        />

        <DelegationEditRulesModal
          isOpen={isEditRulesModalOpen}
          onOpenChange={setIsEditRulesModalOpen}
          activePerson={activePerson}
          editRulesData={editRulesData}
          setEditRulesData={setEditRulesData}
          onSubmit={handleSaveRules}
          isRenter={isRenter}
          permissions={permissions}
          handleSelectEditRulesDurationPreset={handleSelectEditRulesDurationPreset}
          setEndOfYearExpiration={setEndOfYearExpiration}
          applyQuickSchedule={applyQuickSchedule}
        />

        <DelegationEmergencyModal
          isOpen={isEmergencyModalOpen}
          onOpenChange={setIsEmergencyModalOpen}
          activePerson={activePerson}
          reason={emergencyReason}
          setReason={setEmergencyReason}
          durationDays={emergencyDurationDays}
          setDurationDays={setEmergencyDurationDays}
          notes={emergencyNotes}
          setNotes={setEmergencyNotes}
          onSubmit={handleActivateEmergency}
        />

        <DelegationRevokeModal
          isOpen={isRevokeModalOpen}
          onOpenChange={setIsRevokeModalOpen}
          activePerson={activePerson}
          reason={revokeReason}
          setReason={setRevokeReason}
          onSubmit={handleRevoke}
        />

        <DelegationAuditModal
          isOpen={isAuditModalOpen}
          onOpenChange={setIsAuditModalOpen}
          activePerson={activePerson}
          loading={loadingAudit}
          events={auditEvents}
        />

        <DelegationContinuityModal
          isOpen={isContinuityModalOpen}
          onOpenChange={setIsContinuityModalOpen}
          authorizedPeople={authorizedPeople}
          planForm={planForm}
          setPlanForm={setPlanForm}
          continuityConditions={continuityConditions}
          continuityActions={continuityActions}
          onSubmit={handleSaveContinuityPlan}
        />
      </div>
    </DashboardLayout>
  );
}
