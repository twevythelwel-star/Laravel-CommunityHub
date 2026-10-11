<?php

namespace App\Services\People;

use App\Models\Household;
use App\Models\Property;
use App\Models\Renter;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * PropertyPeopleService: Centralized service for querying people associated with a property.
 *
 * Extracts shared logic from DelegatedAccessController to avoid duplication across
 * RenterController, HouseholdController, and delegation views.
 */
class PropertyPeopleService
{
    /**
     * Determine the primary property for a user.
     */
    public function resolvePrimaryProperty(User $user, ?Renter $renterRecord = null): ?Property
    {
        $property = null;

        if ($renterRecord?->property_id) {
            $property = Property::find($renterRecord->property_id);
        }

        if (! $property && $user->properties()->exists()) {
            $property = $user->properties()->orderBy('id')->first();
        }

        if (! $property && $renterRecord?->homeowner_id) {
            $property = $this->rentedProperty($renterRecord->homeowner_id, $renterRecord->lot ?: $user->lot);
        }

        if (! $property && $user->allAuthorizedProperties()->isNotEmpty()) {
            $property = $user->allAuthorizedProperties()->first();
        }

        return $property;
    }

    /**
     * Get unit label for a user (e.g., "Unit 14").
     */
    public function resolveUnitLabel(User $user, ?Renter $renterRecord = null): string
    {
        $rawLot = $user->lot
            ?? $user->properties()->first()?->lot_number
            ?? $user->allAuthorizedProperties()->first()?->lot_number
            ?? $renterRecord?->lot;

        $lotNumber = $rawLot ? preg_replace('/^(unit|lot)\s+/i', '', trim($rawLot)) : null;

        return $lotNumber ? "Unit {$lotNumber}" : 'Your property';
    }

    /**
     * Get household members for a homeowner.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getHouseholdMembers(User $user, string $unitLabel): Collection
    {
        $households = Household::where('primary_homeowner_id', $user->id)
            ->with(['members.gatePass'])
            ->get();

        $members = collect();
        $formerOccupants = collect();

        foreach ($households as $hh) {
            foreach ($hh->members as $member) {
                $isMemberActive = ($member->status === 'active') && (! $member->valid_until || ! $member->valid_until->isPast());
                $mPass = $member->gatePass;

                $mCatCode = match ($member->pass_category) {
                    'HOMEOWNER' => 'GP-HO',
                    'RENTER' => 'GP-RNT',
                    'LONG_TERM_OCCUPANT' => 'GP-LTO',
                    'DELEGATE' => 'GP-DEL',
                    'HOMEOWNER_STAFF' => 'GP-HST',
                    default => 'GP-RES',
                };

                $mShape = match ($member->pass_category) {
                    'HOMEOWNER' => 'Hexagon',
                    'RENTER' => 'Squircle',
                    'LONG_TERM_OCCUPANT' => 'HouseHex',
                    'DELEGATE' => 'Octagon',
                    'HOMEOWNER_STAFF' => 'HouseHex',
                    default => 'RoundedSquare',
                };

                $memberItem = [
                    'id' => 'household-member-'.$member->id,
                    'sourceType' => 'household_member',
                    'sourceId' => $member->id,
                    'categoryKey' => $isMemberActive ? 'residents' : 'former_occupants',
                    'categoryLabel' => $isMemberActive ? ('Household Member ('.ucfirst($member->role_in_household).')') : 'Former Household Member',
                    'categoryBadgeColor' => $isMemberActive ? $member->roleBadgeColor() : 'bg-zinc-500/10 text-zinc-400 border-zinc-500/30',
                    'name' => $member->name,
                    'email' => $member->email,
                    'phone' => $member->phone,
                    'relationship' => $member->relationship_label ?: ucfirst($member->role_in_household),
                    'roleOrTitle' => 'Household Member • '.ucfirst($member->role_in_household),
                    'propertyLabel' => $unitLabel,
                    'propertyId' => null,
                    'status' => $isMemberActive ? 'active' : 'expired',
                    'startsAt' => $member->created_at?->toIso8601String(),
                    'expiresAt' => $member->valid_until?->toIso8601String(),
                    'activationMethod' => 'household_sponsorship',
                    'approvalStatus' => $isMemberActive ? 'approved' : 'expired',
                    'permissions' => $member->permissions ?? ['gate_access'],
                    'accessRules' => $member->access_schedule ?? [
                        'gate_access' => true,
                        'pool_access' => true,
                        'gym_access' => true,
                        'clubhouse_access' => true,
                        'allowed_days' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                        'entry_start_time' => '00:00',
                        'entry_end_time' => '23:59',
                    ],
                    'credential' => $mPass ? [
                        'passId' => $mPass->pass_id,
                        'category' => $mPass->category->value,
                        'categoryCode' => $mCatCode,
                        'shape' => $mShape,
                        'status' => $mPass->status->value,
                        'isActive' => $mPass->isActive() && $isMemberActive,
                        'validFrom' => $mPass->valid_from?->toIso8601String(),
                        'validUntil' => $mPass->valid_until?->toIso8601String(),
                    ] : [
                        'passId' => 'GP-HHM-'.$member->id,
                        'category' => $member->pass_category ?: 'RESIDENT',
                        'categoryCode' => $mCatCode,
                        'shape' => $mShape,
                        'status' => $isMemberActive ? 'ACTIVE' : 'EXPIRED',
                        'isActive' => $isMemberActive,
                        'validFrom' => $member->created_at?->toIso8601String(),
                        'validUntil' => $member->valid_until?->toIso8601String(),
                    ],
                    'raw' => $member,
                ];

                if ($isMemberActive) {
                    $members->push($memberItem);
                } else {
                    $formerOccupants->push($memberItem);
                }
            }
        }

        return collect([
            'active' => $members,
            'former' => $formerOccupants,
        ]);
    }

    /**
     * Get renters for a homeowner.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getRenters(User $user, string $unitLabel): Collection
    {
        $renters = Renter::where('homeowner_id', $user->id)->get();

        $longTerm = collect();
        $shortTerm = collect();

        foreach ($renters as $r) {
            $isRenterActive = ($r->status === 'Active') && (! $r->lease_end || ! $r->lease_end->isPast());
            $isItemShortTerm = (stripos($r->stay_type ?? '', 'short') !== false)
                || (stripos($r->stay_type ?? '', 'airbnb') !== false)
                || (stripos($r->notes ?? '', 'airbnb') !== false);

            $item = [
                'id' => 'renter-'.$r->id,
                'sourceType' => 'renter',
                'sourceId' => $r->id,
                'categoryKey' => $isItemShortTerm ? 'short_term_rentals' : 'long_term_renters',
                'categoryLabel' => $isItemShortTerm ? 'Short-Term Rental' : 'Long-Term Renter',
                'categoryBadgeColor' => $isItemShortTerm ? 'bg-amber-500/10 text-amber-400 border-amber-500/30' : 'bg-blue-500/10 text-blue-400 border-blue-500/30',
                'name' => $r->name,
                'email' => $r->email,
                'phone' => $r->phone,
                'relationship' => $r->relationship ?? 'Renter',
                'roleOrTitle' => $isItemShortTerm ? 'Short-Term Rental Guest' : 'Long-Term Renter',
                'propertyLabel' => $unitLabel,
                'propertyId' => $r->property_id,
                'status' => $isRenterActive ? 'active' : 'expired',
                'startsAt' => $r->lease_start?->toIso8601String(),
                'expiresAt' => $r->lease_end?->toIso8601String(),
                'activationMethod' => 'rental_agreement',
                'approvalStatus' => $isRenterActive ? 'approved' : 'expired',
                'raw' => $r,
            ];

            if ($isItemShortTerm) {
                $shortTerm->push($item);
            } else {
                $longTerm->push($item);
            }
        }

        return collect([
            'long_term' => $longTerm,
            'short_term' => $shortTerm,
        ]);
    }

    /**
     * Helper to find the property a renter is renting from a homeowner.
     * Which of a homeowner's properties a renter rents: the one matching the
     * renter's lot, or the only one the homeowner has. An owner of several
     * with no lot to go on gives none, rather than a guess at the first.
     */
    private function rentedProperty(int $homeownerId, ?string $renterLot): ?Property
    {
        $owned = Property::where('owner_user_id', $homeownerId)->orderBy('id')->get();
        $bare = fn (?string $lot) => strtolower(preg_replace('/^(unit|lot|#)\s*/i', '', trim((string) $lot)));

        if ($renterLot !== null && $renterLot !== '') {
            return $owned->first(fn (Property $p) => $bare($p->lot_number) === $bare($renterLot));
        }

        return $owned->count() === 1 ? $owned->first() : null;
    }
}
