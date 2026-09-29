<?php

namespace App\Livewire;

use App\Enums\PassStatus;
use App\Models\GatePass;
use App\Models\Warning;
use Livewire\Component;

class EstateStatusWidget extends Component
{
    public int $activePassesCount = 0;

    public int $activeAlertsCount = 0;

    public string $communityName = 'Cypress Bay Estate';

    public bool $isRefreshed = false;

    public function mount(): void
    {
        $this->refreshStats();
    }

    public function refreshStats(): void
    {
        $this->activePassesCount = GatePass::where('status', PassStatus::Active)->count();
        $this->activeAlertsCount = Warning::count();
        $this->isRefreshed = true;
    }

    public function render()
    {
        return view('livewire.estate-status-widget');
    }
}
