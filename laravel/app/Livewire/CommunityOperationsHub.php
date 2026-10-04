<?php

namespace App\Livewire;

use App\Enums\PassStatus;
use App\Models\GatePass;
use App\Models\User;
use App\Models\Warning;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.livewire')]
class CommunityOperationsHub extends Component
{
    #[Url(as: 'tab')]
    public string $activeTab = 'overview';

    public bool $autoPolling = false;

    public string $pollingInterval = '30s';

    // Dashboard Metrics
    public int $activePassesCount = 0;

    public int $onSiteVisitorsCount = 0;

    public int $activeAlertsCount = 0;

    public int $totalResidentsCount = 0;

    public int $todayPassesIssued = 0;

    /**
     * The security desk's view of the estate: every pass, visitor and alert.
     * The directory tab is for administrators only, so a `?tab=directory`
     * link opens the overview for anyone else.
     */
    public function mount(): void
    {
        $this->authorize('manageSecurity');

        if (! $this->mayOpenTab($this->activeTab)) {
            $this->activeTab = 'overview';
        }

        $this->refreshKpis();
    }

    private function mayOpenTab(string $tab): bool
    {
        if (! in_array($tab, ['overview', 'passes', 'directory', 'warnings', 'ui-kit', 'search'], true)) {
            return false;
        }

        return $tab !== 'directory' || Gate::allows('manageUsers');
    }

    public function refreshKpis(): void
    {
        $this->activePassesCount = GatePass::where('status', PassStatus::Active)->count();
        $this->onSiteVisitorsCount = GatePass::where('status', PassStatus::CheckedIn)->count();
        $this->activeAlertsCount = Warning::count();
        $this->totalResidentsCount = User::count();
        $this->todayPassesIssued = GatePass::whereDate('created_at', today())->count();
    }

    public function setTab(string $tab): void
    {
        if ($this->mayOpenTab($tab)) {
            $this->activeTab = $tab;
        }
    }

    public function togglePolling(): void
    {
        $this->autoPolling = ! $this->autoPolling;
        $status = $this->autoPolling ? 'enabled' : 'paused';
        $this->dispatch('notify', message: "Live dashboard telemetry {$status}.", type: 'info');
    }

    public function render(): View
    {
        $this->refreshKpis();

        $recentPasses = GatePass::latest()->limit(5)->get();
        $recentAlerts = Warning::latest()->limit(4)->get();

        return view('livewire.community-operations-hub', [
            'recentPasses' => $recentPasses,
            'recentAlerts' => $recentAlerts,
        ]);
    }
}
