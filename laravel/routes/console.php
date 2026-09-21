<?php

use App\Models\User;
use App\Services\GatePassEngine;
use Illuminate\Console\Command;
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

/*
 | Local seeding convenience only.
 |
 | This flattens every account to one shared secret and echoes it to the
 | terminal. Two things made that dangerous on a deployed host:
 |
 |   1. It was callable in any environment.
 |   2. It read env('SEED_PASSWORD') directly. `env()` outside a config file
 |      returns null once `php artisan config:cache` has run — which is the
 |      normal production deploy step — so the ?: fell through and every
 |      account silently became the hardcoded literal.
 |
 | The default now comes from config (cache-safe) and the command refuses to
 | run anywhere but local and testing.
 */
Artisan::command('users:set-passwords {password?}', function (?string $password = null) {
    if (! app()->environment('local', 'testing')) {
        $this->error(
            'users:set-passwords is a local seeding helper and is refused in ['.app()->environment().'].'
        );

        return Command::FAILURE;
    }

    $targetPassword = $password ?: config('auth.seed_password');
    $count = 0;

    foreach (User::all() as $user) {
        $user->password = $targetPassword;
        $user->save();
        $this->info("Updated {$user->email} ({$user->name})");
        $count++;
    }

    $this->info("Total users updated to '{$targetPassword}': {$count}");

    return Command::SUCCESS;
})->purpose('Set all user passwords to a shared default (local and testing only)');
