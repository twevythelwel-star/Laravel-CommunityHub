<?php

namespace App\Providers;

use App\Services\GatePassEngine;
use App\Services\GeofenceService;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GeofenceService::class);

        // Resolved lazily so the missing-secret guard only fires when the engine
        // is actually used, not on every container boot.
        $this->app->singleton(GatePassEngine::class, fn () => new GatePassEngine);
    }

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
    }
}
