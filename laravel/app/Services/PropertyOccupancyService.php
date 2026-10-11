<?php

namespace App\Services;

use App\Models\DelegatedAccess;
use App\Models\GatePass;
use App\Models\Renter;
use App\Models\User;
use App\Models\Visitor;
use App\Services\Occupancy\OccupancyCategoryResolver;
use App\Services\Occupancy\OccupancyCensusQueryService;
use App\Services\Occupancy\OccupancyHierarchyBuilder;
use App\Services\Occupancy\OccupancyMetricsService;
use Illuminate\Support\Collection;

class PropertyOccupancyService
{
    public const ESTATE_OPERATIONS = 'Estate Operations';

    public function __construct(
        protected ?OccupancyCategoryResolver $categoryResolver = null,
        protected ?OccupancyCensusQueryService $censusQueryService = null,
        protected ?OccupancyHierarchyBuilder $hierarchyBuilder = null,
        protected ?OccupancyMetricsService $metricsService = null,
    ) {
        $this->categoryResolver ??= app(OccupancyCategoryResolver::class);
        $this->censusQueryService ??= app(OccupancyCensusQueryService::class);
        $this->hierarchyBuilder ??= app(OccupancyHierarchyBuilder::class);
        $this->metricsService ??= app(OccupancyMetricsService::class);
    }

    public function resolveCategory(GatePass|Visitor|User|DelegatedAccess|Renter|array $entity): string
    {
        return $this->categoryResolver->resolveCategory($entity);
    }

    public function categoryLabel(string $categoryKey): string
    {
        return $this->categoryResolver->categoryLabel($categoryKey);
    }

    public function normalizeUnit(?string $raw): ?string
    {
        return $this->categoryResolver->normalizeUnit($raw);
    }

    public function buildRelationshipChain(array $item): array
    {
        return $this->categoryResolver->buildRelationshipChain($item);
    }

    public function getSummary(?string $unit = null): array
    {
        return $this->metricsService->getSummary($unit);
    }

    public function getExpectedTodaySummary(?string $unit = null): array
    {
        return $this->metricsService->getExpectedTodaySummary($unit);
    }

    public function getDualSummary(?string $unit = null): array
    {
        return $this->metricsService->getDualSummary($unit);
    }

    public function getAllCurrentOccupants(?string $unitFilter = null, ?string $categoryFilter = null, ?string $search = null, bool $includeStale = false): Collection
    {
        return $this->censusQueryService->getAllCurrentOccupants($unitFilter, $categoryFilter, $search, $includeStale);
    }

    public function getAllExpectedToday(?string $unitFilter = null, ?string $categoryFilter = null, ?string $search = null): Collection
    {
        return $this->censusQueryService->getAllExpectedToday($unitFilter, $categoryFilter, $search);
    }

    public function getUnitsSummary(): array
    {
        return $this->metricsService->getUnitsSummary();
    }

    public function getUnitDrilldown(string $unit): array
    {
        return $this->hierarchyBuilder->getUnitDrilldown($unit);
    }

    public function getCommunityHierarchy(?string $scopedUnit = null): array
    {
        return $this->hierarchyBuilder->getCommunityHierarchy($scopedUnit);
    }

    public function getEnclosureZonesSummary(): array
    {
        return $this->metricsService->getEnclosureZonesSummary();
    }

    public function staleCheckIns(?string $unitFilter = null): Collection
    {
        return $this->metricsService->staleCheckIns($unitFilter);
    }
}
