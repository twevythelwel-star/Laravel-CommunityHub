<?php

namespace App\Filament\Providers;

use App\Filament\Core\Panel;
use App\Filament\Core\PanelRegistry;
use App\Filament\Resources\GatePassResource;
use App\Filament\Resources\ResidentResource;
use App\Filament\Resources\WarningResource;
use App\Filament\Widgets\EstateStatsOverviewWidget;
use Illuminate\Support\ServiceProvider;

class AdminPanelProvider extends ServiceProvider
{
    public function register(): void
    {
        $panel = Panel::make('admin')
            ->path('/admin')
            ->brandName('Community Hub &middot; Admin Management')
            ->resources([
                GatePassResource::class,
                ResidentResource::class,
                WarningResource::class,
            ])
            ->widgets([
                EstateStatsOverviewWidget::class,
            ])
            ->navigationGroups([
                'Access & Gate Operations',
                'Community & Residents',
                'Safety & Security',
                'System & Settings',
            ]);

        PanelRegistry::register($panel);
    }

    public function boot(): void
    {
        // Panel boot routines
    }
}
