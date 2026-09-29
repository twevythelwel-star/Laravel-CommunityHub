<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// Public/Estate-wide channel for verified residents & staff to receive active security alerts
Broadcast::channel('community-alerts', function (User $user) {
    return $user->isActive();
});

// Private channel for user-specific real-time notifications (e.g. visitor arrival notices)
Broadcast::channel('users.{id}', function (User $user, $id) {
    return (int) $user->id === (int) $id;
});

// Private channel for security gate personnel
Broadcast::channel('gate.{gateId}', function (User $user, $gateId) {
    return $user->role->isSecurity() || $user->role->isAdministrative();
});
