<?php

use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\TenancyServiceProvider;
use Laravel\Octane\OctaneServiceProvider;

return [
    AppServiceProvider::class,
    AuthServiceProvider::class,
    TenancyServiceProvider::class,
    HorizonServiceProvider::class,
    OctaneServiceProvider::class,
];
