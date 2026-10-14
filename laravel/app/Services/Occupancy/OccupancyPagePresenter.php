<?php

namespace App\Services\Occupancy;

use App\Models\MusterRollCall;
use App\Models\MusterSession;
use App\Models\User;
use App\Services\EmergencyMusterService;
use Illuminate\Support\Collection;

/**
 * The props of "Who's on property?" (Dashboard/Occupancy).
 *
 * Occupancy managers (the manageOccupancy gate) see the whole estate and may
 * pick any unit. Anyone else sees the estate totals and their own unit only,
 * whatever ?unit= says, and nothing when they have no unit: a null unit is
 * the whole estate to the census.
 */
class OccupancyPagePresenter
{
    private const PER_PAGE = 25;

    public function __construct(
        private readonly OccupancyCategoryResolver $categories,
        private readonly OccupancyCensusQueryService $census,
        private readonly OccupancyHierarchyBuilder $hierarchy,
        private readonly OccupancyMetricsService $metrics,
        private readonly EmergencyMusterService $muster,
    ) {}

    /**
     * @param  array{category?: string|null, search?: string|null, tab?: string|null, unit?: string|null, page?: int|null}  $filters
     * @return array<string, mixed>
     */
    public function props(User $user, array $filters): array
    {
        $manages = $user->can('manageOccupancy');
        $ownUnit = $this->categories->normalizeUnit($user->lot);
        $category = $filters['category'] ?: 'all';
        $search = $filters['search'] ?: null;
        $tab = $filters['tab'] ?: 'inside';
        $unit = $manages ? ($filters['unit'] ?: null) : $ownUnit;

        $list = ! ($manages || $unit) ? collect() : ($tab === 'expected'
            ? $this->census->getAllExpectedToday(unitFilter: $unit, categoryFilter: $category, search: $search)
            : $this->census->getAllCurrentOccupants(unitFilter: $unit, categoryFilter: $category, search: $search));

        return [
            'summary' => $this->metrics->getSummary(),
            'expectedSummary' => $this->metrics->getExpectedTodaySummary(),
            'unitSummary' => $unit ? $this->metrics->getSummary($unit) : null,
            'unitExpectedSummary' => $unit ? $this->metrics->getExpectedTodaySummary($unit) : null,
            'units' => $manages ? $this->metrics->getUnitsSummary() : [],
            'selectedUnit' => $unit,
            'selectedCategory' => $category,
            'search' => $search,
            'activeTab' => $tab,
            'communityHierarchy' => $this->hierarchy->getCommunityHierarchy($manages ? null : $ownUnit),
            'occupants' => $this->page($list, max(1, (int) ($filters['page'] ?? 1))),
            'canManage' => $manages,
            'userUnit' => $ownUnit,
            'activeMuster' => $this->activeMuster($user),
            'enclosureZones' => $manages ? $this->metrics->getEnclosureZonesSummary() : [],
            'lastUpdated' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array{data: Collection, total: int, current_page: int, per_page: int, last_page: int}
     */
    private function page(Collection $list, int $page): array
    {
        return [
            'data' => $list->forPage($page, self::PER_PAGE)->values(),
            'total' => $list->count(),
            'current_page' => $page,
            'per_page' => self::PER_PAGE,
            'last_page' => (int) ceil($list->count() / self::PER_PAGE),
        ];
    }

    /**
     * The running muster, with the roll calls this viewer may see: all of
     * them for occupancy managers, their own unit's for anyone else.
     *
     * @return array<string, mixed>|null
     */
    private function activeMuster(User $user): ?array
    {
        $session = $this->muster->getActiveSession();

        if (! $session) {
            return null;
        }

        return [
            'id' => $session->id,
            'incident_type' => $session->incident_type,
            'incident_label' => $session->incidentLabel(),
            'title' => $session->title,
            'assembly_point' => $session->assembly_point,
            'notes' => $session->notes,
            'started_at' => $session->started_at->toIso8601String(),
            'started_time' => $session->started_at->format('g:i A'),
            'initiator_name' => $session->initiator?->name ?? 'Security Dispatch',
            'counts' => $session->getCounts(),
            'roll_calls' => $this->visibleRollCalls($session, $user),
        ];
    }

    private function visibleRollCalls(MusterSession $session, User $user): Collection
    {
        return $session->rollCalls
            ->filter(fn (MusterRollCall $rc) => $user->can('viewUnitOccupancy', $rc->unit))
            ->values()
            ->map(fn (MusterRollCall $rc) => [
                'id' => $rc->id,
                'occupant_id' => $rc->occupant_id,
                'name' => $rc->occupant_name,
                'pass_id' => $rc->pass_id,
                'unit' => $rc->unit,
                'category' => $rc->category,
                'status' => $rc->status,
                'status_label' => $rc->statusLabel(),
                'contact' => $rc->contact,
                'vehicle' => $rc->vehicle,
                'notes' => $rc->notes,
                'marked_at' => $rc->marked_at?->toIso8601String(),
                'marked_by_name' => $rc->marker?->name,
            ]);
    }
}
