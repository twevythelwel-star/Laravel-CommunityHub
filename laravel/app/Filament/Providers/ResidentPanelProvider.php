<?php

namespace App\Filament\Providers;

use App\Filament\Core\Panel;
use App\Filament\Core\PanelRegistry;
use App\Filament\Resources\GatePassResource;
use App\Filament\Resources\WarningResource;
use App\Filament\Widgets\EstateStatsOverviewWidget;
use Illuminate\Support\ServiceProvider;

class ResidentPanelProvider extends ServiceProvider
{
    public function register(): void
    {
        $panel = Panel::make('portal')
            ->path('/portal')
            ->brandName('Community Hub &middot; Resident Portal')
            ->resources([
                GatePassResource::class,
                WarningResource::class,
            ])
            ->widgets([
                EstateStatsOverviewWidget::class,
            ])
            ->navigationGroups([
                'My Gate Access',
                'Community Warnings',
            ]);

        PanelRegistry::register($panel);
    }

    public function boot(): void
    {
        // Panel boot routines
    }
}
