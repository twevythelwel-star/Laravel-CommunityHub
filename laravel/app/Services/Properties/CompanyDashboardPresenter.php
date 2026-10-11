<?php

namespace App\Services\Properties;

use App\Enums\OwnershipEntityType;
use App\Models\AccessRiskIncident;
use App\Models\AuthorizationDocument;
use App\Models\AuthorizedPerson;
use App\Models\DelegatedAccess;
use App\Models\GatePass;
use App\Models\Household;
use App\Models\Invoice;
use App\Models\OwnershipEntity;
use App\Models\ParkingPass;
use App\Models\Property;
use App\Models\Renter;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Visitor;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * The company dashboard: a representative sees only the properties their
 * appointment covers; administrators see every company.
 */
class CompanyDashboardPresenter
{
    /**
     * Resolve the active corporate entity for the user.
     */
    public function resolveCompanyForUser(User $user, ?string $requestedCompanyId = null): OwnershipEntity
    {
        // 1. If a specific company is requested and valid
        if ($requestedCompanyId) {
            $candidate = OwnershipEntity::find($requestedCompanyId);
            if ($candidate) {
                $this->authorizeAccessToCompany($user, $candidate);

                return $candidate;
            }
        }

        // 2. If the user is an authorized representative of any company
        $userEntity = OwnershipEntity::whereHas('authorizedPeople', function ($q) use ($user) {
            $q->where('user_id', $user->id)->current();
        })->where('entity_type', OwnershipEntityType::Company)->first();

        if ($userEntity) {
            return $userEntity;
        }

        // 3. An administrator starts on the first company.
        if ($user->role->isAdministrative()) {
            $primaryCompany = OwnershipEntity::where('entity_type', OwnershipEntityType::Company)->orderBy('id')->first();

            if ($primaryCompany) {
                return $primaryCompany;
            }
        }

        // 4. Any active authorized entity (including trust/partnership if applicable)
        $anyAuthorized = OwnershipEntity::whereHas('authorizedPeople', function ($q) use ($user) {
            $q->where('user_id', $user->id)->current();
        })->first();

        if ($anyAuthorized) {
            return $anyAuthorized;
        }

        // 5. Fallback for admins: first entity or abort 403 for non-admins
        $fallback = OwnershipEntity::first();
        if ($fallback && $user->role->isAdministrative()) {
            return $fallback;
        }

        abort(403, 'You are not an authorized representative of any company ownership entity.');
    }

    /**
     * Check if user is authorized to view this company entity.
     */
    private function authorizeAccessToCompany(User $user, OwnershipEntity $entity): void
    {
        Gate::forUser($user)->authorize('view', $entity);
    }

    /**
     * Render the company dashboard data with strict property-level authorization scoping.
     */
    public function props(Request $request, OwnershipEntity $company): array
    {
        $user = $request->user();

        // ── STRICT PROPERTY SCOPING ──────────────────────────────────────
        // "The company representative sees only properties they are authorized to manage."
        $authorizedProperties = $user->authorizedPropertiesForEntity($company);

        // If the company has properties but representative has none authorized:
        if ($authorizedProperties->isEmpty() && ! $user->role->isAdministrative()) {
            // Representative has no properties assigned
            $authorizedPropertyIds = [];
        } else {
            $authorizedPropertyIds = $authorizedProperties->pluck('id')->all();
        }

        // Property filtering: single unit vs entire authorized portfolio
        $requestedPropertyParam = $request->string('property_id')->trim()->toString();
        $selectedProperty = null;

        if ($requestedPropertyParam && $requestedPropertyParam !== 'all') {
            $selectedProperty = $authorizedProperties->first(function ($p) use ($requestedPropertyParam) {
                return (string) $p->id === $requestedPropertyParam
                    || strcasecmp($p->lot_number, $requestedPropertyParam) === 0
                    || strcasecmp($p->property_code, $requestedPropertyParam) === 0;
            });

            if (! $selectedProperty) {
                // Deny unauthorized property access attempt
                abort(403, 'You are not authorized to manage the requested property.');
            }

            $scopedProperties = collect([$selectedProperty]);
        } else {
            $scopedProperties = $authorizedProperties;
        }

        $scopedPropertyIds = $scopedProperties->pluck('id')->all();
        $scopedLotNumbers = $scopedProperties->pluck('lot_number')->filter()->all();

        // ── Available companies for the switcher ──────────────────────────
        $availableCompanies = ($user->role->isAdministrative())
            ? OwnershipEntity::where('entity_type', OwnershipEntityType::Company)
                ->select(['id', 'legal_name', 'trade_name', 'entity_type', 'status'])
                ->get()
            : OwnershipEntity::whereHas('authorizedPeople', function ($q) use ($user) {
                $q->where('user_id', $user->id)->current();
            })->select(['id', 'legal_name', 'trade_name', 'entity_type', 'status'])->get();

        // ── PROPERTIES LIST (Formatted to match prompt standard) ──────────
        // e.g.:
        // Unit 14  ● Occupied
        // Unit 18  ● Vacant
        // Unit 22  ● Rented
        $propertiesList = $authorizedProperties->map(function (Property $property) use ($selectedProperty) {
            $statusRaw = strtolower($property->status ?: 'vacant');
            $statusLabel = match ($statusRaw) {
                'occupied' => 'Occupied',
                'rented' => 'Rented',
                default => 'Vacant',
            };

            $statusColor = match ($statusRaw) {
                'occupied' => 'emerald',
                'rented' => 'blue',
                default => 'amber',
            };

            $currentOccupant = $property->primaryOccupant;
            $currentRenter = $property->renters()->where('status', 'Active')->first();

            return [
                'id' => $property->id,
                'lot_number' => $property->lot_number ?: "Unit {$property->id}",
                'property_code' => $property->property_code,
                'street_address' => $property->street_address ?: 'Royal Palm Way',
                'property_type' => $property->property_type ?: 'Residential Unit',
                'status' => $statusRaw,
                'status_label' => $statusLabel,
                'status_color' => $statusColor,
                'is_selected' => $selectedProperty?->id === $property->id,
                'occupant_name' => $property->occupantName() ?? ($currentRenter?->name ?: 'Vacant'),
                'occupant' => $currentOccupant ? [
                    'id' => $currentOccupant->id,
                    'name' => $currentOccupant->name,
                    'phone' => $currentOccupant->phone,
                    'email' => $currentOccupant->email,
                ] : null,
                'renter' => $currentRenter ? [
                    'id' => $currentRenter->id,
                    'name' => $currentRenter->name,
                    'stay_type' => $currentRenter->stay_type,
                    'lease_end' => $currentRenter->lease_end?->format('M d, Y'),
                ] : null,
                'stats' => [
                    'residents_count' => ($property->primary_occupant_id ? 1 : 0) + ($property->primaryOccupant ? Household::where('primary_homeowner_id', $property->primary_occupant_id)->first()?->members()->count() ?? 0 : 0),
                    'renters_count' => $property->renters()->count(),
                    'visitors_count' => Visitor::where('property_id', $property->id)->count(),
                    'staff_count' => DelegatedAccess::where('property_id', $property->id)->whereIn('access_level', ['Domestic Staff', 'Caregiver', 'Staff', 'Housekeeper'])->count(),
                    'contractors_count' => DelegatedAccess::where('property_id', $property->id)->where('access_level', 'Contractor')->count(),
                    'vehicles_count' => Vehicle::where('property', $property->lot_number)->orWhere('property', "Unit {$property->lot_number}")->count(),
                    'parking_count' => ParkingPass::where('property', $property->lot_number)->orWhere('property', "Unit {$property->lot_number}")->count(),
                    'pending_balance_minor' => Invoice::where('property_id', $property->id)->where('status', 'pending')->sum('amount_minor'),
                ],
            ];
        })->values();

        // ── PEOPLE & ACCESS (The 7 Pillars) ──────────────────────────────
        $peopleAndAccess = [
            // 1. Residents
            'residents' => $this->getScopedResidents($scopedProperties),
            // 2. Renters
            'renters' => $this->getScopedRenters($scopedPropertyIds, $scopedLotNumbers),
            // 3. Visitors
            'visitors' => $this->getScopedVisitors($scopedPropertyIds, $scopedProperties),
            // 4. Staff
            'staff' => $this->getScopedStaff($scopedPropertyIds),
            // 5. Contractors
            'contractors' => $this->getScopedContractors($scopedPropertyIds),
            // 6. Legacy Contacts
            'legacy_contacts' => $this->getScopedLegacyContacts($scopedPropertyIds),
            // 7. Representatives
            'representatives' => $this->getCompanyRepresentatives($company, $authorizedProperties),
        ];

        // ── OPERATIONS (The 6 Pillars) ───────────────────────────────────
        $operations = [
            // 1. Vehicles
            'vehicles' => $this->getScopedVehicles($scopedProperties, $scopedLotNumbers),
            // 2. Parking
            'parking' => $this->getScopedParking($scopedProperties, $scopedLotNumbers),
            // 3. Credentials
            'credentials' => $this->getScopedCredentials($scopedProperties, $scopedLotNumbers),
            // 4. Incidents
            'incidents' => $this->getScopedIncidents($scopedProperties),
            // 5. Payments
            'payments' => $this->getScopedPayments($scopedPropertyIds),
            // 6. Documents
            'documents' => $this->getCompanyDocuments($company),
        ];

        // ── Aggregate Summary KPIs ───────────────────────────────────────
        $summary = [
            'total_properties' => $authorizedProperties->count(),
            'occupied_count' => $authorizedProperties->where('status', 'occupied')->count(),
            'vacant_count' => $authorizedProperties->where('status', 'vacant')->count(),
            'rented_count' => $authorizedProperties->where('status', 'rented')->count(),
            'total_residents' => count($peopleAndAccess['residents']),
            'total_renters' => count($peopleAndAccess['renters']),
            'total_visitors' => count($peopleAndAccess['visitors']),
            'total_staff' => count($peopleAndAccess['staff']),
            'total_contractors' => count($peopleAndAccess['contractors']),
            'total_legacy_contacts' => count($peopleAndAccess['legacy_contacts']),
            'total_representatives' => count($peopleAndAccess['representatives']),
            'total_vehicles' => count($operations['vehicles']),
            'total_parking' => count($operations['parking']),
            'total_credentials' => count($operations['credentials']),
            'total_incidents' => count($operations['incidents']),
            'total_documents' => count($operations['documents']),
            'total_pending_dues_minor' => Invoice::whereIn('property_id', $scopedPropertyIds)->where('status', 'pending')->sum('amount_minor'),
            'total_paid_dues_minor' => Invoice::whereIn('property_id', $scopedPropertyIds)->where('status', 'paid')->sum('amount_minor'),
        ];

        // User's specific representative record for this company
        $userPerson = $company->activeAuthorizedPeople()->where('user_id', $user->id)->first();

        return [
            'company' => [
                'id' => $company->id,
                'legal_name' => $company->legal_name,
                'trade_name' => $company->trade_name,
                'entity_type' => $company->entity_type->value,
                'entity_type_label' => $company->entity_type->label(),
                'registration_number' => $company->registration_number,
                'jurisdiction' => $company->jurisdiction,
                'primary_email' => $company->primary_email,
                'primary_phone' => $company->primary_phone,
                'billing_address' => $company->billing_address,
                'status' => $company->status,
                'verification_status' => $company->verification_status ?: 'verified',
                'is_verified' => $company->isVerified(),
                'verification_source' => $company->verification_source ?: 'Companies Office of Jamaica (COJ)',
                'metadata' => $company->metadata,
            ],
            'verification' => $company->getVerificationSummary(),
            'beneficial_ownership' => $company->getBeneficialOwnershipProfile($user),
            'can_view_beneficial_ownership' => $company->canUserViewBeneficialOwnership($user),
            'available_companies' => $availableCompanies,
            'properties' => $propertiesList,
            'selected_property_id' => $selectedProperty?->id ?? 'all',
            'selected_property' => $selectedProperty ? [
                'id' => $selectedProperty->id,
                'lot_number' => $selectedProperty->lot_number,
                'property_code' => $selectedProperty->property_code,
                'street_address' => $selectedProperty->street_address,
                'status' => $selectedProperty->status,
                'occupant_name' => $selectedProperty->occupantName(),
            ] : null,
            'summary' => $summary,
            'people_and_access' => $peopleAndAccess,
            'operations' => $operations,
            'current_user_authorization' => [
                'is_admin' => $user->role->isAdministrative(),
                'role' => $userPerson?->role?->value ?? ($user->role->isAdministrative() ? 'admin' : 'representative'),
                'role_label' => $userPerson?->role?->label() ?? ($user->role->isAdministrative() ? 'System Administrator' : 'Authorized Representative'),
                'is_signatory' => (bool) ($userPerson?->is_signatory ?? $user->role->isAdministrative()),
                'is_primary_contact' => (bool) ($userPerson?->is_primary_contact ?? false),
                'can_view_beneficial_ownership' => $company->canUserViewBeneficialOwnership($user),
                'permissions' => $userPerson?->effectivePermissions() ?? ['*'],
                'can_manage_visitors' => $userPerson?->canManageVisitors() ?? true,
                'can_manage_renters' => $userPerson?->canManageRenters() ?? true,
                'can_manage_staff' => $userPerson?->canManageStaff() ?? true,
                'can_manage_contractors' => $userPerson?->canManageContractors() ?? true,
                'can_manage_vehicles' => $userPerson?->canManageVehicles() ?? true,
                'can_manage_billing' => $userPerson?->canManageBilling() ?? true,
            ],
        ];
    }

    /**
     * Residents: Primary occupants and verified household members.
     */
    private function getScopedResidents(Collection $properties): array
    {
        $residents = [];

        foreach ($properties as $property) {
            $occupant = $property->primaryOccupant;
            if ($occupant) {
                $residents[] = [
                    'id' => 'res_occ_'.$occupant->id,
                    'name' => $occupant->name,
                    'role' => 'Primary Occupant',
                    'unit' => $property->lot_number ?: "Unit {$property->id}",
                    'property_id' => $property->id,
                    'email' => $occupant->email,
                    'phone' => $occupant->phone ?: 'Unlisted',
                    'status' => 'Active',
                    'residing_since' => $occupant->created_at?->format('M Y') ?? 'Registered',
                    'badge' => 'Primary Resident',
                ];

                $household = Household::where('primary_homeowner_id', $occupant->id)->first();
                if ($household) {
                    foreach ($household->members()->where('status', 'active')->get() as $member) {
                        $residents[] = [
                            'id' => 'res_mem_'.$member->id,
                            'name' => $member->name,
                            'role' => $member->relationship_label ?: 'Household Member',
                            'unit' => $property->lot_number ?: "Unit {$property->id}",
                            'property_id' => $property->id,
                            'email' => $member->email ?: 'Via Primary Occupant',
                            'phone' => $member->phone ?: 'Unlisted',
                            'status' => 'Active',
                            'residing_since' => $member->created_at?->format('M Y') ?? 'Registered',
                            'badge' => 'Co-occupant',
                        ];
                    }
                }
            }
        }

        return $residents;
    }

    /**
     * Renters: Long-term tenants and short-term guests.
     */
    private function getScopedRenters(array $propertyIds, array $lotNumbers): array
    {
        return Renter::where(function ($q) use ($propertyIds, $lotNumbers) {
            $q->whereIn('property_id', $propertyIds);
            foreach ($lotNumbers as $lot) {
                $q->orWhere('lot', $lot)->orWhere('lot', "Unit {$lot}");
            }
        })
            ->latest()
            ->get()
            ->map(fn (Renter $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'unit' => $r->property?->lot_number ?: ($r->lot ?: 'Assigned Unit'),
                'property_id' => $r->property_id,
                'stay_type' => $r->stay_type ?: 'Long-term',
                'contact' => $r->contact ?: 'Unlisted',
                'status' => $r->status ?: 'Active',
                'lease_start' => $r->lease_start?->format('M d, Y') ?? 'Immediate',
                'lease_end' => $r->lease_end?->format('M d, Y') ?? 'Indefinite',
                'is_expired' => $r->leaseHasExpired(),
                'notes' => $r->notes,
            ])
            ->values()
            ->all();
    }

    /**
     * Visitors: Scheduled, expected, and checked-in guests.
     */
    private function getScopedVisitors(array $propertyIds, Collection $properties): array
    {
        $occupantUserIds = $properties->pluck('primary_occupant_id')->filter()->all();

        return Visitor::where(function ($q) use ($propertyIds, $occupantUserIds) {
            $q->whereIn('property_id', $propertyIds);
            if (! empty($occupantUserIds)) {
                $q->orWhereIn('homeowner_id', $occupantUserIds);
            }
        })
            ->latest('expected_at')
            ->get()
            ->map(fn (Visitor $v) => [
                'id' => $v->id,
                'name' => $v->name,
                'type' => $v->type ?: 'Visitor',
                'unit' => $v->property?->lot_number ?: ($v->homeowner_name ?: 'Estate Unit'),
                'property_id' => $v->property_id,
                'status' => $v->status?->value ?? $v->status,
                'status_label' => ucfirst($v->status?->value ?? (string) $v->status),
                'expected_at' => $v->expected_at?->format('M d, Y H:i') ?? 'Today',
                'checked_in_at' => $v->checked_in_at?->format('H:i') ?? null,
                'host_name' => $v->homeowner_name ?: $v->homeowner?->name,
                'is_blocked' => (bool) $v->is_blocked,
            ])
            ->values()
            ->all();
    }

    /**
     * Staff: Domestic cleaners, housekeepers, caregivers, groundskeepers.
     */
    private function getScopedStaff(array $propertyIds): array
    {
        return DelegatedAccess::whereIn('property_id', $propertyIds)
            ->where(function ($q) {
                $q->whereIn('access_level', ['Domestic Staff', 'Caregiver', 'Staff', 'Housekeeper', 'Domestic Worker'])
                    ->orWhere('authorization_type', 'household_staff')
                    ->orWhere('authorization_type', 'estate_care');
            })
            ->latest()
            ->get()
            ->map(fn (DelegatedAccess $d) => [
                'id' => $d->id,
                'name' => $d->name,
                'role' => $d->relationship ?: 'Household Staff',
                'access_level' => $d->access_level,
                'unit' => $d->property?->lot_number ?: "Unit {$d->property_id}",
                'property_id' => $d->property_id,
                'phone' => $d->phone ?: 'Unlisted',
                'email' => $d->email ?: 'Unlisted',
                'status' => $d->status ?: 'active',
                'schedule' => $d->access_rules['days_of_week'] ?? ['Scheduled Shifts'],
                'time_window' => ($d->access_rules['time_window_start'] ?? '08:00').' - '.($d->access_rules['time_window_end'] ?? '17:00'),
            ])
            ->values()
            ->all();
    }

    /**
     * Contractors: Service vendors, electricians, plumbers, construction teams.
     */
    private function getScopedContractors(array $propertyIds): array
    {
        return DelegatedAccess::whereIn('property_id', $propertyIds)
            ->where(function ($q) {
                $q->where('access_level', 'Contractor')
                    ->orWhere('authorization_type', 'vendor_work_order')
                    ->orWhere('authorization_type', 'preventive_maintenance')
                    ->orWhere('relationship', 'Contractor');
            })
            ->latest()
            ->get()
            ->map(fn (DelegatedAccess $d) => [
                'id' => $d->id,
                'name' => $d->name,
                'trade' => $d->relationship ?: 'Contractor',
                'unit' => $d->property?->lot_number ?: "Unit {$d->property_id}",
                'property_id' => $d->property_id,
                'phone' => $d->phone ?: 'Unlisted',
                'email' => $d->email ?: 'Unlisted',
                'status' => $d->status ?: 'active',
                'starts_at' => $d->starts_at?->format('M d, Y') ?? 'Active',
                'expires_at' => $d->expires_at?->format('M d, Y') ?? 'On Completion',
                'notes' => $d->security_notes ?: 'Authorized property maintenance permit.',
            ])
            ->values()
            ->all();
    }

    /**
     * Legacy Contacts: Emergency succession contacts and continuity delegates.
     */
    private function getScopedLegacyContacts(array $propertyIds): array
    {
        return DelegatedAccess::whereIn('property_id', $propertyIds)
            ->where(function ($q) {
                $q->whereIn('access_level', ['Legacy Delegate', 'Emergency Contact', 'Emergency Delegate'])
                    ->orWhere('authorization_type', 'legacy_contact')
                    ->orWhere('authorization_type', 'emergency_delegate');
            })
            ->latest()
            ->get()
            ->map(fn (DelegatedAccess $d) => [
                'id' => $d->id,
                'name' => $d->name,
                'relationship' => $d->relationship ?: 'Emergency Succession Contact',
                'unit' => $d->property?->lot_number ?: "Unit {$d->property_id}",
                'property_id' => $d->property_id,
                'phone' => $d->phone ?: 'Unlisted',
                'email' => $d->email ?: 'Unlisted',
                'status' => $d->status ?: 'active',
                'notes' => $d->security_notes ?: 'Designated legal & property succession contact.',
            ])
            ->values()
            ->all();
    }

    /**
     * Representatives: Authorized company directors, property managers, accountants.
     */
    private function getCompanyRepresentatives(OwnershipEntity $company, Collection $authorizedProperties): array
    {
        return $company->activeAuthorizedPeople()
            ->with('user')
            ->get()
            ->map(function (AuthorizedPerson $p) {
                $scopedProps = $p->authorizedProperties();
                $isAllProps = ! isset($p->metadata['authorized_property_ids']) || empty($p->metadata['authorized_property_ids']);

                return [
                    'id' => $p->id,
                    'user_id' => $p->user_id,
                    'name' => $p->full_name ?: $p->user?->name,
                    'role' => $p->role->value,
                    'role_label' => $p->role->label(),
                    'corporate_title' => $p->metadata['corporate_title'] ?? $p->role->label(),
                    'email' => $p->email ?: $p->user?->email,
                    'phone' => $p->phone ?: $p->user?->phone,
                    'is_primary_contact' => (bool) $p->is_primary_contact,
                    'is_signatory' => (bool) $p->is_signatory,
                    'verification_status' => $p->verification_status,
                    'status' => $p->status,
                    'permissions' => $p->effectivePermissions(),
                    'managed_properties_summary' => $isAllProps
                        ? 'All Company Properties'
                        : $scopedProps->pluck('lot_number')->implode(', '),
                    'authorized_property_ids' => $p->metadata['authorized_property_ids'] ?? null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Vehicles: Authorized vehicles with ANPR and parking assignments.
     */
    private function getScopedVehicles(Collection $properties, array $lotNumbers): array
    {
        $occupantIds = $properties->pluck('primary_occupant_id')->filter()->all();

        return Vehicle::where(function ($q) use ($lotNumbers, $occupantIds) {
            foreach ($lotNumbers as $lot) {
                $q->orWhere('property', $lot)->orWhere('property', "Unit {$lot}");
            }
            if (! empty($occupantIds)) {
                $q->orWhereIn('user_id', $occupantIds);
            }
        })
            ->latest()
            ->get()
            ->map(fn (Vehicle $v) => [
                'id' => $v->id,
                'license_plate' => $v->formattedPlate(),
                'make' => $v->make,
                'model' => $v->model,
                'year' => $v->year,
                'color' => $v->color,
                'unit' => $v->property ?: 'Company Unit',
                'parking_location' => $v->parking_location ?: 'Designated Bay',
                'is_ev' => (bool) $v->is_ev,
                'anpr_enabled' => (bool) $v->anpr_enabled,
                'status' => $v->status ?: 'active',
                'description' => $v->fullDescription(),
            ])
            ->values()
            ->all();
    }

    /**
     * Parking: Allocated bays and active parking permits.
     */
    private function getScopedParking(Collection $properties, array $lotNumbers): array
    {
        $occupantIds = $properties->pluck('primary_occupant_id')->filter()->all();

        return ParkingPass::where(function ($q) use ($lotNumbers, $occupantIds) {
            foreach ($lotNumbers as $lot) {
                $q->orWhere('property', $lot)->orWhere('property', "Unit {$lot}");
            }
            if (! empty($occupantIds)) {
                $q->orWhereIn('user_id', $occupantIds);
            }
        })
            ->latest()
            ->get()
            ->map(fn (ParkingPass $p) => [
                'id' => $p->id,
                'pass_id' => $p->pass_id,
                'category' => $p->category,
                'category_label' => ucfirst(str_replace('_', ' ', $p->category)),
                'license_plate' => strtoupper($p->license_plate),
                'unit' => $p->property,
                'assigned_bay' => $p->assigned_bay ?: 'General Parking',
                'holder_name' => $p->holder_name,
                'valid_from' => $p->valid_from?->format('M d, Y') ?? 'Active',
                'valid_until' => $p->valid_until?->format('M d, Y') ?? 'Permanent',
                'status' => $p->status ?: 'active',
                'is_ev_charging' => (bool) ($p->metadata['ev_charging_enabled'] ?? false),
            ])
            ->values()
            ->all();
    }

    /**
     * Credentials: Digital gate passes, RFID cards, and NFC credentials.
     */
    private function getScopedCredentials(Collection $properties, array $lotNumbers): array
    {
        $occupantIds = $properties->pluck('primary_occupant_id')->filter()->all();

        return GatePass::where(function ($q) use ($lotNumbers, $occupantIds) {
            foreach ($lotNumbers as $lot) {
                $q->orWhere('property', $lot)->orWhere('property', "Unit {$lot}");
            }
            if (! empty($occupantIds)) {
                $q->orWhereIn('user_id', $occupantIds);
            }
        })
            ->latest()
            ->get()
            ->map(fn (GatePass $g) => [
                'id' => $g->id,
                'pass_id' => $g->pass_id,
                'category' => $g->category?->value ?? (string) $g->category,
                'category_label' => $g->category?->label() ?? 'Pass Credential',
                'holder_name' => $g->holder_name,
                'unit' => $g->property ?: 'Estate Unit',
                'access_zone' => $g->access_zone ?: 'Full Estate Access',
                'designated_gate' => $g->designated_gate?->label() ?? 'Main Gate',
                'status' => $g->status?->value ?? (string) $g->status,
                'valid_from' => $g->valid_from?->format('M d, Y') ?? 'Active',
                'valid_until' => $g->valid_until?->format('M d, Y') ?? 'Never',
                'credential_type' => $g->metadata['credential_type'] ?? 'Digital Wallet & QR',
            ])
            ->values()
            ->all();
    }

    /**
     * Incidents: Gate exceptions, tailgating flags, sensor alerts.
     */
    private function getScopedIncidents(Collection $properties): array
    {
        $occupantIds = $properties->pluck('primary_occupant_id')->filter()->all();

        return AccessRiskIncident::where(function ($q) use ($occupantIds) {
            if (! empty($occupantIds)) {
                $q->whereIn('user_id', $occupantIds);
            }
        })
            ->latest('occurred_at')
            ->get()
            ->map(fn (AccessRiskIncident $i) => [
                'id' => $i->id,
                'title' => $i->title,
                'gate' => $i->gate ?: 'Gate 01',
                'flag_type' => $i->flag_type,
                'risk_level' => strtoupper($i->risk_level ?: 'LOW'),
                'description' => $i->description,
                'status' => strtoupper($i->status ?: 'RESOLVED'),
                'resolution_notes' => $i->resolution_notes,
                'occurred_at' => $i->occurred_at?->format('M d, Y H:i') ?? 'Recent',
            ])
            ->values()
            ->all();
    }

    /**
     * Payments: Invoices, maintenance dues, HOA assessments, payment receipts.
     */
    private function getScopedPayments(array $propertyIds): array
    {
        return Invoice::whereIn('property_id', $propertyIds)
            ->with('property')
            ->latest('due_on')
            ->get()
            ->map(fn (Invoice $inv) => [
                'id' => $inv->id,
                'reference' => $inv->reference ?: "INV-{$inv->id}",
                'unit' => $inv->property?->lot_number ?: "Unit {$inv->property_id}",
                'property_id' => $inv->property_id,
                'amount_formatted' => '$'.number_format($inv->amount_minor / 100, 2).' '.$inv->currency,
                'amount_minor' => $inv->amount_minor,
                'currency' => $inv->currency,
                'due_on' => $inv->due_on?->format('M d, Y') ?? 'On receipt',
                'paid_at' => $inv->paid_at?->format('M d, Y H:i'),
                'status' => $inv->status ?: 'pending',
                'status_label' => ucfirst($inv->status ?: 'pending'),
                'period' => $inv->period_start && $inv->period_end
                    ? $inv->period_start->format('M Y')
                    : 'Assessment Dues',
            ])
            ->values()
            ->all();
    }

    /**
     * Documents: Corporate articles, title deeds, resolutions, lease contracts.
     */
    private function getCompanyDocuments(OwnershipEntity $company): array
    {
        return AuthorizationDocument::where('ownership_entity_id', $company->id)
            ->latest('issued_at')
            ->get()
            ->map(fn (AuthorizationDocument $doc) => [
                'id' => $doc->id,
                'title' => $doc->title,
                'document_type' => $doc->document_type,
                'document_type_label' => ucwords(str_replace('_', ' ', $doc->document_type)),
                'holder_name' => $doc->holder_name ?: $company->legal_name,
                'file_name' => $doc->file_name ?: 'document.pdf',
                'file_size_formatted' => $this->formatBytes($doc->file_size_bytes),
                'mime_type' => $doc->mime_type ?: 'application/pdf',
                'issued_at' => $doc->issued_at?->format('M d, Y') ?? 'N/A',
                'expires_at' => $doc->expires_at?->format('M d, Y') ?? 'Permanent',
                'verification_status' => $doc->verification_status ?: 'verified',
                'status' => $doc->status ?: 'active',
            ])
            ->values()
            ->all();
    }

    private function formatBytes(?int $bytes): string
    {
        if (! $bytes) {
            return '1.2 MB';
        }
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 1).' '.$units[$i];
    }
}
