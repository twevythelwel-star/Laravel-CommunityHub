<?php

namespace App\Services\Delegation;

use App\Enums\AuthorizationType;
use App\Enums\PassStatus;
use App\Enums\UserRole;
use App\Events\Realtime\OperationsCommandCenterEvent;
use App\Models\DelegatedAccess;
use App\Models\EmergencyContinuityPlan;
use App\Models\GatePass;
use App\Models\InAppNotification;
use App\Models\User;
use App\Services\GatePassEngine;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DelegatedAccessService
{
    public function __construct(
        protected GatePassEngine $gatePassEngine
    ) {}

    /**
     * Create a new delegated access record or emergency contact.
     *
     * @param  array<string, mixed>  $data
     */
    public function createDelegation(User $grantor, array $data): DelegatedAccess
    {
        return DB::transaction(function () use ($grantor, $data) {
            $email = strtolower(trim($data['email']));

            $accessLevel = $data['access_level'];
            $activationMethod = $data['activation_method'] ?? 'immediate';

            // Requires admin approval if specified or full authorized rep
            $approvalStatus = ($activationMethod === 'admin_approval_required' || $accessLevel === 'Full Authorized Representative')
                ? 'pending'
                : 'approved';

            $authorizationType = $data['authorization_type'] ?? match ($accessLevel) {
                'Long-Term Occupant' => 'long_term_occupant',
                'Caregiver' => 'caregiver',
                'Property Delegate' => 'property_manager',
                'Authorized Representative', 'Full Authorized Representative' => 'authorized_representative',
                'Legacy Delegate' => 'legacy_contact',
                'Domestic Staff' => 'domestic_staff',
                'Family Member' => 'family_member',
                'Contractor' => 'contractor',
                default => ($accessLevel === 'Emergency Contact' ? 'emergency_contact' : 'legacy_contact'),
            };

            $authEnum = AuthorizationType::tryFrom($authorizationType);
            $defaultRules = $authEnum?->defaultAccessRules() ?? [];
            $accessRules = array_merge($defaultRules, $this->normalizeRules($data['access_rules'] ?? []));

            $permissions = $data['permissions'] ?? [];
            if ($accessLevel === 'Long-Term Occupant' && ! in_array('gate_access', $permissions)) {
                $permissions[] = 'gate_access';
            }

            $durationType = $data['duration_type'] ?? 'custom';
            $startsAt = ! empty($data['starts_at']) ? Carbon::parse($data['starts_at']) : now();
            $expiresAt = $this->expiresAt($durationType, $startsAt, $data['expires_at'] ?? null, null);

            $delegation = DelegatedAccess::create([
                'grantor_user_id' => $grantor->id,
                // Linked when the invitee accepts (acceptInvite), not because the
                // grantor typed an email that happens to match an account.
                'delegate_user_id' => null,
                'property_id' => $data['property_id'] ?? $grantor->properties()->first()?->id,
                'name' => trim($data['name']),
                'email' => $email,
                'phone' => $data['phone'] ?? null,
                'relationship' => $data['relationship'],
                'authorization_type' => $authorizationType,
                'access_level' => $accessLevel,
                'duration_type' => $durationType,
                'permissions' => $permissions,
                'access_rules' => $accessRules,
                'status' => 'active',
                'starts_at' => $startsAt,
                'expires_at' => $expiresAt,
                'activation_method' => $activationMethod,
                'approval_status' => $approvalStatus,
                'approved_by' => $approvalStatus === 'approved' ? $grantor->id : null,
                'approved_at' => $approvalStatus === 'approved' ? now() : null,
                'invite_token' => Str::random(40),
                'security_notes' => $data['security_notes'] ?? null,
            ]);

            $delegation->logEvent('created', "Delegated access created ({$accessLevel}) for {$delegation->name}", [
                'access_level' => $accessLevel,
                'authorization_type' => $authorizationType,
                'relationship' => $delegation->relationship,
                'activation_method' => $activationMethod,
                'permissions' => $delegation->permissions,
                'access_rules' => $delegation->access_rules,
            ], $grantor);

            // If gate access permission granted and active, issue Gate Pass
            // Approved only: a pending delegation used to get a working pass.
            if (($delegation->hasPermission('gate_access') || ! empty($accessRules['gate_access'])) && $delegation->status === 'active' && $delegation->approval_status === 'approved') {
                $delegation->issueDelegateGatePass($this->gatePassEngine, $grantor);
            }

            // Create notification for grantor
            InAppNotification::create([
                'user_id' => $grantor->id,
                'tenant_id' => $grantor->tenant_id ?? null,
                'category' => 'delegation',
                'title' => "Delegated Contact Added: {$delegation->name}",
                'body' => "You have designated {$delegation->name} ({$delegation->relationship}) as an {$accessLevel}.",
                'action_url' => '/dashboard/delegation',
                'priority' => 'normal',
                'data' => [
                    'delegation_id' => $delegation->id,
                    'access_level' => $accessLevel,
                ],
            ]);

            return $delegation;
        });
    }

    /**
     * Change an authorization's access rules, permissions and duration, and
     * carry the new validity and rules onto its live gate passes.
     *
     * Rules given are merged over the saved ones; a duration given
     * recomputes the window from the new (or saved) start.
     *
     * @param  array{access_rules: array<string, mixed>, permissions?: array<int, string>, duration_type?: ?string, starts_at?: ?string, expires_at?: ?string}  $data
     */
    public function updateRules(DelegatedAccess $delegation, array $data, User $actor): DelegatedAccess
    {
        $updates = [
            'access_rules' => array_merge($delegation->access_rules ?? [], $this->normalizeRules($data['access_rules'])),
            'permissions' => $data['permissions'] ?? $delegation->permissions,
        ];

        if (! empty($data['duration_type'])) {
            $startsAt = ! empty($data['starts_at']) ? Carbon::parse($data['starts_at']) : ($delegation->starts_at ?? now());
            $updates['duration_type'] = $data['duration_type'];
            $updates['starts_at'] = $startsAt;
            $updates['expires_at'] = $this->expiresAt($data['duration_type'], $startsAt, $data['expires_at'] ?? null, $delegation->expires_at);
        }

        $delegation->update($updates);

        $validUntil = $delegation->passValidUntil();
        $delegation->gatePasses()
            ->whereIn('status', [PassStatus::Active, PassStatus::Issued, PassStatus::CheckedIn])
            ->get()
            ->each(fn (GatePass $pass) => $pass->update([
                'valid_until' => $validUntil,
                'metadata' => array_merge($pass->metadata ?? [], [
                    'duration_type' => $delegation->duration_type,
                    'access_rules' => $delegation->access_rules,
                ]),
            ]));

        $delegation->logEvent('rules_updated', "Access permissions & operational rules updated for {$delegation->name}", [
            'access_rules' => $delegation->access_rules,
            'permissions' => $delegation->permissions,
            'duration_type' => $delegation->duration_type,
        ], $actor);

        return $delegation;
    }

    /**
     * The form's entry_start_time/entry_end_time and allowed_days, also
     * stored as the time_window and days_permitted the scanner enforces.
     *
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    private function normalizeRules(array $rules): array
    {
        if (! empty($rules['entry_start_time']) && ! empty($rules['entry_end_time'])) {
            $rules['time_window'] = ['start' => $rules['entry_start_time'], 'end' => $rules['entry_end_time']];
        }
        if (! empty($rules['allowed_days'])) {
            $rules['days_permitted'] = $rules['allowed_days'];
        }

        return $rules;
    }

    /**
     * When an authorization of this duration, starting then, ends. A custom
     * duration uses the requested end, else $fallback.
     */
    private function expiresAt(string $durationType, Carbon $startsAt, ?string $requested, ?Carbon $fallback): ?Carbon
    {
        return match ($durationType) {
            '2_hours' => (clone $startsAt)->addHours(2),
            '1_day' => (clone $startsAt)->isToday() ? (clone $startsAt)->endOfDay() : (clone $startsAt)->addDay(),
            '1_week' => (clone $startsAt)->addDays(7)->endOfDay(),
            'manual_revocation' => null,
            'recurring' => $requested ? Carbon::parse($requested) : (clone $startsAt)->endOfYear(),
            default => $requested ? Carbon::parse($requested) : $fallback,
        };
    }

    /**
     * Activate emergency delegated access.
     */
    public function activateEmergencyAccess(
        DelegatedAccess $delegation,
        string $reason,
        User $actor,
        int $durationDays = 7,
        ?string $notes = null
    ): DelegatedAccess {
        return DB::transaction(function () use ($delegation, $reason, $actor, $durationDays, $notes) {
            $expiresAt = now()->addDays($durationDays);
            $delegation->activateEmergency($reason, $actor, $expiresAt, $notes);

            // One emergency pass, replacing any current one; each activation
            // used to add another without retiring the last.
            if ($delegation->hasPermission('gate_access') && $delegation->approval_status === 'approved') {
                $delegation->revokePasses($actor, 'Replaced by emergency pass');
                $delegation->issueDelegateGatePass($this->gatePassEngine, $actor);
            }

            $grantor = $delegation->grantor;

            // Broadcast to Command Center and Security Dispatch
            OperationsCommandCenterEvent::dispatch(
                alertId: 'EMERG-DEL-'.strtoupper(bin2hex(random_bytes(3))),
                type: 'emergency',
                severity: 'warning',
                headline: sprintf('EMERGENCY ACCESS ACTIVATED: %s for %s (%s)', $delegation->name, $grantor?->name, $grantor?->lot ?? 'Lot'),
                location: $grantor?->propertyLabel() ?? 'Residence',
                operatorName: $actor->name,
                details: [
                    'delegation_id' => $delegation->id,
                    'delegate_name' => $delegation->name,
                    'relationship' => $delegation->relationship,
                    'grantor_name' => $grantor?->name,
                    'reason' => $reason,
                    'expires_at' => $expiresAt->toIso8601String(),
                ]
            );

            // Notify Homeowner
            if ($grantor) {
                InAppNotification::create([
                    'user_id' => $grantor->id,
                    'tenant_id' => $grantor->tenant_id ?? null,
                    'category' => 'emergency',
                    'title' => "🚨 Emergency Access Activated: {$delegation->name}",
                    'body' => "Emergency delegated access has been activated for {$delegation->name} ({$delegation->relationship}). Reason: {$reason}. Active until {$expiresAt->format('M d, Y')}.",
                    'action_url' => '/dashboard/delegation',
                    'priority' => 'critical',
                    'data' => [
                        'delegation_id' => $delegation->id,
                        'reason' => $reason,
                        'expires_at' => $expiresAt->toIso8601String(),
                    ],
                ]);
            }

            // Notify Security Guards
            User::where('role', UserRole::Security->value)->get()->each(function (User $guard) use ($delegation, $grantor, $expiresAt) {
                InAppNotification::create([
                    'user_id' => $guard->id,
                    'category' => 'security',
                    'title' => "⚠️ Security Notice: Delegate {$delegation->name} Authorized",
                    'body' => "Emergency authorization active for {$delegation->name} acting on behalf of {$grantor?->name} at {$grantor?->lot}. Valid until {$expiresAt->format('M d, Y')}.",
                    'action_url' => '/dashboard/gate-scanner',
                    'priority' => 'high',
                ]);
            });

            Log::channel('security')->warning('Emergency delegated access activated', [
                'delegation_id' => $delegation->id,
                'grantor_id' => $delegation->grantor_user_id,
                'delegate_name' => $delegation->name,
                'reason' => $reason,
                'actor_id' => $actor->id,
            ]);

            return $delegation;
        });
    }

    /**
     * Deactivate emergency delegated access.
     */
    public function deactivateEmergencyAccess(DelegatedAccess $delegation, User $actor, ?string $reason = null): DelegatedAccess
    {
        $delegation->deactivateEmergency($actor, $reason);

        // End it at the gate too: the emergency pass used to stay valid to its
        // original expiry. A delegation that is still active gets an ordinary pass.
        $delegation->revokePasses($actor, 'Emergency access ended');
        if ($delegation->isActive() && $delegation->hasPermission('gate_access')) {
            $delegation->issueDelegateGatePass($this->gatePassEngine, $actor);
        }

        // Notify grantor
        if ($delegation->grantor) {
            InAppNotification::create([
                'user_id' => $delegation->grantor_user_id,
                'category' => 'delegation',
                'title' => "Emergency Access Ended: {$delegation->name}",
                'body' => "Emergency access for {$delegation->name} has been deactivated.",
                'action_url' => '/dashboard/delegation',
                'priority' => 'normal',
            ]);
        }

        return $delegation;
    }

    /**
     * Revoke a delegation permanently.
     */
    public function revokeDelegation(DelegatedAccess $delegation, User $actor, ?string $reason = null): DelegatedAccess
    {
        return $delegation->revoke($actor, $reason);
    }

    /**
     * Create or update the homeowner's Emergency Continuity Plan.
     *
     * @param  array<string, mixed>  $data
     */
    public function saveContinuityPlan(User $homeowner, array $data): EmergencyContinuityPlan
    {
        return EmergencyContinuityPlan::updateOrCreate(
            ['user_id' => $homeowner->id],
            [
                'primary_delegate_id' => $data['primary_delegate_id'] ?? null,
                'secondary_delegate_id' => $data['secondary_delegate_id'] ?? null,
                'activation_conditions' => $data['activation_conditions'] ?? ['medical_emergency', 'incapacity'],
                'required_verification' => $data['required_verification'] ?? 'admin_verification',
                'authorized_actions' => $data['authorized_actions'] ?? ['gate_access', 'visitor_management', 'property_maintenance'],
                'max_duration_days' => $data['max_duration_days'] ?? 30,
                'requires_admin_approval' => $data['requires_admin_approval'] ?? true,
                'notify_homeowner_on_trigger' => $data['notify_homeowner_on_trigger'] ?? true,
                'notify_community_security' => $data['notify_community_security'] ?? true,
                'notification_recipients' => $data['notification_recipients'] ?? [],
                'special_instructions' => $data['special_instructions'] ?? null,
            ]
        );
    }

    /** An administrator approves a delegation that needed it; its pass is issued only now. */
    public function approveDelegation(DelegatedAccess $delegation, User $admin): DelegatedAccess
    {
        return DB::transaction(function () use ($delegation, $admin) {
            $delegation->update(['approval_status' => 'approved', 'approved_by' => $admin->id, 'approved_at' => now()]);
            $delegation->logEvent('approved', "Delegation approved by {$admin->name}", [], $admin);

            if ($delegation->isActive() && ($delegation->hasPermission('gate_access') || ! empty($delegation->access_rules['gate_access']))) {
                $delegation->issueDelegateGatePass($this->gatePassEngine, $admin);
            }

            return $delegation;
        });
    }

    public function rejectDelegation(DelegatedAccess $delegation, User $admin, ?string $reason = null): DelegatedAccess
    {
        return DB::transaction(function () use ($delegation, $admin, $reason) {
            $delegation->update(['approval_status' => 'rejected', 'approved_by' => $admin->id, 'approved_at' => now()]);
            $delegation->logEvent('rejected', 'Delegation rejected'.($reason ? ": {$reason}" : ''), ['reason' => $reason], $admin);
            $delegation->revokePasses($admin, 'Delegation rejected');

            return $delegation;
        });
    }

    /**
     * The invitee links the delegation to their own account. Only the account
     * with the invited email may, and the token is single-use.
     */
    public function acceptInvite(string $token, User $user): DelegatedAccess
    {
        $delegation = DelegatedAccess::where('invite_token', $token)->first();

        if (! $delegation || strcasecmp($delegation->email, $user->email) !== 0) {
            throw new \DomainException('This invitation is not for your account, or has already been used.');
        }

        $delegation->update(['delegate_user_id' => $user->id, 'invite_token' => null]);
        $delegation->logEvent('accepted', "Invitation accepted by {$user->name}", [], $user);

        return $delegation;
    }
}
