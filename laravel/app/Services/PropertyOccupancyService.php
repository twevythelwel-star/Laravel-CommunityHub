<?php

namespace App\Services;

use App\Enums\GateId;
use App\Enums\PassCategory;
use App\Enums\PassStatus;
use App\Enums\UserRole;
use App\Enums\VisitorStatus;
use App\Models\DelegatedAccess;
use App\Models\GatePass;
use App\Models\Property;
use App\Models\Renter;
use App\Models\User;
use App\Models\Visitor;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PropertyOccupancyService
{
    /**
     * Map a pass, visitor, user, or delegated entity to the 7 primary community occupancy categories:
     * 1. residents
     * 2. long_term_guests (Long-Term Guests / Renters)
     * 3. short_term_guests (Short-Term Rental Guests / Airbnb)
     * 4. visitors
     * 5. staff
     * 6. contractors
     * 7. legacy_contacts
     */
    public function resolveCategory(GatePass|Visitor|User|DelegatedAccess|Renter|array $entity): string
    {
        if ($entity instanceof Visitor) {
            $type = strtolower($entity->type ?? '');
            if (str_contains($type, 'short') || str_contains($type, 'airbnb')) {
                return 'short_term_guests';
            }

            return 'visitors';
        }

        if ($entity instanceof Renter) {
            $stay = strtolower($entity->stay_type ?? '');
            $notes = strtolower($entity->notes ?? '');
            if (str_contains($stay, 'airbnb') || str_contains($stay, 'short') || str_contains($notes, 'airbnb')) {
                return 'short_term_guests';
            }

            return 'long_term_guests';
        }

        if ($entity instanceof DelegatedAccess) {
            $level = strtolower($entity->access_level ?? '');
            $rel = strtolower($entity->relationship ?? '');
            if (str_contains($level, 'short') || str_contains($rel, 'short') || str_contains($rel, 'airbnb')) {
                return 'short_term_guests';
            }
            if (str_contains($level, 'long') || str_contains($rel, 'long')) {
                return 'long_term_guests';
            }
            if (str_contains($level, 'contractor') || str_contains($rel, 'contractor')) {
                return 'contractors';
            }
            if (str_contains($level, 'staff') || str_contains($level, 'caregiver') || str_contains($rel, 'domestic') || str_contains($rel, 'staff')) {
                return 'staff';
            }

            return 'legacy_contacts';
        }

        if ($entity instanceof User) {
            if ($entity->role === UserRole::Staff || $entity->role === UserRole::Security) {
                return 'staff';
            }

            if ($entity->role === UserRole::TemporaryHomeowner) {
                return 'long_term_guests';
            }

            return 'residents';
        }

        if (is_array($entity)) {
            $cat = $entity['categoryKey'] ?? $entity['category'] ?? null;
            if ($cat && in_array($cat, ['residents', 'long_term_guests', 'short_term_guests', 'visitors', 'staff', 'contractors', 'legacy_contacts'], true)) {
                return $cat;
            }
        }

        // GatePass
        $pass = $entity;
        $category = $pass->category;
        $authType = strtolower($pass->metadata['authorization_type'] ?? '');
        $rel = strtolower($pass->metadata['relationship'] ?? '');
        $level = strtolower($pass->metadata['access_level'] ?? '');

        // 1. Short-Term Rental Guests (Airbnb, VRBO)
        if ($authType === 'short_term_rental' || str_contains($rel, 'airbnb') || str_contains($rel, 'short-term') || str_contains($rel, 'short term') || str_contains($level, 'airbnb') || str_contains($level, 'short-term')) {
            return 'short_term_guests';
        }

        // 2. Long-Term Guests / Renters
        if ($category === PassCategory::LongTermOccupant || $authType === 'long_term_occupant' || str_contains($rel, 'long-term') || str_contains($rel, 'long term') || str_contains($level, 'long-term') || $category === PassCategory::Renter) {
            return 'long_term_guests';
        }

        // 3. Visitors
        if ($category === PassCategory::Visitor) {
            return 'visitors';
        }

        // 4. Staff
        if (in_array($category, [PassCategory::Staff, PassCategory::Security, PassCategory::HomeownerStaff], true) || str_contains($level, 'staff') || str_contains($level, 'caregiver')) {
            return 'staff';
        }

        // 5. Contractors
        if ($category === PassCategory::Contractor || str_contains($rel, 'contractor') || str_contains($level, 'contractor')) {
            return 'contractors';
        }

        // 6. Legacy Contacts / Delegates
        if ($category === PassCategory::Delegate || ! empty($pass->metadata['delegated_access_id']) || str_contains($level, 'legacy') || str_contains($level, 'emergency') || str_contains($rel, 'attorney') || str_contains($rel, 'executor')) {
            return 'legacy_contacts';
        }

        // 7. Residents (Homeowners / System Admins)
        if (in_array($category, [PassCategory::Homeowner, PassCategory::SysAdmin, PassCategory::Admin], true)) {
            return 'residents';
        }

        return 'visitors';
    }

    /**
     * Category human-readable label.
     */
    public function categoryLabel(string $categoryKey): string
    {
        return match ($categoryKey) {
            'residents' => 'Residents',
            'long_term_guests' => 'Long-Term Guests / Renters',
            'short_term_guests' => 'Short-Term Rental Guests',
            'visitors' => 'Visitors',
            'staff' => 'Staff',
            'contractors' => 'Contractors',
            'legacy_contacts' => 'Legacy Contacts',
            default => ucfirst(str_replace('_', ' ', $categoryKey)),
        };
    }

    /**
     * Get aggregate breakdown of people CURRENTLY INSIDE the community.
     * Optionally scoped to a specific unit / lot.
     */
    public function getSummary(?string $unit = null): array
    {
        $occupants = $this->getAllCurrentOccupants($unit);

        $counts = [
            'residents' => 0,
            'long_term_guests' => 0,
            'short_term_guests' => 0,
            'visitors' => 0,
            'staff' => 0,
            'contractors' => 0,
            'legacy_contacts' => 0,
        ];

        foreach ($occupants as $item) {
            $cat = $item['categoryKey'];
            if (isset($counts[$cat])) {
                $counts[$cat]++;
            }
        }

        $counts['total'] = array_sum($counts);

        return $counts;
    }

    /**
     * Get aggregate breakdown of people EXPECTED TODAY in the community.
     * Optionally scoped to a specific unit / lot.
     */
    public function getExpectedTodaySummary(?string $unit = null): array
    {
        $expected = $this->getAllExpectedToday($unit);

        $counts = [
            'residents' => 0,
            'long_term_guests' => 0,
            'short_term_guests' => 0,
            'visitors' => 0,
            'staff' => 0,
            'contractors' => 0,
            'legacy_contacts' => 0,
        ];

        foreach ($expected as $item) {
            $cat = $item['categoryKey'];
            if (isset($counts[$cat])) {
                $counts[$cat]++;
            }
        }

        $counts['total'] = array_sum($counts);

        return $counts;
    }

    /**
     * Combined Dual Summary: Both "Currently Inside" + "Expected Today"
     */
    public function getDualSummary(?string $unit = null): array
    {
        return [
            'inside' => $this->getSummary($unit),
            'expected' => $this->getExpectedTodaySummary($unit),
        ];
    }

    /**
     * Normalize property / unit string (e.g. "Unit 14", "Lot 14", "#14", "14" -> normalized identifier).
     */
    /**
     * One group for the estate's own people: Security, Staff, and
     * administrators who do not live here. Their accounts carry their post
     * ("Gatehouse 1", "Control Room", "HQ-01") where a resident has a lot, so
     * each post was counted as a unit. An administrator who owns a home here
     * is counted at it, like any owner.
     */
    public const ESTATE_OPERATIONS = 'Estate Operations';

    private function isEstatePersonnel(GatePass $pass): bool
    {
        $user = $pass->user;

        return $user !== null
            && $pass->visitor_id === null
            && $pass->delegated_access_id === null
            && ($user->role->isAdministrative() || $user->role->isOperational())
            && $user->properties->isEmpty();
    }

    public function normalizeUnit(?string $raw): ?string
    {
        if (empty($raw)) {
            return null;
        }

        $trimmed = trim($raw);
        // Repeated prefixes too: passes were stored as "Unit Lot 42".
        if (preg_match('/^(?:(?:Unit|Lot|#)\s*)+([A-Za-z0-9\-_]+)/i', $trimmed, $m)) {
            return 'Unit '.$m[1];
        }

        if (preg_match('/^(\d+[A-Za-z]?)\s*(?:,|$)/', $trimmed, $m)) {
            return 'Unit '.$m[1];
        }

        return $trimmed;
    }

    /**
     * Model the underlying access relationship chain:
     * Person → Property → Relationship → Authorization → Visibility → Permissions
     */
    public function buildRelationshipChain(array $item): array
    {
        $cat = $item['categoryKey'] ?? 'visitors';
        $role = $item['role'] ?? $this->categoryLabel($cat);
        $name = $item['name'] ?? 'Authorized Person';
        $property = $item['property'] ?? $item['unit'] ?? 'Estate Property';
        $host = $item['host'] ?? $item['authorizedBy'] ?? 'Property Owner';
        $accessType = $item['accessType'] ?? match ($cat) {
            'legacy_contacts' => 'Emergency & Legacy Access',
            'short_term_guests' => 'Airbnb Reservation',
            'long_term_guests' => 'Residential Lease Agreement',
            'staff' => 'Estate Staff Clearance',
            'contractors' => 'Authorized Vendor Permit',
            'residents' => 'Primary Resident Title',
            default => 'Visitor Pass',
        };

        // Determine explicit permissions and boundaries
        $can = [];
        $cannot = [];

        switch ($cat) {
            case 'legacy_contacts':
                $can = ['Enter property', 'Receive emergency notifications', 'Authorize visitors during emergency'];
                $cannot = ['View community directory', 'View other properties', 'Modify property ownership'];
                break;
            case 'long_term_guests':
                $can = ['Enter property', 'Manage own visitors', 'See authorized property staff', 'See authorized property contractors', 'Manage own legacy contacts'];
                $cannot = ['View other properties', 'View community residents', 'Remove homeowner', 'Change property ownership', 'View homeowner financials'];
                break;
            case 'short_term_guests':
                $can = ['Enter property during stay', 'Generate visitor passes for own guests', 'View assigned property services'];
                $cannot = ['View homeowner legacy contacts', 'View unrelated residents', 'View community-wide contractor list', 'Access other properties'];
                break;
            case 'visitors':
                $can = ['Enter property at designated gate', 'Park in guest stall', 'Stay until pass expiration'];
                $cannot = ['Authorize other visitors', 'View community records', 'Access off-limits facilities'];
                break;
            case 'contractors':
                $can = ['Enter via service lane during permitted work hours', 'Access designated work parcel'];
                $cannot = ['Enter outside working hours', 'Access residential amenities', 'View community data'];
                break;
            case 'staff':
                $can = ['Enter estate for authorized duties', 'Verify gate credentials', 'Respond to site incidents'];
                $cannot = ['Access private residences without dispatch clearance'];
                break;
            case 'residents':
                $can = ['Full access to own property', 'Manage all occupants and visitors', 'Access community amenities', 'Authorize delegates'];
                $cannot = ['Access private lots of other homeowners'];
                break;
        }

        return [
            'person' => $name,
            'property' => $property,
            'relationship' => $role,
            'authorizedBy' => $host,
            'accessType' => $accessType,
            'visibilityScope' => in_array($cat, ['staff'], true) ? 'community' : 'property',
            'can' => $can,
            'cannot' => $cannot,
            'summary' => "{$name} → {$role} → {$property} → Authorized by {$host} → {$accessType}",
        ];
    }

    /**
     * Get all occupants CURRENTLY INSIDE the community with rich details.
     */
    /**
     * Everyone checked in and not checked out. A transient check-in older than
     * config('occupancy.stale_after_hours') is marked `stale` and left out
     * unless $includeStale — see staleCheckIns() and the muster roll call.
     */
    public function getAllCurrentOccupants(?string $unitFilter = null, ?string $categoryFilter = null, ?string $search = null, bool $includeStale = false): Collection
    {
        $records = collect();
        $seenPassIds = [];
        $seenVisitorIds = [];
        $seenUserIds = [];

        // 1. GatePass records that are CheckedIn
        $passes = GatePass::with(['user.properties', 'visitor', 'delegatedAccess'])
            ->where('status', PassStatus::CheckedIn)
            ->get();

        foreach ($passes as $pass) {
            $catKey = $this->resolveCategory($pass);
            $property = $pass->property ?: ($pass->user?->lot ?: $pass->visitor?->homeowner?->lot);
            $normalizedUnit = $this->normalizeUnit($property) ?: ($pass->user?->role->isResident() && ! $pass->visitor_id ? 'Unassigned' : 'Common Grounds');
            if ($this->isEstatePersonnel($pass)) {
                $property = $normalizedUnit = self::ESTATE_OPERATIONS;
            }

            $hostName = $pass->metadata['host_name'] ?? $pass->metadata['authorized_by'] ?? $pass->metadata['grantor_name'] ?? ($pass->visitor?->homeowner_name ?? $pass->visitor?->homeowner?->name);

            $seenPassIds[] = $pass->id;
            if ($pass->visitor_id) {
                $seenVisitorIds[] = $pass->visitor_id;
            }
            if ($pass->user_id) {
                $seenUserIds[] = $pass->user_id;
            }

            $accessType = $pass->metadata['access_type'] ?? match ($catKey) {
                'legacy_contacts' => ($pass->metadata['access_level'] ?? 'Emergency'),
                'short_term_guests' => 'Airbnb Guest',
                'long_term_guests' => 'Long-Term Lease',
                'contractors' => 'Contractor Pass',
                'staff' => 'Staff Clearance',
                default => 'Visitor Pass',
            };

            // No end date is shown as such, not as an invented one ("Oct 14, 2026").
            $expiration = $pass->valid_until ? $pass->valid_until->format('M j, Y') : ($pass->metadata['expiration'] ?? 'No end date');

            $role = match ($catKey) {
                'legacy_contacts' => ($pass->metadata['role'] ?? $pass->metadata['relationship'] ?? 'Legacy Contact'),
                default => ($pass->metadata['role'] ?? $pass->metadata['access_level'] ?? $pass->metadata['relationship'] ?? $this->categoryLabel($catKey)),
            };

            $record = [
                'id' => 'pass_'.$pass->id,
                'type' => 'gate_pass',
                'passId' => $pass->pass_id,
                'name' => $pass->holder_name ?: ($pass->user?->name ?? $pass->visitor?->name ?? 'Occupant'),
                'categoryKey' => $catKey,
                'categoryLabel' => $this->categoryLabel($catKey),
                'role' => $role,
                'property' => $property ?: $normalizedUnit,
                'unit' => $normalizedUnit,
                'authorizedFor' => $normalizedUnit,
                'host' => $hostName,
                'authorizedBy' => $hostName ?: 'Property Owner',
                'accessType' => $accessType,
                'qrStatus' => 'Active',
                'checkedInAt' => $pass->checked_in_at?->toIso8601String() ?? $pass->updated_at->toIso8601String(),
                'checkedInTime' => $pass->checked_in_at?->format('g:i A') ?? $pass->updated_at->format('g:i A'),
                'checkedInDuration' => $pass->checked_in_at ? $pass->checked_in_at->diffForHumans(['parts' => 2, 'short' => true]) : 'Active',
                'gate' => ($pass->designated_gate === GateId::Gate01 || $pass->designated_gate?->value === 'GATE-01') ? 'Main Gate' : ($pass->metadata['gate'] ?? 'Main Gate'),
                'vehicle' => $pass->metadata['vehicle'] ?? $pass->visitor?->vehicle ?? null,
                'contact' => $pass->metadata['contact'] ?? $pass->user?->phone ?? $pass->visitor?->contact ?? null,
                'avatarUrl' => $pass->user?->avatar_url ?? null,
                'status' => 'Checked In',
                'checkInStatus' => 'INSIDE',
                'expiration' => $expiration,
            ];

            $record['relationshipChain'] = $this->buildRelationshipChain($record);
            $records->push($record);
        }

        // 2. Visitors with status 'Checked In' not already captured by pass
        $visitors = Visitor::with('homeowner')
            ->where('status', VisitorStatus::CheckedIn)
            ->whereNotIn('id', $seenVisitorIds)
            ->get();

        foreach ($visitors as $v) {
            $catKey = 'visitors';
            $property = $v->homeowner?->lot ?: null;
            $normalizedUnit = $this->normalizeUnit($property) ?: 'Common Grounds';
            $host = $v->homeowner_name ?? $v->homeowner?->name;

            $record = [
                'id' => 'visitor_'.$v->id,
                'type' => 'visitor',
                'passId' => sprintf('VIS-%04d', $v->id),
                'name' => $v->name,
                'categoryKey' => $catKey,
                'categoryLabel' => 'Visitors',
                'role' => 'Visitor',
                'property' => $property ?: $normalizedUnit,
                'unit' => $normalizedUnit,
                'authorizedFor' => $normalizedUnit,
                'host' => $host,
                'authorizedBy' => $host ?: 'Homeowner',
                'accessType' => 'Guest Pass',
                'qrStatus' => 'Active',
                'checkedInAt' => $v->checked_in_at?->toIso8601String() ?? $v->updated_at->toIso8601String(),
                'checkedInTime' => $v->checked_in_at?->format('g:i A') ?? $v->updated_at->format('g:i A'),
                'checkedInDuration' => $v->checked_in_at ? $v->checked_in_at->diffForHumans(['parts' => 2, 'short' => true]) : 'Active',
                'gate' => 'Main Gate',
                'vehicle' => $v->vehicle,
                'contact' => $v->contact,
                'avatarUrl' => null,
                'status' => 'Checked In',
                'checkInStatus' => 'INSIDE',
                'expiration' => now()->endOfDay()->format('M j, Y'),
            ];

            $record['relationshipChain'] = $this->buildRelationshipChain($record);
            $records->push($record);
        }

        // 3. Resident users who have active gate pass or resident on-site record
        $residentUsers = User::query()
            ->whereIn('role', [UserRole::Homeowner, UserRole::TemporaryHomeowner])
            ->where('status', 'Active')
            ->whereNotIn('id', $seenUserIds)
            ->whereHas('gatePasses', function ($q) {
                $q->where('status', PassStatus::CheckedIn);
            })
            ->get();

        foreach ($residentUsers as $resUser) {
            $pass = $resUser->gatePasses()->whereIn('status', [PassStatus::CheckedIn, PassStatus::Active])->first();
            // "Lot Unassigned" normalised to a unit called "Unit Unassigned".
            $property = $resUser->lot ?: 'Unassigned';
            $normalizedUnit = $resUser->lot ? $this->normalizeUnit($resUser->lot) : 'Unassigned';
            $isRenter = $resUser->role === UserRole::TemporaryHomeowner;
            $catKey = $isRenter ? 'long_term_guests' : 'residents';

            $record = [
                'id' => 'user_'.$resUser->id,
                'type' => 'resident',
                'passId' => $pass?->pass_id ?? sprintf('RES-%04d', $resUser->id),
                'name' => $resUser->name,
                'categoryKey' => $catKey,
                'categoryLabel' => $this->categoryLabel($catKey),
                'role' => $isRenter ? 'Renter' : 'Homeowner',
                'property' => $property,
                'unit' => $normalizedUnit,
                'authorizedFor' => $normalizedUnit,
                'host' => $resUser->name,
                'authorizedBy' => $resUser->name,
                'accessType' => $isRenter ? 'Residential Lease' : 'Homeowner Resident',
                'qrStatus' => 'Active',
                'checkedInAt' => $pass?->checked_in_at?->toIso8601String() ?? $resUser->created_at->toIso8601String(),
                'checkedInTime' => $pass?->checked_in_at?->format('g:i A') ?? 'On-Site',
                'checkedInDuration' => 'Permanent Resident',
                'gate' => 'Residents Lane',
                'vehicle' => null,
                'contact' => $resUser->phone ?? $resUser->email,
                'avatarUrl' => $resUser->avatar_url,
                'status' => 'On-Site',
                'checkInStatus' => 'INSIDE',
                'expiration' => 'Permanent',
            ];

            $record['relationshipChain'] = $this->buildRelationshipChain($record);
            $records->push($record);
        }

        // A visitor who left without checking out looked "inside" forever.
        $records = $records->map(fn (array $item) => $item + ['stale' => $this->isStaleCheckIn($item)]);
        if (! $includeStale) {
            $records = $records->reject(fn (array $item) => $item['stale']);
        }

        // Filter by unit if requested
        if (! empty($unitFilter)) {
            $wanted = strtolower((string) $this->normalizeUnit($unitFilter));

            $records = $records->filter(fn ($item) => in_array($wanted, [
                strtolower((string) $this->normalizeUnit($item['unit'] ?? null)),
                strtolower((string) $this->normalizeUnit($item['property'] ?? null)),
            ], true));
        }

        // Filter by category if requested
        if (! empty($categoryFilter) && $categoryFilter !== 'all') {
            $records = $records->where('categoryKey', $categoryFilter);
        }

        // Filter by search string if requested
        if (! empty($search)) {
            $term = strtolower(trim($search));
            $records = $records->filter(function ($item) use ($term) {
                return str_contains(strtolower($item['name'] ?? ''), $term) ||
                    str_contains(strtolower($item['passId'] ?? ''), $term) ||
                    str_contains(strtolower($item['property'] ?? ''), $term) ||
                    str_contains(strtolower($item['unit'] ?? ''), $term) ||
                    str_contains(strtolower($item['vehicle'] ?? ''), $term) ||
                    str_contains(strtolower($item['contact'] ?? ''), $term) ||
                    str_contains(strtolower($item['host'] ?? ''), $term);
            });
        }

        return $records->values();
    }

    /**
     * Get all people EXPECTED TODAY who have valid credentials but have not checked in yet,
     * or are scheduled for arrival today.
     */
    public function getAllExpectedToday(?string $unitFilter = null, ?string $categoryFilter = null, ?string $search = null): Collection
    {
        $records = collect();
        $seenPassIds = [];

        // 1. Visitors scheduled or expected today with status 'Expected'
        $expectedVisitors = Visitor::with('homeowner')
            ->whereIn('status', [VisitorStatus::Expected])
            ->get();

        foreach ($expectedVisitors as $v) {
            $catKey = 'visitors';
            $property = $v->homeowner?->lot ?: null;
            $normalizedUnit = $this->normalizeUnit($property) ?: 'Common Grounds';
            $host = $v->homeowner_name ?? $v->homeowner?->name;

            $record = [
                'id' => 'exp_vis_'.$v->id,
                'type' => 'expected_visitor',
                'passId' => sprintf('VIS-%04d', $v->id),
                'name' => $v->name,
                'categoryKey' => $catKey,
                'categoryLabel' => 'Visitors',
                'role' => 'Scheduled Visitor',
                'property' => $property ?: $normalizedUnit,
                'unit' => $normalizedUnit,
                'host' => $host,
                'authorizedBy' => $host ?: 'Homeowner',
                'accessType' => 'Expected Guest Pass',
                'qrStatus' => 'Scheduled',
                'checkedInAt' => null,
                'checkedInTime' => 'Expected Today',
                'checkedInDuration' => 'Pending Entry',
                'gate' => 'Main Gate',
                'vehicle' => $v->vehicle,
                'contact' => $v->contact,
                'avatarUrl' => null,
                'status' => 'Expected Today',
                'checkInStatus' => 'EXPECTED',
                'expiration' => now()->endOfDay()->format('M j, Y'),
            ];

            $record['relationshipChain'] = $this->buildRelationshipChain($record);
            $records->push($record);
        }

        // 2. Active gate passes not currently checked in
        $activePasses = GatePass::with(['user.properties', 'visitor', 'delegatedAccess'])
            ->whereIn('status', [PassStatus::Active, PassStatus::Issued, PassStatus::Approved])
            ->get();

        foreach ($activePasses as $pass) {
            $catKey = $this->resolveCategory($pass);
            $property = $pass->property ?: ($pass->user?->lot ?: $pass->visitor?->homeowner?->lot);
            $normalizedUnit = $this->normalizeUnit($property) ?: ($pass->user?->role->isResident() && ! $pass->visitor_id ? 'Unassigned' : 'Common Grounds');
            if ($this->isEstatePersonnel($pass)) {
                $property = $normalizedUnit = self::ESTATE_OPERATIONS;
            }

            $hostName = $pass->metadata['host_name'] ?? $pass->metadata['authorized_by'] ?? ($pass->visitor?->homeowner_name ?? $pass->visitor?->homeowner?->name);

            $record = [
                'id' => 'exp_pass_'.$pass->id,
                'type' => 'expected_pass',
                'passId' => $pass->pass_id,
                'name' => $pass->holder_name ?: ($pass->user?->name ?? $pass->visitor?->name ?? 'Expected Occupant'),
                'categoryKey' => $catKey,
                'categoryLabel' => $this->categoryLabel($catKey),
                'role' => $pass->metadata['access_level'] ?? $pass->metadata['relationship'] ?? $this->categoryLabel($catKey),
                'property' => $property ?: $normalizedUnit,
                'unit' => $normalizedUnit,
                'host' => $hostName,
                'authorizedBy' => $hostName ?: 'Property Owner',
                'accessType' => $pass->metadata['access_level'] ?? 'Credential Clearance',
                'qrStatus' => 'Active',
                'checkedInAt' => null,
                'checkedInTime' => 'Not Checked In',
                'checkedInDuration' => 'Valid Today',
                'gate' => $pass->designated_gate?->value ?? 'Main Gate',
                'vehicle' => $pass->metadata['vehicle'] ?? null,
                'contact' => $pass->metadata['contact'] ?? null,
                'avatarUrl' => null,
                'status' => 'Expected Today',
                'checkInStatus' => 'EXPECTED',
                'expiration' => $pass->valid_until ? $pass->valid_until->format('M j, Y') : 'Valid Today',
            ];

            $record['relationshipChain'] = $this->buildRelationshipChain($record);
            $records->push($record);
        }

        // 3. Active Renters (Lease or Short-Term) not already checked in
        $activeRenters = Renter::where('lease_end', '>=', now())
            ->where('lease_start', '<=', now())
            ->with('homeowner')
            ->get();

        foreach ($activeRenters as $r) {
            $catKey = $this->resolveCategory($r);
            // Their own lot, else their homeowner's. It defaulted to "Unit 14",
            // and "Lot 42" became "Unit Lot 42", which normalised to "Unit Lot".
            $lot = $r->lot ?: $r->homeowner?->lot;
            $normalizedUnit = $this->normalizeUnit($lot) ?: 'Common Grounds';
            $property = $lot ? $normalizedUnit : 'Unassigned';

            $record = [
                'id' => 'exp_renter_'.$r->id,
                'type' => 'expected_renter',
                'passId' => sprintf('RNT-%04d', $r->id),
                'name' => $r->name,
                'categoryKey' => $catKey,
                'categoryLabel' => $this->categoryLabel($catKey),
                'role' => $catKey === 'short_term_guests' ? 'Airbnb Guest' : 'Lease Tenant',
                'property' => $property,
                'unit' => $normalizedUnit,
                'host' => 'Property Owner',
                'authorizedBy' => 'Property Owner',
                'accessType' => $catKey === 'short_term_guests' ? 'Airbnb Reservation' : 'Active Lease',
                'qrStatus' => 'Active',
                'checkedInAt' => null,
                'checkedInTime' => 'Expected Today',
                'checkedInDuration' => 'Lease Current',
                'gate' => 'Main Gate',
                'vehicle' => null,
                'contact' => $r->contact,
                'avatarUrl' => null,
                'status' => 'Expected Today',
                'checkInStatus' => 'EXPECTED',
                'expiration' => $r->lease_end ? Carbon::parse($r->lease_end)->format('M j, Y') : 'Current Lease',
            ];

            $record['relationshipChain'] = $this->buildRelationshipChain($record);
            $records->push($record);
        }

        // Filter by unit if requested
        if (! empty($unitFilter)) {
            $wanted = strtolower((string) $this->normalizeUnit($unitFilter));

            $records = $records->filter(fn ($item) => in_array($wanted, [
                strtolower((string) $this->normalizeUnit($item['unit'] ?? null)),
                strtolower((string) $this->normalizeUnit($item['property'] ?? null)),
            ], true));
        }

        // Filter by category if requested
        if (! empty($categoryFilter) && $categoryFilter !== 'all') {
            $records = $records->where('categoryKey', $categoryFilter);
        }

        // Filter by search string if requested
        if (! empty($search)) {
            $term = strtolower(trim($search));
            $records = $records->filter(function ($item) use ($term) {
                return str_contains(strtolower($item['name'] ?? ''), $term) ||
                    str_contains(strtolower($item['passId'] ?? ''), $term) ||
                    str_contains(strtolower($item['property'] ?? ''), $term) ||
                    str_contains(strtolower($item['unit'] ?? ''), $term);
            });
        }

        return $records->values();
    }

    /**
     * Get list of all distinct units/lots currently occupied with counts.
     */
    public function getUnitsSummary(): array
    {
        $allInside = $this->getAllCurrentOccupants();
        $allExpected = $this->getAllExpectedToday();

        $allUnits = $allInside->pluck('unit')->merge($allExpected->pluck('unit'))->unique()->filter()->values();

        $result = [];
        foreach ($allUnits as $unitName) {
            $insideForUnit = $allInside->where('unit', $unitName);
            $expectedForUnit = $allExpected->where('unit', $unitName);

            $unitCounts = [
                'unit' => $unitName,
                'total' => $insideForUnit->count(),
                'currentlyInside' => $insideForUnit->count(),
                'expectedToday' => $expectedForUnit->count(),
                'residents' => $insideForUnit->where('categoryKey', 'residents')->count(),
                'long_term_guests' => $insideForUnit->where('categoryKey', 'long_term_guests')->count(),
                'short_term_guests' => $insideForUnit->where('categoryKey', 'short_term_guests')->count(),
                'visitors' => $insideForUnit->where('categoryKey', 'visitors')->count(),
                'staff' => $insideForUnit->where('categoryKey', 'staff')->count(),
                'contractors' => $insideForUnit->where('categoryKey', 'contractors')->count(),
                'legacy_contacts' => $insideForUnit->where('categoryKey', 'legacy_contacts')->count(),
            ];
            $result[] = $unitCounts;
        }

        usort($result, fn ($a, $b) => $b['total'] <=> $a['total']);

        return $result;
    }

    /**
     * Dedicated Drill-down API endpoint: "Who's inside Unit 14?"
     * Returns structured roster by the 7 categories with IN / OUT / EXPECTED statuses.
     */
    public function getUnitDrilldown(string $unit): array
    {
        $normalized = $this->normalizeUnit($unit) ?: $unit;
        $inside = $this->getAllCurrentOccupants(unitFilter: $unit);
        $expected = $this->getAllExpectedToday(unitFilter: $unit);

        // Also check any checked-out visitors today for complete context
        $checkedOutVisitors = Visitor::with('homeowner')
            ->where('status', VisitorStatus::CheckedOut)
            ->get()
            ->filter(function ($v) use ($normalized) {
                if ($v->homeowner?->lot) {
                    return strtolower((string) $this->normalizeUnit($v->homeowner->lot)) === strtolower($normalized);
                }
                $prop = $v->homeowner_name ?: null;
                if ($prop && strtolower((string) $this->normalizeUnit($prop)) === strtolower($normalized)) {
                    return true;
                }
                if ($prop && str_contains(strtolower($prop), strtolower($normalized))) {
                    return true;
                }

                return false;
            });

        $categories = [
            'residents',
            'long_term_guests',
            'short_term_guests',
            'visitors',
            'staff',
            'contractors',
            'legacy_contacts',
        ];

        $rosterByCategory = [];
        $insideSummary = [];
        $expectedSummary = [];

        foreach ($categories as $cat) {
            $catInside = $inside->where('categoryKey', $cat)->values()->map(function ($item) {
                $item['status'] = 'IN';
                $item['checkInStatus'] = 'INSIDE';

                return $item;
            })->all();

            $catExpected = $expected->where('categoryKey', $cat)->values()->map(function ($item) {
                $item['status'] = 'EXPECTED';
                $item['checkInStatus'] = 'EXPECTED';

                return $item;
            })->all();

            $items = array_merge($catInside, $catExpected);

            // If category is visitors, append checked-out visitors as 'OUT'
            if ($cat === 'visitors') {
                foreach ($checkedOutVisitors as $co) {
                    $items[] = [
                        'id' => 'co_vis_'.$co->id,
                        'name' => $co->name,
                        'categoryKey' => 'visitors',
                        'categoryLabel' => 'Visitors',
                        'role' => 'Visitor',
                        'property' => $normalized,
                        'unit' => $normalized,
                        'status' => 'OUT',
                        'checkInStatus' => 'OUT',
                        'authorizedBy' => $co->homeowner_name ?? $co->homeowner?->name ?? 'Host',
                        'accessType' => 'Visitor Pass',
                        'qrStatus' => 'Checked Out',
                        'checkInTime' => $co->checked_in_at?->format('g:i A') ?? 'Earlier Today',
                        'gate' => 'Main Gate',
                        'expiration' => 'Expired',
                        'relationshipChain' => $this->buildRelationshipChain([
                            'name' => $co->name,
                            'categoryKey' => 'visitors',
                            'property' => $normalized,
                            'host' => $co->homeowner_name ?? $co->homeowner?->name,
                        ]),
                    ];
                }
            }

            $rosterByCategory[$cat] = $items;
            $insideSummary[$cat] = count($catInside);
            $expectedSummary[$cat] = count($catExpected);
        }

        $insideSummary['total'] = $inside->count();
        $expectedSummary['total'] = $expected->count();

        return [
            'unit' => $normalized,
            'requestedQuery' => $unit,
            'summary' => $insideSummary,
            'expectedSummary' => $expectedSummary,
            'total' => $inside->count(),
            'totalCurrentlyInside' => $inside->count(),
            'totalExpectedToday' => $expected->count(),
            'occupants' => $inside->values()->all(),
            'expected' => $expected->values()->all(),
            'rosterByCategory' => $rosterByCategory,
        ];
    }

    /**
     * Community Map & Spatial Hierarchy Tree.
     * Provides structured hierarchical data:
     * COMMUNITY
     *   ├── Unit 14 (5 inside, 8 expected)
     *   │     ├── 2 Residents
     *   │     ├── 1 Renter
     *   │     ├── 1 Visitor
     *   │     └── 1 Legacy Contact
     *   └── Unit 15 (3 inside, 4 expected)
     *
     * If $scopedUnit is provided (non-staff user), ONLY their single authorized unit is returned.
     */
    public function getCommunityHierarchy(?string $scopedUnit = null): array
    {
        $allInside = $this->getAllCurrentOccupants($scopedUnit);
        $allExpected = $this->getAllExpectedToday($scopedUnit);

        $distinctUnits = $allInside->pluck('unit')->merge($allExpected->pluck('unit'))->unique()->filter()->values();

        // Counts come from the occupants found, for every unit. Units 14 and 15
        // used to report fixed numbers ("5 inside, 8 expected") whatever was
        // true, and an empty estate showed three invented units.
        if ($scopedUnit) {
            $norm = $this->normalizeUnit($scopedUnit) ?: $scopedUnit;
            $distinctUnits = collect([$norm]);
        }

        // Addresses from the property records; every one used to be
        // "{lot}, Royal Palm Way".
        $addresses = Property::query()
            ->get(['lot_number', 'street_address'])
            ->mapWithKeys(fn (Property $p) => [$this->normalizeUnit($p->lot_number) => $p->label()]);

        $propertyNodes = [];

        foreach ($distinctUnits as $unitName) {
            $insideForUnit = $allInside->where('unit', $unitName)->values();
            $expectedForUnit = $allExpected->where('unit', $unitName)->values();

            $insideCounts = [
                'residents' => $insideForUnit->where('categoryKey', 'residents')->count(),
                'long_term_guests' => $insideForUnit->where('categoryKey', 'long_term_guests')->count(),
                'short_term_guests' => $insideForUnit->where('categoryKey', 'short_term_guests')->count(),
                'visitors' => $insideForUnit->where('categoryKey', 'visitors')->count(),
                'staff' => $insideForUnit->where('categoryKey', 'staff')->count(),
                'contractors' => $insideForUnit->where('categoryKey', 'contractors')->count(),
                'legacy_contacts' => $insideForUnit->where('categoryKey', 'legacy_contacts')->count(),
            ];

            $expectedCounts = [
                'residents' => $expectedForUnit->where('categoryKey', 'residents')->count(),
                'long_term_guests' => $expectedForUnit->where('categoryKey', 'long_term_guests')->count(),
                'short_term_guests' => $expectedForUnit->where('categoryKey', 'short_term_guests')->count(),
                'visitors' => $expectedForUnit->where('categoryKey', 'visitors')->count(),
                'staff' => $expectedForUnit->where('categoryKey', 'staff')->count(),
                'contractors' => $expectedForUnit->where('categoryKey', 'contractors')->count(),
                'legacy_contacts' => $expectedForUnit->where('categoryKey', 'legacy_contacts')->count(),
            ];

            $breakdown = [];
            foreach ($insideCounts as $k => $cnt) {
                if ($cnt > 0 || ($expectedCounts[$k] ?? 0) > 0) {
                    $shortLabel = match ($k) {
                        'residents' => ($cnt === 1 ? 'Resident' : 'Residents'),
                        'long_term_guests' => ($cnt === 1 ? 'Renter' : 'Renters'),
                        'short_term_guests' => ($cnt === 1 ? 'Short-Term Guest' : 'Short-Term Guests'),
                        'visitors' => ($cnt === 1 ? 'Visitor' : 'Visitors'),
                        'staff' => 'Staff',
                        'contractors' => ($cnt === 1 ? 'Contractor' : 'Contractors'),
                        'legacy_contacts' => ($cnt === 1 ? 'Legacy Contact' : 'Legacy Contacts'),
                        default => $this->categoryLabel($k),
                    };

                    $breakdown[] = [
                        'key' => $k,
                        'label' => $this->categoryLabel($k),
                        'shortLabel' => $shortLabel,
                        'branchLabel' => "{$cnt} {$shortLabel}",
                        'inside' => $cnt,
                        'expected' => $expectedCounts[$k] ?? 0,
                    ];
                }
            }

            preg_match('/(\d+)/', $unitName, $m);
            $lotNumber = $m[1] ?? '0';

            $propertyNodes[] = [
                'id' => 'prop_'.strtolower(str_replace(' ', '_', $unitName)),
                'unit' => $unitName,
                'lot' => $lotNumber,
                'address' => $addresses[$unitName] ?? $unitName,
                'insideCount' => $insideForUnit->count(),
                'expectedCount' => $expectedForUnit->count() + $insideForUnit->count(),
                'categoriesInside' => $insideCounts,
                'categoriesExpected' => $expectedCounts,
                'breakdown' => $breakdown,
                'occupants' => $insideForUnit->take(5)->map(fn ($o) => [
                    'name' => $o['name'],
                    'category' => $o['categoryLabel'],
                    'status' => 'IN',
                ])->all(),
            ];
        }

        usort($propertyNodes, fn ($a, $b) => $b['insideCount'] <=> $a['insideCount']);

        return [
            'communityName' => 'Community Access Command Center',
            'isScoped' => ! empty($scopedUnit),
            'scopedUnit' => $scopedUnit,
            'totalInside' => $allInside->count(),
            'totalExpected' => $allExpected->count(),
            'properties' => $propertyNodes,
        ];
    }

    /**
     * Get breakdown of occupants across the estate's physical security enclosures:
     * 1. Outer Perimeter Enclosure
     * 2. Residential Parcels Enclosure
     * 3. Clubhouse & Amenity Enclosure
     * 4. Trade & Contractor Enclosure
     * 5. Emergency Assembly Muster Enclosure
     */
    public function getEnclosureZonesSummary(): array
    {
        $all = $this->getAllCurrentOccupants();

        $perimeterCount = $all->count();
        $residentialCount = $all->filter(function ($o) {
            $unitLower = strtolower($o['unit'] ?? '');

            return in_array($o['categoryKey'], ['residents', 'long_term_guests', 'short_term_guests'], true) ||
                (! str_contains($unitLower, 'common') && ! str_contains($unitLower, 'gate') && ! str_contains($unitLower, 'park'));
        })->count();

        $amenityCount = $all->filter(function ($o) {
            $unitLower = strtolower($o['unit'] ?? '');

            return str_contains($unitLower, 'clubhouse') ||
                str_contains($unitLower, 'pool') ||
                str_contains($unitLower, 'park') ||
                str_contains($unitLower, 'common') ||
                $o['categoryKey'] === 'visitors';
        })->count();

        $contractorCount = $all->where('categoryKey', 'contractors')->count();
        $staffCount = $all->where('categoryKey', 'staff')->count();

        return [
            [
                'id' => 'outer_perimeter',
                'name' => 'Outer Perimeter Boundary Enclosure',
                'type' => 'Primary Boundary',
                'description' => 'Main Cadastral Security Fence Line & Automated Gates',
                'icon' => 'Shield',
                'status' => 'Fully Secured',
                'badge' => 'Active Containment',
                'occupant_count' => $perimeterCount,
            ],
            [
                'id' => 'residential_enclosures',
                'name' => 'Residential Parcel Enclosures',
                'type' => 'Private Compounds',
                'description' => 'Gated Villas, Townhomes & Private Lots (e.g. Unit 14)',
                'icon' => 'Home',
                'status' => 'Monitored',
                'badge' => 'Access Restricted',
                'occupant_count' => $residentialCount,
            ],
            [
                'id' => 'amenity_enclosure',
                'name' => 'Clubhouse & Amenity Enclosure',
                'type' => 'Recreational Zone',
                'description' => 'Swimming Pool, Gymnasium, Central Park & Pavilion',
                'icon' => 'Waves',
                'status' => 'Operational',
                'badge' => 'Keycard Access',
                'occupant_count' => $amenityCount,
            ],
            [
                'id' => 'contractor_enclosure',
                'name' => 'Trade & Construction Enclosure',
                'type' => 'Industrial Zone',
                'description' => 'Designated Worksites, Utility Compound & Maintenance Bays',
                'icon' => 'HardHat',
                'status' => 'Permit Required',
                'badge' => 'Authorized Hours',
                'occupant_count' => $contractorCount,
            ],
            [
                'id' => 'muster_enclosure',
                'name' => 'Emergency Assembly Muster Enclosure',
                'type' => 'Safety Zone',
                'description' => 'Central Park East Muster Field (Gate 01 Ingress)',
                'icon' => 'Flame',
                'status' => 'Cleared & Standby',
                'badge' => 'First Responders',
                'occupant_count' => 0,
            ],
        ];
    }

    /** Check-ins old enough that the person has most likely left without checking out. */
    public function staleCheckIns(?string $unitFilter = null): Collection
    {
        return $this->getAllCurrentOccupants(unitFilter: $unitFilter, includeStale: true)
            ->where('stale', true)
            ->values();
    }

    /**
     * Visitors, contractors, short-term guests and staff come and go; past the
     * configured age their check-in no longer says they are here. Residents,
     * long-term guests and delegates are never stale.
     */
    private function isStaleCheckIn(array $item): bool
    {
        if (! in_array($item['categoryKey'] ?? null, ['visitors', 'contractors', 'short_term_guests', 'staff'], true)) {
            return false;
        }

        if (empty($item['checkedInAt'])) {
            return false;
        }

        $hours = max(1, (int) config('occupancy.stale_after_hours', 24));

        return Carbon::parse($item['checkedInAt'])->lt(now()->subHours($hours));
    }
}
