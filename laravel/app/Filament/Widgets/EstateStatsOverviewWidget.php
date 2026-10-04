<?php

namespace App\Filament\Widgets;

use App\Enums\PassStatus;
use App\Filament\Core\Widgets\Stat;
use App\Filament\Core\Widgets\StatsOverviewWidget;
use App\Models\GatePass;
use App\Models\User;
use App\Models\Warning;

class EstateStatsOverviewWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $activePasses = GatePass::where('status', PassStatus::Active)->count();
        $onSite = GatePass::where('status', PassStatus::CheckedIn)->count();
        $warnings = Warning::count();
        $residents = User::count();

        return [
            Stat::make('Active Passes', $activePasses)
                ->description('Authorized visitor and resident credentials')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),

            Stat::make('Visitors On Site', $onSite)
                ->description('Currently checked in at estate perimeter')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('info'),

            Stat::make('Security Alerts', $warnings)
                ->description('Active community notices')
                ->descriptionIcon('heroicon-m-shield-exclamation')
                ->color('danger'),

            Stat::make('Residents & Staff', $residents)
                ->description('Registered accounts in good standing')
                ->descriptionIcon('heroicon-m-home')
                ->color('primary'),
        ];
    }
}
