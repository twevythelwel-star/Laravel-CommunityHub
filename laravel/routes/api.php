<?php

use App\Http\Controllers\Api\AccessLogApiController;
use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\GatePassApiController;
use App\Http\Controllers\Api\MapApiController;
use App\Http\Controllers\Api\VisitorApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| JSON API
|--------------------------------------------------------------------------
| Backs the Capacitor mobile build (capacitor.config.ts) and the handheld
| gate scanner. Same controllers-to-services path as the Inertia routes, so
| validation rules and access policy are enforced identically on both.
|
| Auth uses Sanctum tokens rather than session cookies, since a native shell
| has no browser session to share.
*/

Route::post('/auth/login', [AuthApiController::class, 'login'])->middleware('throttle:6,1');

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
