<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| These routes are loaded by the TenancyServiceProvider and protected by
| domain identification and central domain protection middleware.
|
*/

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    Route::get('/tenant-app', function () {
        return response()->json([
            'status' => 'active',
            'tenant_id' => tenant('id'),
            'name' => tenant('name'),
            'estate_name' => tenant('estate_name') ?? config('gatepass.estate_name'),
            'app_name' => config('app.name'),
            'message' => 'Welcome to '.(tenant('name') ?? 'Tenant').' Community Hub',
        ]);
    })->name('tenant.home');

    Route::get('/api/tenant/info', function () {
        return response()->json([
            'status' => 'active',
            'tenant_id' => tenant('id'),
            'name' => tenant('name'),
            'community_id' => tenant('community_id'),
            'estate_name' => tenant('estate_name') ?? config('gatepass.estate_name'),
            'app_name' => config('app.name'),
            'cache_tag' => config('tenancy.cache.tag_base').'_'.tenant('id'),
            'domains' => tenant()->domains->pluck('domain')->all(),
        ]);
    })->name('tenant.info');
});
