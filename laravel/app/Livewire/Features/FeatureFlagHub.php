<?php

declare(strict_types=1);

namespace App\Livewire\Features;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Features\FeatureFlagService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class FeatureFlagHub extends Component
{
    /**
     * System Admin only: toggling or purging a flag changes the platform for
     * everybody. boot() runs on the first load and on every action request.
     */
    public function boot(): void
    {
        $this->authorize('operatePlatform');
    }

    public string $activeTab = 'flags_catalog'; // flags_catalog, rollouts_ab, scopes_simulator, database_store

    // Filter state
    public string $filterType = 'all';

    public string $search = '';

    // Scope Simulator state
    public string $simFeature = 'checkout_flow_experiment';

    public string $simScopeType = 'user'; // user, role, tenant

    public string $simScopeIdentifier = '1';

    public ?array $simResult = null;

    // Feedback message
    public ?string $feedbackMessage = null;

    public function selectTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function toggleFeature(string $feature, FeatureFlagService $service): void
    {
        $isActive = $service->active($feature);

        if ($isActive) {
            $service->deactivate($feature);
            $this->feedbackMessage = "Feature [{$feature}] deactivated globally.";
        } else {
            $service->activate($feature);
            $this->feedbackMessage = "Feature [{$feature}] activated globally.";
        }
    }

    public function runSimulation(FeatureFlagService $service): void
    {
        $this->simResult = $service->simulate(
            $this->simFeature,
            $this->simScopeType,
            $this->simScopeIdentifier
        );

        $this->feedbackMessage = "Evaluated [{$this->simFeature}] for {$this->simScopeType} [{$this->simScopeIdentifier}].";
    }

    public function purgeStore(FeatureFlagService $service): void
    {
        $service->purge();
        $this->feedbackMessage = 'Flushed all resolved feature values from the Pennant store.';
    }

    public function render(FeatureFlagService $service): View
    {
        $catalog = $service->getCatalog();
        $currentUser = auth()->user();

        // Apply filters
        $filteredCatalog = array_filter($catalog, function ($item, $key) {
            if ($this->filterType !== 'all' && $item['type'] !== $this->filterType) {
                return false;
            }

            if ($this->search) {
                $needle = strtolower($this->search);
                $haystack = strtolower($item['name'].' '.$key.' '.$item['description'].' '.$item['use_case']);
                if (! str_contains($haystack, $needle)) {
                    return false;
                }
            }

            return true;
        }, ARRAY_FILTER_USE_BOTH);

        // Augment with current user's resolved state
        $augmentedCatalog = [];
        foreach ($filteredCatalog as $key => $meta) {
            $augmentedCatalog[$key] = array_merge($meta, [
                'current_active' => $service->active($key, $currentUser),
                'current_value' => $service->value($key, $currentUser),
            ]);
        }

        // Query database stored features
        $dbFeatures = [];
        try {
            $dbFeatures = DB::table('features')->latest('id')->limit(20)->get()->toArray();
        } catch (\Throwable) {
            $dbFeatures = [];
        }

        return view('livewire.features.feature-flag-hub', [
            'catalog' => $augmentedCatalog,
            'totalCount' => count($catalog),
            'databaseEntries' => $dbFeatures,
            'allFeaturesList' => array_keys($catalog),
        ])->layout('layouts.app', ['header' => 'Feature Flags & Experimentation']);
    }
}
