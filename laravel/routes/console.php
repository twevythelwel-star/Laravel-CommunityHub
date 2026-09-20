<?php

use App\Services\GatePassEngine;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 | Prune spent anti-replay nonces. Rows are only needed until their validity
 | window closes, so this keeps the table small without weakening replay
 | detection.
 */
Schedule::call(function (GatePassEngine $engine) {
    $engine->pruneNonces();
})->hourly()->name('gatepass:prune-nonces');

/*
 | Expire stale visitor pre-clearances. The original ran this check in the
 | browser every 60 seconds, so it only happened while the visitors page was
 | open. Every 15 minutes on the server is both more reliable and far cheaper.
 */
Schedule::command('visitors:expire-no-shows')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->name('visitors:expire-no-shows');
