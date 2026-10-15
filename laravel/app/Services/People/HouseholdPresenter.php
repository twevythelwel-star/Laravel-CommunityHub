<?php

namespace App\Services\People;

use App\Models\GatePass;
use App\Models\HouseholdMember;
use App\Models\User;
use App\Services\GatePassEngine;
use App\Services\HouseholdManagementService;

/**
 * The props of the Family & Household page (Dashboard/Household): the
 * owner's household (created on first visit) and each member with their
 * role, permissions and, while their pass is active, a fresh QR token.
 */
class HouseholdPresenter
{
    public function __construct(
        private readonly HouseholdManagementService $households,
        private readonly GatePassEngine $engine,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function props(User $owner): array
    {
        $household = $this->households->getOrCreateHousehold($owner);

        return [
            'household' => [
                'id' => $household->id,
                'name' => $household->name,
                'property_number' => $household->property_number,
                'address' => $household->address,
                'notes' => $household->notes,
                'members' => $household->members->map(fn (HouseholdMember $m) => $this->member($m)),
            ],
            'availablePermissions' => HouseholdManagementService::AVAILABLE_PERMISSIONS,
            'isOwner' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function member(HouseholdMember $m): array
    {
        return [
            'id' => $m->id,
            'name' => $m->name,
            'email' => $m->email,
            'phone' => $m->phone,
            'role_in_household' => $m->role_in_household,
            'relationship_label' => $m->relationship_label,
            'pass_category' => $m->pass_category,
            'credential_label' => $m->credentialTypeLabel(),
            'role_badge_class' => $m->roleBadgeColor(),
            'permissions' => $m->permissions ?? [],
            'access_schedule' => $m->access_schedule,
            'status' => $m->status,
            'valid_until' => $m->valid_until?->toFormattedDateString(),
            'gate_pass' => $m->gatePass ? [
                'id' => $m->gatePass->id,
                'pass_id' => $m->gatePass->pass_id,
                'category' => $m->gatePass->category->value,
                'status' => $m->gatePass->status->value,
                'holder_name' => $m->gatePass->holder_name,
                'token' => $this->token($m->gatePass),
            ] : null,
        ];
    }

    /** A signed QR token for an active pass, falling back to its id if signing fails. */
    private function token(GatePass $pass): ?string
    {
        if (! $pass->isActive()) {
            return null;
        }

        try {
            return $this->engine->issueToken($pass)['token'];
        } catch (\Throwable) {
            return $pass->pass_id;
        }
    }
}
