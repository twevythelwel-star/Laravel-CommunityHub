<?php

use App\Models\User;
use App\Services\GatePassEngine;
use App\Services\Messaging\MessageNotSent;
use App\Services\SmsService;
use App\Services\WhatsAppService;
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
 | Sends one real message through Twilio to check the credentials in .env.
 | Prints the message SID, or the reason it was refused. Never prints secrets.
 */
Artisan::command('messaging:test {phone : The number to send to, e.g. 876-555-1234 or +18765551234} {--whatsapp : Send a WhatsApp message instead of an SMS}', function (SmsService $sms, WhatsAppService $whatsApp) {
    $isWhatsApp = (bool) $this->option('whatsapp');
    $channel = $isWhatsApp ? 'WhatsApp' : 'SMS';
    $service = $isWhatsApp ? $whatsApp : $sms;

    $this->line('Account SID:  '.(filled(config('services.twilio.sid')) ? 'set' : 'MISSING (TWILIO_SID)'));
    $this->line('Auth:         '.match (true) {
        filled(config('services.twilio.api_key')) && filled(config('services.twilio.api_secret')) => 'API key (TWILIO_API_KEY / TWILIO_API_SECRET)',
        filled(config('services.twilio.token')) => 'Auth Token (TWILIO_TOKEN)',
        default => 'MISSING (TWILIO_TOKEN, or TWILIO_API_KEY + TWILIO_API_SECRET)',
    });
    $this->line('Sender:       '.(filled(config($isWhatsApp ? 'services.twilio.whatsapp_from' : 'services.twilio.from'))
        ? 'set'
        : 'MISSING ('.($isWhatsApp ? 'TWILIO_WHATSAPP_FROM' : 'TWILIO_FROM').')'));

    if (! $service->isConfigured()) {
        $this->error("{$channel} is not configured. Add the missing values to laravel/.env.");

        return Command::FAILURE;
    }

    try {
        $sid = $service->send($this->argument('phone'), 'Community Hub: this is a test message. Your gate pass notifications are set up.');
    } catch (MessageNotSent $e) {
        $this->error($e->getMessage());
        $this->line('Twilio error codes are listed at https://www.twilio.com/docs/api/errors');

        return Command::FAILURE;
    }

    $this->info("{$channel} accepted by Twilio. Message SID: {$sid}");
    $this->line('Accepted is not delivered: check the message status in the Twilio console if it does not arrive.');

    return Command::SUCCESS;
})->purpose('Send a test SMS or WhatsApp message through Twilio');

/*
 | Expire gate passes whose validity window has closed. Someone still checked
 | in is left alone: they are checked out, however late, not expired.
 */
Artisan::command('gatepass:expire', function (GatePassEngine $engine) {
    $this->info(sprintf('Expired %d gate pass(es).', $engine->expireLapsedPasses()));
})->purpose('Expire gate passes past their validity window');

Schedule::command('gatepass:expire')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->name('gatepass:expire');

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
