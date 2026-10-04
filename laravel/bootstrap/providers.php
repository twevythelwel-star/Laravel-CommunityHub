<?php

use App\Filament\Providers\AdminPanelProvider;
use App\Filament\Providers\ResidentPanelProvider;
use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\TenancyServiceProvider;
use Laravel\Octane\OctaneServiceProvider;

return [
    AppServiceProvider::class,
    AuthServiceProvider::class,
    AdminPanelProvider::class,
    ResidentPanelProvider::class,
    TenancyServiceProvider::class,
    HorizonServiceProvider::class,
    OctaneServiceProvider::class,
];
