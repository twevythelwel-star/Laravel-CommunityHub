<?php

use App\Http\Controllers\Api\AccessLogApiController;
use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\GatePassApiController;
use App\Http\Controllers\Api\MapApiController;
use App\Http\Controllers\Api\VisitorApiController;
use App\Http\Controllers\ProviderWebhookController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\TwilioWebhookController;
use App\Http\Controllers\UniversalPaymentWebhookController;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| JSON API
|--------------------------------------------------------------------------
| Backs the Capacitor mobile build and the handheld
| gate scanner. Same controllers-to-services path as the Inertia routes, so
| validation rules and access policy are enforced identically on both.
|
| Auth uses Sanctum tokens rather than session cookies, since a native shell
| has no browser session to share.
*/

Route::post('/auth/login', [AuthApiController::class, 'login'])->middleware('throttle:6,1');

// Stripe calls this, not a user: no session, no token. Authenticity comes from
// the Stripe-Signature header, verified against STRIPE_WEBHOOK_SECRET.
Route::post('/webhooks/stripe', StripeWebhookController::class)->name('webhooks.stripe');

// Universal Multi-Provider Webhook Processor:
// POST /api/webhooks/payments/{provider}
// Implements the 14-step verified processing pipeline (auth, signature, idempotency,
// transaction search, amount/currency/merchant verification, ledger, invoice, receipt, notification)
Route::post('/webhooks/payments/{provider}', UniversalPaymentWebhookController::class)
    ->where('provider', '[a-z0-9_]+')
    ->name('webhooks.payments.provider');

// Any other provider that reports server-to-server (PaymentProvider with
// HandlesWebhooks). Each verifies its own signature; see ProviderWebhookController.
Route::post('/webhooks/{provider}', ProviderWebhookController::class)
    ->where('provider', '[a-z0-9_]+')
    ->name('webhooks.provider');

// Twilio calls this for messaging status callbacks (queued -> sent -> delivered / failed).
// Verified with X-Twilio-Signature via Twilio SDK RequestValidator.
Route::post('/webhooks/twilio/status', [TwilioWebhookController::class, 'messagingStatus'])
    ->name('webhooks.twilio.status');

// Websocket channel authorisation for token clients (the Capacitor shell and
// handheld scanners): POST /api/broadcasting/auth. Rules in routes/channels.php.
Broadcast::routes(['middleware' => ['auth:sanctum']]);

Route::middleware('auth:sanctum')->group(function () {

    Route::post('/auth/logout', [AuthApiController::class, 'logout']);
    Route::get('/auth/me', [AuthApiController::class, 'me']);

    // ── Gate pass ──
    Route::get('/gate-pass', [GatePassApiController::class, 'show']);
    Route::get('/gate-pass/token', [GatePassApiController::class, 'token']);
    Route::post('/gate-pass/rotate', [GatePassApiController::class, 'rotate']);

    // Scanning is the security-critical endpoint: it both validates and writes
    // the access-log entry, so a scanner cannot record an entry it did not verify.
    Route::post('/gate-pass/validate', [GatePassApiController::class, 'validateToken'])
        ->middleware('can:scanPasses');
    Route::post('/gate-pass/scans/{scan}/confirm', [GatePassApiController::class, 'confirmScan'])
        ->middleware(['can:scanPasses', 'throttle:60,1'])
        ->whereUuid('scan');

    // ── Visitors ──
    Route::get('/visitors', [VisitorApiController::class, 'index']);
    Route::post('/visitors', [VisitorApiController::class, 'store']);
    Route::post('/visitors/{visitor}/check-in', [VisitorApiController::class, 'checkIn'])
        ->middleware('can:manageSecurity');
    Route::post('/visitors/{visitor}/check-out', [VisitorApiController::class, 'checkOut'])
        ->middleware('can:manageSecurity');

    // ── Access log ──
    Route::get('/access-log', [AccessLogApiController::class, 'index'])
        ->middleware('can:manageSecurity');

    // ── Map & geofence ──
    Route::get('/map/boundary', [MapApiController::class, 'boundary']);
    Route::get('/map/landmarks', [MapApiController::class, 'landmarks']);
    Route::post('/map/check-position', [MapApiController::class, 'checkPosition']);
});
