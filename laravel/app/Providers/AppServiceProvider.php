<?php

namespace App\Providers;

use App\Events\VisitorCheckedInEvent;
use App\Services\GatePassEngine;
use App\Services\GeofenceService;
use App\Services\NotificationEngine\NotificationEngine;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
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

        Event::listen(VisitorCheckedInEvent::class, function (VisitorCheckedInEvent $event) {
            try {
                app(NotificationEngine::class)->dispatchArrivalNotice($event->visitor, $event->gate);
            } catch (\Throwable $e) {
                Log::warning('Failed to dispatch arrival notice: '.$e->getMessage());
            }
        });
    }
}
