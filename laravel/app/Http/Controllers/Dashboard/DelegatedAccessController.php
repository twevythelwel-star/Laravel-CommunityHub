<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\AuthorizationType;
use App\Enums\PassCategory;
use App\Enums\PassStatus;
use App\Enums\UserRole;
use App\Enums\VisitorStatus;
use App\Http\Controllers\Controller;
use App\Models\DelegatedAccess;
use App\Models\EmergencyContinuityPlan;
use App\Models\GatePass;
use App\Models\Household;
use App\Models\Property;
use App\Models\Renter;
use App\Models\User;
use App\Models\Visitor;
use App\Services\AccessVisibilityService;
use App\Services\Delegation\DelegatedAccessService;
use App\Services\GatePassEngine;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DelegatedAccessController extends Controller
{
    public function __construct(
        protected GatePassEngine $engine
    ) {}

    /**
     * Display the Delegated Access Center ("Unit 14 — People & Access" or "Unit 14 — My Access Network").
     * Strict isolation invariant: A homeowner can only see people associated with their own unit.
     */
    public function index(Request $request): Response|JsonResponse
    {
        $user = $request->user();

        // Check if actor is Short-Term Guest, Long-Term Renter, or Homeowner
        $visibilityService = app(AccessVisibilityService::class);
        $roleName = $visibilityService->resolveUserRole($user);
        $viewAs = strtolower((string) ($request->query('view_as') ?? $request->input('view_as') ?? ''));
        $renterRecord = ($user->role !== UserRole::Homeowner) ? ($user->renter ?? $user->activeStay()) : null;

        $isShortTermRenter = false;
        if ($renterRecord) {
            $stay = strtolower((string) ($renterRecord->stay_type ?? ''));
            $notes = strtolower((string) ($renterRecord->notes ?? ''));
            if (str_contains($stay, 'short') || str_contains($stay, 'airbnb') || str_contains($notes, 'airbnb') || str_contains($stay, 'vacation')) {
                $isShortTermRenter = true;
            }
        }

        $isShortTerm = ($user->role !== UserRole::Homeowner || in_array($viewAs, ['short_term', 'airbnb', 'guest', 'vacation'], true)) && (
            in_array($viewAs, ['short_term', 'airbnb', 'guest', 'vacation'], true)
            || $roleName === 'Short-Term Rental / Airbnb Host/Guest'
            || $roleName === 'Short-Term Renter'
            || $isShortTermRenter
        );

        $isRenter = ! $isShortTerm && ($roleName === 'Long-Term Renter' || $viewAs === 'renter');
        $isHomeowner = ! $isRenter && ! $isShortTerm && ($user->role === UserRole::Homeowner);
        $isAdmin = $user->role->isAdministrative();

        // Determine dynamic property & unit label (e.g. Unit 14)
        $rawLot = $user->lot
            ?? $user->properties()->first()?->lot_number
            ?? $renterRecord?->lot;
        // No lot anywhere is no lot: it used to be "14", which then matched
        // whichever property is numbered 14.
        $lotNumber = $rawLot ? preg_replace('/^(unit|lot)\s+/i', '', trim($rawLot)) : null;
        $unitLabel = $lotNumber ? "Unit {$lotNumber}" : 'Your property';

        // The property whose staff and contractors this page lists. Only ever
        // one the user is tied to: their stay's, their own, or (for a renter)
        // the one of their homeowner's that they rent. Matching any property
        // by lot number showed a renter another household's people.
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
        $propertyId = $property?->id;

        if ($isShortTerm) {
            $pageTitle = "{$unitLabel} — My Stay";
            $pageSubtitle = "Your reservation details, active access pass, registered guests, and property services for {$unitLabel}.";
            $scopeBadge = 'Short-Term Guest Scope • Reservation Bounded';
        } elseif ($isRenter) {
            $pageTitle = "{$unitLabel} — My Access Network";
            $pageSubtitle = "Authorized occupants, visitors, staff, and contractors for your tenancy at {$unitLabel}.";
            $scopeBadge = 'Tenant Access Scope • Bounded by Lease';
        } else {
            $pageTitle = "{$unitLabel} — People & Access";
            $pageSubtitle = "Authorized occupants, guests, staff, contractors, and legacy delegates strictly associated with {$unitLabel}.";
            $scopeBadge = 'Zero Cross-Unit Leakage • Scoped';
        }

        // Query delegations:
        // Admin: all community delegations
        // Short-term / Long-term renter: their own granted delegations + staff and contractors authorized for their property
        // Homeowner: their own granted delegations
        if ($isAdmin) {
            $allDelegations = DelegatedAccess::with(['grantor', 'delegate', 'property', 'gatePasses'])->latest()->get();
        } elseif ($isShortTerm || $isRenter) {
            $allDelegations = DelegatedAccess::with(['grantor', 'delegate', 'property', 'gatePasses'])
                ->where(function ($query) use ($user, $propertyId) {
                    $query->where('grantor_user_id', $user->id);
                    if ($propertyId) {
                        $query->orWhere(function ($propQuery) use ($propertyId) {
                            $propQuery->where('property_id', $propertyId)
                                ->where(function ($sub) {
                                    $sub->whereIn('authorization_type', ['domestic_staff', 'caregiver', 'property_manager', 'contractor'])
                                        ->orWhereIn('access_level', ['Domestic Staff', 'Caregiver', 'Property Delegate', 'Contractor']);
                                });
                        });
                    }
                })
                ->latest()
                ->get();
        } else {
            $allDelegations = $user->delegatedAccessesGranted()->with(['delegate', 'property', 'gatePasses'])->latest()->get();
        }

        // 1. Residents / Occupants Card
        $userPass = $this->engine->issuePassFor($user);
        if ($isRenter) {
            $residentsList = collect([
                [
                    'id' => 'user-'.$user->id,
                    'sourceType' => 'user',
                    'sourceId' => $user->id,
                    'categoryKey' => 'residents',
                    'categoryLabel' => 'Primary Tenant (Leaseholder)',
                    'categoryBadgeColor' => 'bg-indigo-500/10 text-indigo-400 border-indigo-500/30',
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone ?? null,
                    'relationship' => 'Primary Leaseholder',
                    'roleOrTitle' => 'Long-Term Renter',
                    'propertyLabel' => $unitLabel,
                    'propertyId' => null,
                    'status' => 'active',
                    'startsAt' => $renterRecord?->lease_start?->toIso8601String() ?? $user->created_at?->toIso8601String(),
                    'expiresAt' => $renterRecord?->lease_end?->toIso8601String(),
                    'activationMethod' => 'lease_clearance',
                    'approvalStatus' => 'approved',
                    'permissions' => [
                        'gate_access' => 'Digital Gate Pass & Scanner Clearance',
                        'pool_access' => 'Estate Pool & Clubhouse Access',
                        'gym_access' => 'Fitness Center Access',
                        'parking_allocated' => 'Tenant Allocated Parking',
                        'visitor_authorization' => 'Authorize Visitors (Within Lease Limits)',
                    ],
                    'accessRules' => [
                        'gate_access' => true,
                        'pool_access' => true,
                        'gym_access' => true,
                        'clubhouse_access' => true,
                        'parking_allocated' => true,
                        'can_invite_visitors' => true,
                        'requires_id_verification' => false,
                        'allowed_days' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                        'entry_start_time' => '00:00',
                        'entry_end_time' => '23:59',
                    ],
                    'credential' => [
                        'passId' => $userPass->pass_id,
                        'category' => 'RENTER',
                        'categoryCode' => 'GP-RNT',
                        'shape' => 'Squircle',
                        'status' => $userPass->status->value,
                        'isActive' => $userPass->isActive(),
                        'validFrom' => $userPass->valid_from?->toIso8601String(),
                        'validUntil' => $renterRecord?->lease_end?->toIso8601String() ?? $userPass->valid_until?->toIso8601String(),
                    ],
                    'raw' => $user,
                ],
            ]);
        } else {
            $residentsList = collect([
                [
                    'id' => 'user-'.$user->id,
                    'sourceType' => 'user',
                    'sourceId' => $user->id,
                    'categoryKey' => 'residents',
                    'categoryLabel' => 'Resident (Owner)',
                    'categoryBadgeColor' => 'bg-emerald-500/10 text-emerald-500 border-emerald-500/30',
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone ?? null,
                    'relationship' => 'Primary Homeowner',
                    'roleOrTitle' => 'Homeowner / Resident',
                    'propertyLabel' => $unitLabel,
                    'propertyId' => $user->properties()->first()?->id,
                    'status' => 'active',
                    'startsAt' => $user->created_at?->toIso8601String(),
                    'expiresAt' => null,
                    'activationMethod' => 'immediate',
                    'approvalStatus' => 'approved',
                    'permissions' => [
                        'gate_access' => 'Digital Gate Pass & Scanner Clearance',
                        'pool_access' => 'Estate Pool & Clubhouse Access',
                        'gym_access' => 'Fitness Center Access',
                        'parking_allocated' => 'Resident Allocated Parking',
                        'visitor_authorization' => 'Authorize Visitors & Pre-Clear Guests',
                        'emergency_communications' => 'Receive & Broadcast Emergency Alerts',
                        'property_maintenance' => 'Submit & Approve Maintenance Work',
                    ],
                    'accessRules' => [
                        'gate_access' => true,
                        'pool_access' => true,
                        'gym_access' => true,
                        'clubhouse_access' => true,
                        'parking_allocated' => true,
                        'can_invite_visitors' => true,
                        'requires_id_verification' => false,
                        'allowed_days' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                        'entry_start_time' => '00:00',
                        'entry_end_time' => '23:59',
                    ],
                    'credential' => [
                        'passId' => $userPass->pass_id,
                        'category' => $userPass->category->value,
                        'categoryCode' => $userPass->category->passIdPrefix(),
                        'shape' => $userPass->category->shape()->value,
                        'status' => $userPass->status->value,
                        'isActive' => $userPass->isActive(),
                        'validFrom' => $userPass->valid_from?->toIso8601String(),
                        'validUntil' => $userPass->valid_until?->toIso8601String(),
                    ],
                    'raw' => $user,
                ],
            ]);
        }

        // Former occupants collection
        $formerOccupantsList = collect();

        // Query Household and Household members strictly for this actor
        $households = Household::where('primary_homeowner_id', $user->id)
            ->with(['members.gatePass'])
            ->get();

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
                    $residentsList->push($memberItem);
                } else {
                    $formerOccupantsList->push($memberItem);
                }
            }
        }

        // Active Renters & Short-Term Rentals under this homeowner
        $longTermRentersList = collect();
        $shortTermRentalsList = collect();

        $renters = Renter::where('homeowner_id', $user->id)->get();
        foreach ($renters as $r) {
            $isRenterActive = ($r->status === 'Active') && (! $r->lease_end || ! $r->lease_end->isPast());
            $isItemShortTerm = (stripos($r->stay_type ?? '', 'short') !== false)
                || (stripos($r->stay_type ?? '', 'airbnb') !== false)
                || (stripos($r->notes ?? '', 'airbnb') !== false);

            $renterPass = null;
            if ($r->user_id) {
                $renterUser = User::find($r->user_id);
                if ($renterUser) {
                    $renterPass = $this->engine->issuePassFor($renterUser);
                }
            }

            $catCode = $isItemShortTerm ? 'GP-AIR' : 'GP-RNT';
            $shape = $isItemShortTerm ? 'RoundedSquare' : 'Squircle';
            $badgeColor = $isItemShortTerm
                ? 'bg-pink-500/10 text-pink-400 border-pink-500/30'
                : 'bg-indigo-500/10 text-indigo-400 border-indigo-500/30';

            $renterItem = [
                'id' => 'renter-'.$r->id,
                'sourceType' => 'renter',
                'sourceId' => $r->id,
                'categoryKey' => ! $isRenterActive ? 'former_occupants' : ($isItemShortTerm ? 'short_term_rentals' : 'long_term_occupants'),
                'categoryLabel' => ! $isRenterActive ? 'Former Resident' : ($isItemShortTerm ? 'Short-Term Rental (Airbnb)' : 'Authorized Long-Term Occupant'),
                'categoryBadgeColor' => ! $isRenterActive ? 'bg-zinc-500/10 text-zinc-400 border-zinc-500/30' : $badgeColor,
                'name' => $r->name,
                'email' => $r->contact && filter_var($r->contact, FILTER_VALIDATE_EMAIL) ? $r->contact : null,
                'phone' => $r->contact && ! filter_var($r->contact, FILTER_VALIDATE_EMAIL) ? $r->contact : null,
                'relationship' => $r->stay_type ?? ($isItemShortTerm ? 'Airbnb Guest' : 'Lease Tenant'),
                'roleOrTitle' => $isItemShortTerm ? 'Active Airbnb / Short-Term Occupant' : 'Authorized Long-Term Occupant',
                'propertyLabel' => collect([$r->lot, $r->street])->filter()->implode(', ') ?: $unitLabel,
                'propertyId' => null,
                'status' => $isRenterActive ? 'active' : 'expired',
                'startsAt' => $r->lease_start?->toIso8601String(),
                'expiresAt' => $r->lease_end?->toIso8601String(),
                'activationMethod' => $isItemShortTerm ? 'rental_reservation' : 'lease_agreement',
                'approvalStatus' => 'approved',
                'permissions' => [
                    'gate_access' => 'Digital Gate Pass & Scanner Clearance',
                    'pool_access' => 'Estate Pool & Clubhouse Access',
                    'gym_access' => 'Fitness Center Access',
                ],
                'accessRules' => [
                    'gate_access' => true,
                    'pool_access' => true,
                    'gym_access' => true,
                    'allowed_days' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                    'entry_start_time' => '00:00',
                    'entry_end_time' => '23:59',
                ],
                'credential' => $renterPass ? [
                    'passId' => $renterPass->pass_id,
                    'category' => $renterPass->category->value,
                    'categoryCode' => $renterPass->category->passIdPrefix(),
                    'shape' => $renterPass->category->shape()->value,
                    'status' => $renterPass->status->value,
                    'isActive' => $renterPass->isActive() && $isRenterActive,
                    'validFrom' => $renterPass->valid_from?->toIso8601String(),
                    'validUntil' => $renterPass->valid_until?->toIso8601String(),
                ] : [
                    'passId' => sprintf('%s-%d', $catCode, $r->id),
                    'category' => $isItemShortTerm ? 'SHORT_TERM_OCCUPANT' : 'RENTER',
                    'categoryCode' => $catCode,
                    'shape' => $shape,
                    'status' => $isRenterActive ? 'ACTIVE' : 'EXPIRED',
                    'isActive' => $isRenterActive,
                    'validFrom' => $r->lease_start?->toIso8601String(),
                    'validUntil' => $r->lease_end?->toIso8601String(),
                ],
                'raw' => $r,
            ];

            if (! $isRenterActive) {
                $formerOccupantsList->push($renterItem);
            } elseif ($isItemShortTerm) {
                $shortTermRentalsList->push($renterItem);
            } else {
                $longTermRentersList->push($renterItem);
            }
        }

        // Active & Historical Visitors strictly for this homeowner
        $visitors = Visitor::where('homeowner_id', $user->id)->with('gatePass')->latest()->get();

        // Categorize Delegations into groups
        $longTermOccupantsList = collect()->concat($longTermRentersList);
        $legacyContactsList = collect();
        $familyMembersList = collect();
        $caregiversList = collect();
        $propertyManagersList = collect();
        $domesticStaffList = collect();
        $contractorsList = collect();
        $visitorsList = collect();

        foreach ($allDelegations as $d) {
            $isFormer = $d->status === 'revoked' || $d->isExpired() || ($d->expires_at && $d->expires_at->isPast());
            $pass = $d->gatePasses->first();
            if (! $pass && $d->hasPermission('gate_access') && $d->status === 'active' && ! $isFormer) {
                try {
                    $pass = $d->issueDelegateGatePass($this->engine, $user);
                } catch (\Throwable $e) {
                    // Ignore pass issuance failure in read view
                }
            }

            $catCode = match (true) {
                $d->access_level === 'Long-Term Occupant' || $d->authorization_type === 'long_term_occupant' => 'GP-LTO',
                $d->access_level === 'Domestic Staff' || $d->authorization_type === 'domestic_staff' => 'GP-HST',
                default => 'GP-DEL',
            };

            $shape = match ($catCode) {
                'GP-LTO' => 'RoundedSquare',
                'GP-HST' => 'HouseHex',
                default => 'Octagon',
            };

            $badgeColor = match ($catCode) {
                'GP-LTO' => 'bg-indigo-500/10 text-indigo-400 border-indigo-500/30',
                'GP-HST' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30',
                default => 'bg-amber-500/10 text-amber-400 border-amber-500/30',
            };

            $personItem = [
                'id' => 'del-'.$d->id,
                'sourceType' => 'delegation',
                'sourceId' => $d->id,
                'categoryKey' => '', // Set below
                'categoryLabel' => $d->access_level,
                'categoryBadgeColor' => $badgeColor,
                'name' => $d->name,
                'email' => $d->email,
                'phone' => $d->phone,
                'relationship' => $d->relationship,
                'roleOrTitle' => $d->access_level,
                'propertyLabel' => $d->property ? "Lot {$d->property->lot_number}" : $unitLabel,
                'propertyId' => $d->property_id,
                'status' => $d->status,
                'isEmergencyActive' => (bool) $d->is_emergency_active,
                'startsAt' => $d->starts_at?->toIso8601String(),
                'expiresAt' => $d->expires_at?->toIso8601String(),
                'durationType' => $d->duration_type ?? 'custom',
                'durationLabel' => $d->durationLabel(),
                'activationMethod' => $d->activation_method,
                'approvalStatus' => $d->approval_status,
                'permissions' => $d->permissions ?? [],
                'accessRules' => $d->access_rules ?? [],
                'credential' => $pass ? [
                    'passId' => $pass->pass_id,
                    'category' => $pass->category->value,
                    'categoryCode' => $catCode,
                    'shape' => $shape,
                    'status' => $pass->status->value,
                    'isActive' => $pass->isActive(),
                    'validFrom' => $pass->valid_from?->toIso8601String(),
                    'validUntil' => $pass->valid_until?->toIso8601String(),
                ] : [
                    'passId' => sprintf('%s-%d-REG', $catCode, $d->id),
                    'category' => $catCode === 'GP-LTO' ? 'LONG_TERM_OCCUPANT' : ($catCode === 'GP-HST' ? 'HOMEOWNER_STAFF' : 'DELEGATE'),
                    'categoryCode' => $catCode,
                    'shape' => $shape,
                    'status' => $d->status === 'active' ? 'ACTIVE' : 'INACTIVE',
                    'isActive' => $d->status === 'active' && ! $isFormer,
                    'validFrom' => $d->starts_at?->toIso8601String(),
                    'validUntil' => $d->expires_at?->toIso8601String(),
                ],
                'raw' => $d,
            ];

            if ($isFormer) {
                $personItem['categoryKey'] = 'former_occupants';
                $personItem['categoryLabel'] = 'Former Delegate / Occupant';
                $personItem['categoryBadgeColor'] = 'bg-rose-500/10 text-rose-400 border-rose-500/30';
                $formerOccupantsList->push($personItem);

                continue;
            }

            // Security Invariant for Short-Term Guest & Long-Term Renter:
            // Short-Term Guest sees ONLY Staff or Contractors authorized for the property (Property Services) - read only.
            // Homeowner legacy contacts, family members, or other residents MUST NEVER leak to short-term guests!
            if ($isShortTerm) {
                $isStaffOrContractor = in_array($d->authorization_type, ['domestic_staff', 'caregiver', 'property_manager', 'contractor'])
                    || in_array($d->access_level, ['Domestic Staff', 'Caregiver', 'Property Delegate', 'Contractor']);
                if (! $isStaffOrContractor) {
                    continue;
                }
                $personItem['canManage'] = false;
                $personItem['propertyLabel'] = "{$unitLabel} • Authorized for Property";
            } elseif ($isRenter && $d->grantor_user_id !== $user->id) {
                $isStaffOrContractor = in_array($d->authorization_type, ['domestic_staff', 'caregiver', 'property_manager', 'contractor'])
                    || in_array($d->access_level, ['Domestic Staff', 'Caregiver', 'Property Delegate', 'Contractor']);
                if (! $isStaffOrContractor) {
                    continue;
                }
                $personItem['canManage'] = false;
                $personItem['propertyLabel'] = "{$unitLabel} • Authorized for Property";
            } else {
                $personItem['canManage'] = true;
            }

            // Check if Short-Term Rental delegation
            $isShortTermDel = (stripos($d->authorization_type ?? '', 'short') !== false)
                || (stripos($d->access_level ?? '', 'short') !== false)
                || (stripos($d->relationship ?? '', 'airbnb') !== false);

            if ($isShortTermDel && ! $isRenter && ! $isShortTerm) {
                $personItem['categoryKey'] = 'short_term_rentals';
                $personItem['categoryLabel'] = 'Short-Term Rental (Guest)';
                $personItem['categoryBadgeColor'] = 'bg-pink-500/10 text-pink-400 border-pink-500/30';
                $shortTermRentalsList->push($personItem);
            } elseif ($d->authorization_type === 'long_term_occupant' || $d->access_level === 'Long-Term Occupant') {
                if (! $isShortTerm) {
                    $personItem['categoryKey'] = 'long_term_occupants';
                    $longTermOccupantsList->push($personItem);
                }
            } elseif ($d->authorization_type === 'caregiver' || $d->access_level === 'Caregiver') {
                $personItem['categoryKey'] = 'caregivers';
                $caregiversList->push($personItem);
            } elseif ($d->authorization_type === 'property_manager' || $d->access_level === 'Property Delegate') {
                $personItem['categoryKey'] = 'property_managers';
                $propertyManagersList->push($personItem);
            } elseif ($d->authorization_type === 'domestic_staff' || $d->access_level === 'Domestic Staff') {
                $personItem['categoryKey'] = 'domestic_staff';
                $domesticStaffList->push($personItem);
            } elseif ($d->authorization_type === 'legacy_contact' || in_array($d->access_level, ['Emergency Contact', 'Legacy Delegate', 'Emergency Delegate', 'Limited Delegate'])) {
                if (! $isShortTerm) {
                    $personItem['categoryKey'] = 'legacy_contacts';
                    $legacyContactsList->push($personItem);
                }
            } elseif ($d->authorization_type === 'family_member' || in_array($d->relationship, ['Family member', 'Family/Friend', 'Child', 'Spouse', 'Parent'])) {
                if (! $isRenter && ! $isShortTerm) {
                    $personItem['categoryKey'] = 'family_members';
                    $familyMembersList->push($personItem);
                } elseif ($isRenter) {
                    $personItem['categoryKey'] = 'legacy_contacts';
                    $legacyContactsList->push($personItem);
                }
            } elseif ($d->authorization_type === 'contractor' || $d->access_level === 'Contractor') {
                $personItem['categoryKey'] = 'contractors';
                $contractorsList->push($personItem);
            } else {
                if (! $isShortTerm) {
                    $personItem['categoryKey'] = 'legacy_contacts';
                    $legacyContactsList->push($personItem);
                }
            }
        }

        // Process Visitors & Contractors
        foreach ($visitors as $v) {
            $isFormer = $v->is_blocked || in_array($v->status->value, ['CheckedOut', 'Expired']) || ($v->expired_at !== null);
            $vPass = $v->gatePass;
            $isContractor = ($v->type === 'Contractor') || ($vPass?->category === PassCategory::Contractor);
            $catCode = $isContractor ? 'GP-CON' : 'GP-VIS';
            $shape = $isContractor ? 'Pentagon' : 'Circle';
            $badgeColor = $isContractor
                ? 'bg-amber-500/10 text-amber-400 border-amber-500/30'
                : 'bg-teal-500/10 text-teal-400 border-teal-500/30';

            $visitorItem = [
                'id' => 'vis-'.$v->id,
                'sourceType' => 'visitor',
                'sourceId' => $v->id,
                'categoryKey' => $isFormer ? 'former_occupants' : ($isContractor ? 'contractors' : 'visitors'),
                'categoryLabel' => $isFormer ? 'Former Guest / Worker' : ($isContractor ? 'Contractor / Vendor' : 'Visitor / Guest'),
                'categoryBadgeColor' => $isFormer ? 'bg-zinc-500/10 text-zinc-400 border-zinc-500/30' : $badgeColor,
                'name' => $v->name,
                'email' => $v->contact && filter_var($v->contact, FILTER_VALIDATE_EMAIL) ? $v->contact : null,
                'phone' => $v->contact && ! filter_var($v->contact, FILTER_VALIDATE_EMAIL) ? $v->contact : null,
                'relationship' => $isContractor ? 'Contractor / Maintenance' : 'Visitor / Social Guest',
                'roleOrTitle' => $isContractor ? 'Service Contractor' : 'Visitor',
                'propertyLabel' => $unitLabel,
                'propertyId' => null,
                'status' => $v->is_blocked ? 'revoked' : strtolower($v->status->value),
                'startsAt' => $v->expected_at?->toIso8601String(),
                'expiresAt' => $v->expired_at?->toIso8601String() ?? $vPass?->valid_until?->toIso8601String(),
                'activationMethod' => 'guest_clearance',
                'approvalStatus' => $v->status === VisitorStatus::Expected ? 'approved' : 'checked_in',
                'permissions' => [
                    'gate_access' => 'Digital Gate Pass & Scanner Clearance',
                ],
                'accessRules' => [
                    'gate_access' => true,
                    'requires_id_verification' => filled($v->id_number) || filled($v->id_type),
                    'allowed_days' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                    'entry_start_time' => '06:00',
                    'entry_end_time' => '22:00',
                ],
                'credential' => $vPass ? [
                    'passId' => $vPass->pass_id,
                    'category' => $vPass->category->value,
                    'categoryCode' => $catCode,
                    'shape' => $shape,
                    'status' => $vPass->status->value,
                    'isActive' => $vPass->isActive() && ! $isFormer,
                    'validFrom' => $vPass->valid_from?->toIso8601String(),
                    'validUntil' => $vPass->valid_until?->toIso8601String(),
                ] : [
                    'passId' => sprintf('%s-%d', $catCode, $v->id),
                    'category' => $isContractor ? 'CONTRACTOR' : 'VISITOR',
                    'categoryCode' => $catCode,
                    'shape' => $shape,
                    'status' => $isFormer ? 'EXPIRED' : 'ACTIVE',
                    'isActive' => ! $isFormer,
                    'validFrom' => $v->expected_at?->toIso8601String(),
                    'validUntil' => $v->expired_at?->toIso8601String(),
                ],
                'raw' => $v,
            ];

            if ($isFormer) {
                $formerOccupantsList->push($visitorItem);
            } elseif ($isContractor) {
                $contractorsList->push($visitorItem);
            } else {
                $visitorsList->push($visitorItem);
            }
        }

        // Unified Staff collection (Domestic Staff + Caregivers + Property Managers)
        $staffList = collect()
            ->concat($domesticStaffList)
            ->concat($caregiversList)
            ->concat($propertyManagersList)
            ->values();

        // All people unified
        if ($isShortTerm) {
            $checkoutDateFormatted = $renterRecord?->lease_end ? $renterRecord->lease_end->format('M j, Y') : now()->addDays(4)->format('M j, Y');
            $checkinDateFormatted = $renterRecord?->lease_start ? $renterRecord->lease_start->format('M j, Y') : now()->format('M j, Y');
            $hostName = $property?->owner?->name ?? $renterRecord?->homeowner?->name ?? 'Property Host';

            // 1. My Stay (Reservation & Property)
            $stayItem = [
                'id' => 'stay-'.$user->id,
                'sourceType' => 'renter',
                'sourceId' => $renterRecord?->id ?? $user->id,
                'categoryKey' => 'my_stay',
                'categoryLabel' => 'Active Reservation',
                'categoryBadgeColor' => 'bg-pink-500/10 text-pink-400 border-pink-500/30',
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone ?? null,
                'relationship' => 'Primary Guest (Reservation Lead)',
                'roleOrTitle' => 'Airbnb Guest',
                'propertyLabel' => $unitLabel,
                'propertyId' => $propertyId,
                'status' => 'active',
                'startsAt' => $renterRecord?->lease_start?->toIso8601String() ?? now()->toIso8601String(),
                'expiresAt' => $renterRecord?->lease_end?->toIso8601String() ?? now()->addDays(4)->toIso8601String(),
                'activationMethod' => 'reservation_checkin',
                'approvalStatus' => 'approved',
                'permissions' => [
                    'property' => $unitLabel,
                    'reservation_dates' => "{$checkinDateFormatted} – {$checkoutDateFormatted}",
                    'host' => $hostName,
                    'stay_type' => 'Short-Term Rental / Airbnb',
                    'gate_clearance' => 'Main Gate Ingress Authorized',
                ],
                'accessRules' => [
                    'gate_access' => true,
                    'pool_access' => true,
                    'gym_access' => true,
                    'entry_start_time' => '00:00',
                    'entry_end_time' => '23:59',
                ],
                'credential' => [
                    'passId' => $userPass->pass_id,
                    'category' => 'SHORT_TERM_OCCUPANT',
                    'categoryCode' => 'GP-AIR',
                    'shape' => 'RoundedSquare',
                    'status' => 'ACTIVE',
                    'isActive' => true,
                    'validFrom' => $renterRecord?->lease_start?->toIso8601String() ?? now()->toIso8601String(),
                    'validUntil' => $renterRecord?->lease_end?->toIso8601String() ?? now()->addDays(4)->toIso8601String(),
                ],
                'canManage' => false,
                'raw' => $renterRecord,
            ];
            $myStayList = collect([$stayItem]);

            // 2. My Access (My QR, Gate access, Validity period)
            $myAccessItem = [
                'id' => 'access-'.$userPass->pass_id,
                'sourceType' => 'user',
                'sourceId' => $user->id,
                'categoryKey' => 'my_access',
                'categoryLabel' => 'Digital Gate Pass',
                'categoryBadgeColor' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30',
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone ?? null,
                'relationship' => 'Gate Clearance',
                'roleOrTitle' => 'Verified Ingress Credential',
                'propertyLabel' => $unitLabel,
                'propertyId' => $propertyId,
                'status' => 'active',
                'startsAt' => $renterRecord?->lease_start?->toIso8601String() ?? now()->toIso8601String(),
                'expiresAt' => $renterRecord?->lease_end?->toIso8601String() ?? now()->addDays(4)->toIso8601String(),
                'activationMethod' => 'gate_pass_clearance',
                'approvalStatus' => 'approved',
                'permissions' => [
                    'qr_credential' => 'Active Dynamic QR (Anti-Replay / 60s Token)',
                    'gate_access' => 'Main Gate & Guest Ingress Cleared',
                    'validity_period' => "{$checkinDateFormatted} – {$checkoutDateFormatted}",
                ],
                'accessRules' => [
                    'gate_access' => true,
                    'allowed_days' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                    'entry_start_time' => '00:00',
                    'entry_end_time' => '23:59',
                ],
                'credential' => [
                    'passId' => $userPass->pass_id,
                    'category' => 'SHORT_TERM_OCCUPANT',
                    'categoryCode' => 'GP-AIR',
                    'shape' => 'RoundedSquare',
                    'status' => 'ACTIVE',
                    'isActive' => true,
                    'validFrom' => $renterRecord?->lease_start?->toIso8601String() ?? now()->toIso8601String(),
                    'validUntil' => $renterRecord?->lease_end?->toIso8601String() ?? now()->addDays(4)->toIso8601String(),
                ],
                'canManage' => false,
                'raw' => $userPass,
            ];
            $myAccessList = collect([$myAccessItem]);

            // 3. Property Services (Authorized staff/contractors for Unit 14)
            $propertyServicesList = collect()
                ->concat($staffList)
                ->concat($contractorsList)
                ->values()
                ->map(function ($item) {
                    $item['categoryKey'] = 'property_services';
                    $item['categoryLabel'] = 'Authorized Property Service';
                    $item['categoryBadgeColor'] = 'bg-amber-500/10 text-amber-400 border-amber-500/30';
                    $item['canManage'] = false; // Strictly read-only for short-term guests!

                    return $item;
                });

            // 4. Unified All People for Short-Term Guest
            $allPeople = collect()
                ->concat($myStayList)
                ->concat($visitorsList)
                ->concat($myAccessList)
                ->concat($propertyServicesList)
                ->unique('id')
                ->values();

            $categoryCounts = [
                'all' => $allPeople->count(),
                'my_stay' => $myStayList->count(),
                'my_visitors' => $visitorsList->count(),
                'my_access' => $myAccessList->count(),
                'property_services' => $propertyServicesList->count(),
                'residents' => 0,
                'long_term_occupants' => 0,
                'short_term_rentals' => 0,
                'legacy_contacts' => 0,
                'family_members' => 0,
                'caregivers' => 0,
                'property_managers' => 0,
                'domestic_staff' => 0,
                'staff' => 0,
                'contractors' => 0,
                'visitors' => $visitorsList->count(),
                'former_occupants' => 0,
            ];

            $sections = [
                [
                    'key' => 'my_stay',
                    'title' => 'My Stay',
                    'subtitle' => "Property: {$unitLabel}",
                    'description' => "Reservation details, host contact, and check-in/check-out clearance for {$unitLabel}",
                    'count' => $myStayList->count(),
                    'items' => $myStayList,
                    'emptyMessage' => "No reservation record found for {$unitLabel}.",
                    'actionLabel' => '',
                    'actionCategory' => '',
                ],
                [
                    'key' => 'my_visitors',
                    'title' => 'My Visitors',
                    'subtitle' => "Guests registered for your stay at {$unitLabel}",
                    'description' => "Authorized visitors and reservation guests registered for your stay at {$unitLabel}",
                    'count' => $visitorsList->count(),
                    'items' => $visitorsList,
                    'emptyMessage' => "No additional visitors currently registered for your stay at {$unitLabel}.",
                    'actionLabel' => 'Pre-Clear Visitor',
                    'actionCategory' => 'visitors',
                ],
                [
                    'key' => 'my_access',
                    'title' => 'My Access',
                    'subtitle' => 'Digital Gate Pass & Scanner Clearance',
                    'description' => 'Your verified QR badge, gate ingress clearance, and stay validity window',
                    'count' => $myAccessList->count(),
                    'items' => $myAccessList,
                    'emptyMessage' => "No active gate credential issued for {$unitLabel}.",
                    'actionLabel' => '',
                    'actionCategory' => '',
                ],
                [
                    'key' => 'property_services',
                    'title' => 'Property Services',
                    'subtitle' => "Authorized staff & contractors for {$unitLabel}",
                    'description' => "Authorized maintenance, housekeeping, and emergency property contacts for {$unitLabel}",
                    'count' => $propertyServicesList->count(),
                    'items' => $propertyServicesList,
                    'emptyMessage' => 'No external property services scheduled during your stay.',
                    'actionLabel' => '',
                    'actionCategory' => '',
                ],
            ];
        } else {
            $myStayList = collect();
            $myAccessList = collect();
            $propertyServicesList = collect();

            $allPeople = collect()
                ->concat($residentsList)
                ->concat($longTermOccupantsList)
                ->concat($shortTermRentalsList)
                ->concat($legacyContactsList)
                ->concat($familyMembersList)
                ->concat($caregiversList)
                ->concat($propertyManagersList)
                ->concat($domesticStaffList)
                ->concat($contractorsList)
                ->concat($visitorsList)
                ->concat($formerOccupantsList)
                ->unique('id')
                ->values();

            $categoryCounts = [
                'all' => $allPeople->count(),
                'residents' => $residentsList->count(),
                'long_term_occupants' => $longTermOccupantsList->count(),
                'short_term_rentals' => $shortTermRentalsList->count(),
                'legacy_contacts' => $legacyContactsList->count(),
                'family_members' => $familyMembersList->count(),
                'caregivers' => $caregiversList->count(),
                'property_managers' => $propertyManagersList->count(),
                'domestic_staff' => $domesticStaffList->count(),
                'staff' => $staffList->count(),
                'contractors' => $contractorsList->count(),
                'visitors' => $visitorsList->count(),
                'former_occupants' => $formerOccupantsList->count(),
            ];

            // Sections Configuration: 5 Narrower Sections for Long-Term Renter, 7 for Homeowner
            if ($isRenter) {
                $sections = [
                    [
                        'key' => 'residents',
                        'title' => 'Household & Occupants',
                        'subtitle' => 'Authorized co-occupants & lease members',
                        'description' => "Verified co-occupants and household members residing under your lease in {$unitLabel}",
                        'count' => $residentsList->count(),
                        'items' => $residentsList,
                        'emptyMessage' => "No additional household members or authorized co-occupants registered for {$unitLabel}.",
                        'actionLabel' => 'Add Co-Occupant',
                        'actionCategory' => 'long_term_occupants',
                    ],
                    [
                        'key' => 'visitors',
                        'title' => 'Visitors',
                        'subtitle' => "Visitors authorized for {$unitLabel}",
                        'description' => 'Current and scheduled guests, deliveries, and pre-cleared visitors invited by you',
                        'count' => $visitorsList->count(),
                        'items' => $visitorsList,
                        'emptyMessage' => "No current or scheduled visitors for {$unitLabel}.",
                        'actionLabel' => 'Pre-Clear Visitor',
                        'actionCategory' => 'visitors',
                    ],
                    [
                        'key' => 'staff',
                        'title' => 'Staff',
                        'subtitle' => 'Staff authorized for your property',
                        'description' => 'Domestic workers, cleaners, and service providers authorized for your unit',
                        'count' => $staffList->count(),
                        'items' => $staffList,
                        'emptyMessage' => 'No dedicated staff currently authorized for your unit.',
                        'actionLabel' => 'Authorize Staff Member',
                        'actionCategory' => 'domestic_staff',
                    ],
                    [
                        'key' => 'contractors',
                        'title' => 'Contractors',
                        'subtitle' => 'Contractors authorized for your property',
                        'description' => "Authorized maintenance contractors, electricians, and technicians for {$unitLabel}",
                        'count' => $contractorsList->count(),
                        'items' => $contractorsList,
                        'emptyMessage' => "No active contractors currently cleared for {$unitLabel}.",
                        'actionLabel' => 'Authorize Contractor',
                        'actionCategory' => 'contractors',
                    ],
                    [
                        'key' => 'legacy_contacts',
                        'title' => 'Legacy Contacts',
                        'subtitle' => 'Your emergency & continuity delegates',
                        'description' => "Designated personal emergency contacts and trusted delegates authorized for your tenancy in {$unitLabel}",
                        'count' => $legacyContactsList->count(),
                        'items' => $legacyContactsList,
                        'emptyMessage' => 'No emergency contacts configured for your lease.',
                        'actionLabel' => 'Add Emergency Contact',
                        'actionCategory' => 'legacy_contacts',
                    ],
                ];
            } else {
                $sections = [
                    [
                        'key' => 'residents',
                        'title' => 'Residents',
                        'subtitle' => 'Homeowner(s) & Household members',
                        'description' => "Primary homeowners and verified household residents living in {$unitLabel}",
                        'count' => $residentsList->count(),
                        'items' => $residentsList,
                        'emptyMessage' => "No household members registered yet for {$unitLabel}.",
                        'actionLabel' => 'Add Household Member',
                        'actionCategory' => 'residents',
                    ],
                    [
                        'key' => 'long_term_occupants',
                        'title' => 'Long-Term Guests / Renters',
                        'subtitle' => 'Authorized long-term occupants',
                        'description' => "Authorized extended occupants and long-term lease tenants residing in {$unitLabel}",
                        'count' => $longTermOccupantsList->count(),
                        'items' => $longTermOccupantsList,
                        'emptyMessage' => "No active long-term occupants or lease tenants registered for {$unitLabel}.",
                        'actionLabel' => 'Add Long-Term Occupant',
                        'actionCategory' => 'long_term_occupants',
                    ],
                    [
                        'key' => 'short_term_rentals',
                        'title' => 'Short-Term Rental',
                        'subtitle' => 'Active Airbnb/short-term occupants, if applicable',
                        'description' => "Active Airbnb reservations and temporary short-term occupants authorized for {$unitLabel}",
                        'count' => $shortTermRentalsList->count(),
                        'items' => $shortTermRentalsList,
                        'emptyMessage' => "No active Airbnb or short-term occupants currently staying at {$unitLabel}.",
                        'actionLabel' => 'Register Short-Term Guest',
                        'actionCategory' => 'short_term_rentals',
                    ],
                    [
                        'key' => 'visitors',
                        'title' => 'Visitors',
                        'subtitle' => "Current and upcoming visitors associated with {$unitLabel}",
                        'description' => "Pre-cleared day visitors, deliveries, and expected guests authorized for {$unitLabel}",
                        'count' => $visitorsList->count(),
                        'items' => $visitorsList,
                        'emptyMessage' => "No current or upcoming visitors scheduled for {$unitLabel}.",
                        'actionLabel' => 'Pre-Clear Visitor',
                        'actionCategory' => 'visitors',
                    ],
                    [
                        'key' => 'staff',
                        'title' => 'Staff',
                        'subtitle' => "Staff authorized for {$unitLabel}",
                        'description' => "Domestic staff, caregivers, housekeepers, gardeners, drivers, and property managers authorized for {$unitLabel}",
                        'count' => $staffList->count(),
                        'items' => $staffList,
                        'emptyMessage' => "No dedicated household staff currently authorized for {$unitLabel}.",
                        'actionLabel' => 'Authorize Staff Member',
                        'actionCategory' => 'domestic_staff',
                    ],
                    [
                        'key' => 'contractors',
                        'title' => 'Contractors',
                        'subtitle' => "Contractors authorized for {$unitLabel}",
                        'description' => "Authorized maintenance contractors, electricians, technicians, and repair personnel for {$unitLabel}",
                        'count' => $contractorsList->count(),
                        'items' => $contractorsList,
                        'emptyMessage' => "No active contractors currently cleared for {$unitLabel}.",
                        'actionLabel' => 'Authorize Contractor',
                        'actionCategory' => 'contractors',
                    ],
                    [
                        'key' => 'legacy_contacts',
                        'title' => 'Legacy Contacts',
                        'subtitle' => "Active legacy/emergency delegates for {$unitLabel}",
                        'description' => "Designated emergency contacts, attorneys, and succession delegates authorized for {$unitLabel}",
                        'count' => $legacyContactsList->count(),
                        'items' => $legacyContactsList,
                        'emptyMessage' => "No active legacy contacts or emergency delegates configured for {$unitLabel}.",
                        'actionLabel' => 'Add Legacy Contact',
                        'actionCategory' => 'legacy_contacts',
                    ],
                ];
            }
        }

        // Granular Capability & Visibility Permission Model
        $permissions = [
            'canSee' => [
                'ownHousehold' => ! $isShortTerm,
                'ownVisitors' => true,
                'propertyStaff' => true,
                'propertyContractors' => true,
                'ownLegacyContacts' => ! $isShortTerm,
                'otherProperties' => false,
                'entireCommunity' => $isAdmin || $user->role->isSecurity(),
                'homeownerFinancials' => ! $isRenter && ! $isShortTerm && ($isHomeowner || $isAdmin),
                'shortTermRentals' => ! $isRenter && ! $isShortTerm && ($isHomeowner || $isAdmin),
                'propertyOwnership' => ! $isRenter && ! $isShortTerm && ($isHomeowner || $isAdmin),
            ],
            'canAct' => [
                'removeHomeowner' => false, // strictly false for renters and short-term guests
                'changePropertyOwnership' => false, // strictly false for renters and short-term guests
                'viewHomeownerFinancials' => false, // strictly false for renters and short-term guests
                'createPermanentCredentials' => ! $isRenter && ! $isShortTerm && ($isHomeowner || $isAdmin), // strictly false
                'modifyCommunitySettings' => $isAdmin,
                'preClearVisitor' => true,
                'authorizeStaff' => ! $isShortTerm, // false for short-term
                'authorizeContractor' => ! $isShortTerm, // false for short-term
                'addOccupants' => ! $isShortTerm, // false for short-term
                'manageLegacyContacts' => ! $isShortTerm, // false for short-term
                'revokeOwnCredentials' => true,
            ],
            'isShortTerm' => $isShortTerm,
            'isRenter' => $isRenter,
            'isHomeowner' => $isHomeowner,
            'isAdmin' => $isAdmin,
            'roleLabel' => $isShortTerm ? 'Short-Term Rental Guest' : ($isRenter ? 'Long-Term Renter' : ($isHomeowner ? 'Homeowner' : $user->role->value)),
            'leaseEndDate' => $renterRecord?->lease_end?->toDateString(),
            'checkoutDate' => $renterRecord?->lease_end?->toDateString(),
            'scopeBadge' => $scopeBadge,
        ];

        // Legacy variables for backwards compatibility
        $contacts = $allDelegations->filter(fn ($d) => $d->access_level === 'Emergency Contact')->values();
        $longTermOccupants = $allDelegations->filter(fn ($d) => $d->access_level === 'Long-Term Occupant' || $d->authorization_type === 'long_term_occupant')->values();
        $delegates = $allDelegations->filter(fn ($d) => $d->access_level !== 'Emergency Contact' && $d->access_level !== 'Long-Term Occupant' && $d->authorization_type !== 'long_term_occupant')->values();
        $continuityPlan = $user->emergencyContinuityPlan()->with(['primaryDelegate', 'secondaryDelegate'])->first();

        $authTypes = collect(AuthorizationType::cases())->map(fn ($type) => [
            'value' => $type->value,
            'label' => $type->label(),
            'tier' => $type->tier()->label(),
            'isResident' => $type->isResident(),
            'defaultRules' => $type->defaultAccessRules(),
        ]);

        $data = [
            // Unified Access & People Props
            'authorizedPeople' => $allPeople,
            'categoryCounts' => $categoryCounts,
            'categorized' => [
                'residents' => $residentsList,
                'long_term_occupants' => $longTermOccupantsList,
                'short_term_rentals' => $shortTermRentalsList,
                'legacy_contacts' => $legacyContactsList,
                'family_members' => $familyMembersList,
                'caregivers' => $caregiversList,
                'property_managers' => $propertyManagersList,
                'domestic_staff' => $domesticStaffList,
                'staff' => $staffList,
                'contractors' => $contractorsList,
                'visitors' => $visitorsList,
                'former_occupants' => $formerOccupantsList,
            ],
            'homeownerSections' => $sections,
            'sections' => $sections,
            'permissions' => $permissions,
            'scopeBadge' => $scopeBadge,
            'pageSubtitle' => $pageSubtitle,
            'unitLabel' => $unitLabel,
            'pageTitle' => $pageTitle,

            // Legacy & Continuity Plan Props
            'contacts' => $contacts,
            'delegates' => $delegates,
            'longTermOccupants' => $longTermOccupants,
            'allDelegations' => $allDelegations,
            'continuityPlan' => $continuityPlan,
            'properties' => $user->properties()->get(['id', 'lot_number', 'street_address']),
            'accessLevels' => DelegatedAccess::ACCESS_LEVELS,
            'relationships' => DelegatedAccess::RELATIONSHIPS,
            'availablePermissions' => DelegatedAccess::PERMISSIONS,
            'authorizationTypes' => $authTypes,
            'continuityConditions' => EmergencyContinuityPlan::CONDITIONS,
            'continuityVerifications' => EmergencyContinuityPlan::VERIFICATIONS,
            'continuityActions' => EmergencyContinuityPlan::ACTIONS,
            'durationTypes' => DelegatedAccess::DURATION_TYPES,
        ];

        if ($request->wantsJson()) {
            return response()->json($data);
        }

        return Inertia::render('Dashboard/Delegation/Index', $data);
    }

    /**
     * Store a new emergency contact, occupant, or delegated access grant.
     */
    public function store(Request $request, DelegatedAccessService $service): RedirectResponse|JsonResponse
    {
        // Residents (homeowners and renters) and administrators. Security and
        // staff could otherwise mint estate gate passes for anyone.
        abort_unless(
            $request->user()->role->isResident() || $request->user()->role->isAdministrative(),
            403,
            'Only residents and administrators can authorize people.',
        );

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'relationship' => ['required', 'string', 'max:100'],
            'authorization_type' => ['nullable', 'string'],
            'access_level' => ['required', 'string', 'in:'.implode(',', array_keys(DelegatedAccess::ACCESS_LEVELS))],
            'duration_type' => ['nullable', 'string', 'in:'.implode(',', array_keys(DelegatedAccess::DURATION_TYPES))],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'in:'.implode(',', array_keys(DelegatedAccess::PERMISSIONS))],
            'access_rules' => ['nullable', 'array'],
            'property_id' => ['nullable', 'exists:properties,id'],
            'activation_method' => ['nullable', 'string', 'in:immediate,emergency_trigger,incapacity_proof,admin_approval_required'],
            'starts_at' => ['nullable', 'date', 'after_or_equal:today'],
            'expires_at' => ['nullable', 'date', 'after:now', 'after_or_equal:starts_at'],
            'security_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        // Only for a property of the grantor's own; it used to be any property id.
        if (! empty($validated['property_id']) && ! $request->user()->role->isAdministrative()) {
            abort_unless(
                $request->user()->properties()->whereKey($validated['property_id'])->exists(),
                403,
                'You can only authorize people for your own property.',
            );
        }

        $visibilityService = app(AccessVisibilityService::class);
        $roleName = $visibilityService->resolveUserRole($request->user());
        $viewAs = strtolower((string) ($request->input('view_as') ?: $request->query('view_as', '')));
        $renterRecord = $request->user()->renter ?? $request->user()->activeStay();

        $isShortTermRenter = false;
        if ($renterRecord) {
            $stay = strtolower((string) ($renterRecord->stay_type ?? ''));
            $notes = strtolower((string) ($renterRecord->notes ?? ''));
            if (str_contains($stay, 'short') || str_contains($stay, 'airbnb') || str_contains($notes, 'airbnb') || str_contains($stay, 'vacation')) {
                $isShortTermRenter = true;
            }
        }

        $isShortTerm = in_array($viewAs, ['short_term', 'airbnb', 'guest', 'vacation'], true)
            || $roleName === 'Short-Term Rental / Airbnb Host/Guest'
            || $roleName === 'Short-Term Renter'
            || $isShortTermRenter;

        $isRenter = ! $isShortTerm && ($roleName === 'Long-Term Renter' || $viewAs === 'renter');

        if ($isShortTerm) {
            // Short-term guests cannot create unrestricted permanent credentials
            if (($validated['duration_type'] ?? '') === 'manual_revocation') {
                throw ValidationException::withMessages([
                    'duration_type' => 'Short-term guests cannot create unrestricted permanent credentials. All passes must have a temporary duration bounded by your reservation check-out.',
                ]);
            }
            // Short-term guests can only pre-clear visitors for their stay
            if (! in_array($validated['access_level'], ['Visitor', 'Guest Pass', 'Day Visitor', 'Pre-Cleared Visitor'], true)) {
                abort(403, 'Short-term guests can only register visitors for their reservation stay.');
            }
            // Bound expiration by stay check-out date
            if ($renterRecord && $renterRecord->lease_end) {
                $inputExpires = ! empty($validated['expires_at']) ? Carbon::parse($validated['expires_at']) : null;
                if (! $inputExpires || $inputExpires->isAfter($renterRecord->lease_end)) {
                    $validated['expires_at'] = $renterRecord->lease_end->copy()->endOfDay();
                }
            }
        } elseif ($isRenter) {
            // Renters cannot create unrestricted permanent credentials
            if (($validated['duration_type'] ?? '') === 'manual_revocation') {
                throw ValidationException::withMessages([
                    'duration_type' => 'Renters cannot create unrestricted permanent credentials. All passes must have a temporary duration bounded by your lease.',
                ]);
            }
            // Renters cannot authorize short-term rentals / Airbnb
            if (($validated['authorization_type'] ?? '') === 'short_term_rental' || stripos($validated['access_level'], 'short') !== false) {
                abort(403, 'Renters are not permitted to authorize short-term rentals.');
            }
            // Renters cannot create homeowner or ownership credentials
            if ($validated['access_level'] === 'Homeowner' || $validated['relationship'] === 'Homeowner') {
                abort(403, 'Renters cannot configure homeowner credentials or change property ownership.');
            }
            // Bound expiration by lease end date if present
            if ($renterRecord && $renterRecord->lease_end) {
                $inputExpires = ! empty($validated['expires_at']) ? Carbon::parse($validated['expires_at']) : null;
                if ($inputExpires && $inputExpires->isAfter($renterRecord->lease_end)) {
                    $validated['expires_at'] = $renterRecord->lease_end->copy()->endOfDay();
                }
            }
        }

        $delegation = $service->createDelegation($request->user(), $validated);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Authorization successfully created for {$delegation->name}.",
                'delegation' => $delegation->load(['property', 'delegate', 'gatePasses']),
            ], 201);
        }

        return back()->with('success', "Authorization created for {$delegation->name}.");
    }

    /**
     * Update access rules and permissions for an authorized person.
     */
    public function updateRules(Request $request, DelegatedAccess $delegation): RedirectResponse|JsonResponse
    {
        $this->authorizeDelegation($request->user(), $delegation);

        $validated = $request->validate([
            'access_rules' => ['required', 'array'],
            'permissions' => ['nullable', 'array'],
            'duration_type' => ['nullable', 'string', 'in:'.implode(',', array_keys(DelegatedAccess::DURATION_TYPES))],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $visibilityService = app(AccessVisibilityService::class);
        $roleName = $visibilityService->resolveUserRole($request->user());
        $viewAs = strtolower((string) ($request->input('view_as') ?: $request->query('view_as', '')));
        $renterRecord = $request->user()->renter ?? $request->user()->activeStay();

        $isShortTermRenter = false;
        if ($renterRecord) {
            $stay = strtolower((string) ($renterRecord->stay_type ?? ''));
            $notes = strtolower((string) ($renterRecord->notes ?? ''));
            if (str_contains($stay, 'short') || str_contains($stay, 'airbnb') || str_contains($notes, 'airbnb') || str_contains($stay, 'vacation')) {
                $isShortTermRenter = true;
            }
        }

        $isShortTerm = in_array($viewAs, ['short_term', 'airbnb', 'guest', 'vacation'], true)
            || $roleName === 'Short-Term Rental / Airbnb Host/Guest'
            || $roleName === 'Short-Term Renter'
            || $isShortTermRenter;

        $isRenter = ! $isShortTerm && ($roleName === 'Long-Term Renter' || $viewAs === 'renter');

        if ($isShortTerm) {
            if ($delegation->grantor_user_id !== $request->user()->id) {
                abort(403, 'Short-term guests cannot modify rules for property services or host credentials.');
            }
            if (($validated['duration_type'] ?? '') === 'manual_revocation') {
                throw ValidationException::withMessages([
                    'duration_type' => 'Short-term guests cannot create unrestricted permanent credentials. Credential duration must be temporary or bounded by your checkout date.',
                ]);
            }
            if ($renterRecord && $renterRecord->lease_end && ! empty($validated['expires_at'])) {
                $inputExpires = Carbon::parse($validated['expires_at']);
                if ($inputExpires->isAfter($renterRecord->lease_end)) {
                    $validated['expires_at'] = $renterRecord->lease_end->toDateString();
                }
            }
        } elseif ($isRenter && ($validated['duration_type'] ?? '') === 'manual_revocation') {
            throw ValidationException::withMessages([
                'duration_type' => 'Renters cannot create unrestricted permanent credentials. Credential duration must be temporary or bounded by your lease.',
            ]);
        }

        // Clamp expires_at to lease end date for renters
        if ($isRenter) {
            if ($renterRecord && $renterRecord->lease_end && ! empty($validated['expires_at'])) {
                $inputExpires = Carbon::parse($validated['expires_at']);
                if ($inputExpires->isAfter($renterRecord->lease_end)) {
                    $validated['expires_at'] = $renterRecord->lease_end->toDateString();
                }
            }
        }

        $inputRules = $validated['access_rules'];
        if (! empty($inputRules['entry_start_time']) && ! empty($inputRules['entry_end_time'])) {
            $inputRules['time_window'] = [
                'start' => $inputRules['entry_start_time'],
                'end' => $inputRules['entry_end_time'],
            ];
        }
        if (! empty($inputRules['allowed_days'])) {
            $inputRules['days_permitted'] = $inputRules['allowed_days'];
        }

        $updates = [
            'access_rules' => array_merge($delegation->access_rules ?? [], $inputRules),
            'permissions' => $validated['permissions'] ?? $delegation->permissions,
        ];

        if (! empty($validated['duration_type'])) {
            $updates['duration_type'] = $validated['duration_type'];
            $startsAt = ! empty($validated['starts_at']) ? Carbon::parse($validated['starts_at']) : ($delegation->starts_at ?? now());
            $updates['starts_at'] = $startsAt;
            $updates['expires_at'] = match ($validated['duration_type']) {
                '2_hours' => (clone $startsAt)->addHours(2),
                '1_day' => (clone $startsAt)->isToday() ? (clone $startsAt)->endOfDay() : (clone $startsAt)->addDay(),
                '1_week' => (clone $startsAt)->addDays(7)->endOfDay(),
                'manual_revocation' => null,
                'recurring' => ! empty($validated['expires_at']) ? Carbon::parse($validated['expires_at']) : (clone $startsAt)->endOfYear(),
                default => ! empty($validated['expires_at']) ? Carbon::parse($validated['expires_at']) : $delegation->expires_at,
            };
        }

        $delegation->update($updates);

        // Sync valid_until on its live passes, checked-in ones included, with the
        // same capped rule as a new pass (DelegatedAccess::passValidUntil()).
        $newValidUntil = $delegation->passValidUntil();

        $delegation->gatePasses()
            ->whereIn('status', [PassStatus::Active, PassStatus::Issued, PassStatus::CheckedIn])
            ->get()
            ->each(function (GatePass $pass) use ($newValidUntil, $delegation) {
                $pass->update([
                    'valid_until' => $newValidUntil,
                    'metadata' => array_merge($pass->metadata ?? [], [
                        'duration_type' => $delegation->duration_type,
                        'access_rules' => $delegation->access_rules,
                    ]),
                ]);
            });

        $delegation->logEvent('rules_updated', "Access permissions & operational rules updated for {$delegation->name}", [
            'access_rules' => $delegation->access_rules,
            'permissions' => $delegation->permissions,
            'duration_type' => $delegation->duration_type,
        ], $request->user());

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Access rules updated for {$delegation->name}.",
                'delegation' => $delegation->fresh(['gatePasses']),
            ]);
        }

        return back()->with('success', "Access rules updated for {$delegation->name}.");
    }

    /**
     * Reissue or rotate a fresh QR credential pass for an authorized person.
     */
    public function reissuePass(Request $request, DelegatedAccess $delegation): RedirectResponse|JsonResponse
    {
        $this->authorizeDelegation($request->user(), $delegation);

        $delegation->revokePasses($request->user());
        $pass = $delegation->issueDelegateGatePass($this->engine, $request->user());

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Fresh QR credential ({$pass->pass_id}) issued for {$delegation->name}.",
                'pass' => $pass,
            ]);
        }

        return back()->with('success', "Fresh QR credential issued for {$delegation->name}.");
    }

    /**
     * Activate emergency mode for an authorized delegate.
     */
    public function activateEmergency(
        Request $request,
        DelegatedAccess $delegation,
        DelegatedAccessService $service
    ): RedirectResponse|JsonResponse {
        $this->authorizeDelegation($request->user(), $delegation);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
            'duration_days' => ['nullable', 'integer', 'min:1', 'max:90'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $service->activateEmergencyAccess(
            delegation: $delegation,
            reason: $validated['reason'],
            actor: $request->user(),
            durationDays: $validated['duration_days'] ?? 7,
            notes: $validated['notes'] ?? null
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Emergency access activated for {$delegation->name}.",
                'delegation' => $delegation->fresh(['events', 'gatePasses']),
            ]);
        }

        return back()->with('success', "Emergency access activated for {$delegation->name}.");
    }

    /**
     * Deactivate emergency mode for an authorized delegate.
     */
    public function deactivateEmergency(
        Request $request,
        DelegatedAccess $delegation,
        DelegatedAccessService $service
    ): RedirectResponse|JsonResponse {
        $this->authorizeDelegation($request->user(), $delegation);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $service->deactivateEmergencyAccess($delegation, $request->user(), $validated['reason'] ?? null);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Emergency access deactivated for {$delegation->name}.",
                'delegation' => $delegation->fresh(['events', 'gatePasses']),
            ]);
        }

        return back()->with('success', "Emergency access deactivated for {$delegation->name}.");
    }

    /**
     * Revoke a delegation permanently.
     */
    public function revoke(
        Request $request,
        DelegatedAccess $delegation,
        DelegatedAccessService $service
    ): RedirectResponse|JsonResponse {
        $this->authorizeDelegation($request->user(), $delegation);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $visibilityService = app(AccessVisibilityService::class);
        $roleName = $visibilityService->resolveUserRole($request->user());
        $viewAs = strtolower((string) ($request->input('view_as') ?: $request->query('view_as', '')));
        $renterRecord = $request->user()->renter ?? $request->user()->activeStay();

        $isShortTermRenter = false;
        if ($renterRecord) {
            $stay = strtolower((string) ($renterRecord->stay_type ?? ''));
            $notes = strtolower((string) ($renterRecord->notes ?? ''));
            if (str_contains($stay, 'short') || str_contains($stay, 'airbnb') || str_contains($notes, 'airbnb') || str_contains($stay, 'vacation')) {
                $isShortTermRenter = true;
            }
        }

        $isShortTerm = in_array($viewAs, ['short_term', 'airbnb', 'guest', 'vacation'], true)
            || $roleName === 'Short-Term Rental / Airbnb Host/Guest'
            || $roleName === 'Short-Term Renter'
            || $isShortTermRenter;

        $isRenter = ! $isShortTerm && ($roleName === 'Long-Term Renter' || $viewAs === 'renter');

        if ($isShortTerm) {
            if ($delegation->grantor_user_id !== $request->user()->id) {
                abort(403, 'Short-term guests cannot revoke property-level authorizations granted by the homeowner.');
            }
            $delegateUser = $delegation->delegate ?? User::where('email', $delegation->email)->first();
            if (($delegateUser && $delegateUser->role === UserRole::Homeowner) || $delegation->relationship === 'Homeowner' || $delegation->access_level === 'Homeowner') {
                abort(403, 'Short-term guests cannot remove or revoke the property homeowner.');
            }
        } elseif ($isRenter) {
            if ($delegation->grantor_user_id !== $request->user()->id) {
                abort(403, 'Renters cannot revoke property-level authorizations granted by the homeowner.');
            }
            $delegateUser = $delegation->delegate ?? User::where('email', $delegation->email)->first();
            if (($delegateUser && $delegateUser->role === UserRole::Homeowner) || $delegation->relationship === 'Homeowner' || $delegation->access_level === 'Homeowner') {
                abort(403, 'Renters cannot remove or revoke the property homeowner.');
            }
        }

        $service->revokeDelegation($delegation, $request->user(), $validated['reason'] ?? null);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Delegation for {$delegation->name} has been revoked.",
                'delegation' => $delegation->fresh(),
            ]);
        }

        return back()->with('success', "Delegation for {$delegation->name} has been revoked.");
    }

    /**
     * Configure the homeowner's Emergency & Incapacity Continuity Plan.
     */
    public function saveContinuityPlan(Request $request, DelegatedAccessService $service): RedirectResponse|JsonResponse
    {
        $visibilityService = app(AccessVisibilityService::class);
        $roleName = $visibilityService->resolveUserRole($request->user());
        $viewAs = strtolower((string) ($request->input('view_as') ?: $request->query('view_as', '')));
        $renterRecord = $request->user()->renter ?? $request->user()->activeStay();

        $isShortTermRenter = false;
        if ($renterRecord) {
            $stay = strtolower((string) ($renterRecord->stay_type ?? ''));
            $notes = strtolower((string) ($renterRecord->notes ?? ''));
            if (str_contains($stay, 'short') || str_contains($stay, 'airbnb') || str_contains($notes, 'airbnb') || str_contains($stay, 'vacation')) {
                $isShortTermRenter = true;
            }
        }

        $isShortTerm = in_array($viewAs, ['short_term', 'airbnb', 'guest', 'vacation'], true)
            || $roleName === 'Short-Term Rental / Airbnb Host/Guest'
            || $roleName === 'Short-Term Renter'
            || $isShortTermRenter;

        $isRenter = ! $isShortTerm && ($roleName === 'Long-Term Renter' || $viewAs === 'renter');

        if ($isShortTerm || $isRenter) {
            abort(403, 'Emergency & Incapacity Continuity Plans are reserved for property homeowners.');
        }
        $validated = $request->validate([
            'primary_delegate_id' => ['nullable', 'exists:delegated_accesses,id'],
            'secondary_delegate_id' => ['nullable', 'exists:delegated_accesses,id'],
            'activation_conditions' => ['required', 'array', 'min:1'],
            'activation_conditions.*' => ['string', 'in:'.implode(',', array_keys(EmergencyContinuityPlan::CONDITIONS))],
            'required_verification' => ['required', 'string', 'in:'.implode(',', array_keys(EmergencyContinuityPlan::VERIFICATIONS))],
            'authorized_actions' => ['required', 'array', 'min:1'],
            'authorized_actions.*' => ['string', 'in:'.implode(',', array_keys(EmergencyContinuityPlan::ACTIONS))],
            'max_duration_days' => ['required', 'integer', 'min:1', 'max:180'],
            'requires_admin_approval' => ['required', 'boolean'],
            'notify_homeowner_on_trigger' => ['required', 'boolean'],
            'notify_community_security' => ['required', 'boolean'],
            'notification_recipients' => ['nullable', 'array'],
            'special_instructions' => ['nullable', 'string', 'max:1500'],
        ]);

        $plan = $service->saveContinuityPlan($request->user(), $validated);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Emergency Continuity Plan saved successfully.',
                'plan' => $plan->fresh(['primaryDelegate', 'secondaryDelegate']),
            ]);
        }

        return back()->with('success', 'Emergency Continuity Plan updated.');
    }

    /**
     * Retrieve the audit trail events for a delegation.
     */
    public function auditEvents(Request $request, DelegatedAccess $delegation): JsonResponse
    {
        $this->authorizeDelegation($request->user(), $delegation);

        $events = $delegation->events()->with('actor:id,name,email')->get();

        return response()->json([
            'delegation_id' => $delegation->id,
            'name' => $delegation->name,
            'events' => $events,
        ]);
    }

    /**
     * Ensure the actor is the grantor homeowner or has platform administrative authority.
     */
    protected function authorizeDelegation($user, DelegatedAccess $delegation): void
    {
        $isGrantor = $user->id === $delegation->grantor_user_id;
        $isAdmin = $user->role->isAdministrative();

        if (! $isGrantor && ! $isAdmin) {
            abort(403, 'Unauthorized access to this delegation boundary.');
        }
    }

    /** An administrator approves a delegation awaiting approval; only then is its pass issued. */
    public function approve(Request $request, DelegatedAccess $delegation, DelegatedAccessService $service): RedirectResponse|JsonResponse
    {
        abort_unless($request->user()->role->isAdministrative(), 403, 'Only administrators approve delegations.');
        abort_unless($delegation->approval_status === 'pending', 422, 'This delegation is not awaiting approval.');

        $service->approveDelegation($delegation, $request->user());

        return $request->wantsJson()
            ? response()->json(['success' => true, 'delegation' => $delegation->fresh(['gatePasses'])])
            : back()->with('success', "Delegation for {$delegation->name} approved.");
    }

    public function reject(Request $request, DelegatedAccess $delegation, DelegatedAccessService $service): RedirectResponse|JsonResponse
    {
        abort_unless($request->user()->role->isAdministrative(), 403, 'Only administrators reject delegations.');
        abort_unless($delegation->approval_status === 'pending', 422, 'This delegation is not awaiting approval.');

        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);
        $service->rejectDelegation($delegation, $request->user(), $validated['reason'] ?? null);

        return $request->wantsJson()
            ? response()->json(['success' => true])
            : back()->with('success', "Delegation for {$delegation->name} rejected.");
    }

    /** The invitee, signed in, accepts the invitation sent to their email. */
    public function accept(Request $request, string $token, DelegatedAccessService $service): RedirectResponse|JsonResponse
    {
        try {
            $delegation = $service->acceptInvite($token, $request->user());
        } catch (\DomainException $e) {
            abort(403, $e->getMessage());
        }

        return $request->wantsJson()
            ? response()->json(['success' => true, 'delegation_id' => $delegation->id])
            : redirect()->route('dashboard.delegation')->with('success', "You are now linked to {$delegation->grantor?->name}'s authorization.");
    }

    /**
     * Which of a homeowner's properties a renter rents: the one matching the
     * renter's lot, or the only one the homeowner has. An owner of several
     * with no lot to go on gives none, rather than a guess at the first.
     */
    private function rentedProperty(int $homeownerId, ?string $renterLot): ?Property
    {
        $owned = Property::where('owner_user_id', $homeownerId)->orderBy('id')->get();
        $bare = fn (?string $lot) => strtolower(preg_replace('/^(unit|lot|#)\s*/i', '', trim((string) $lot)));

        if (filled($renterLot)) {
            return $owned->first(fn (Property $p) => $bare($p->lot_number) === $bare($renterLot));
        }

        return $owned->count() === 1 ? $owned->first() : null;
    }
}
