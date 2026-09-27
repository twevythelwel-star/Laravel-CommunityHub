<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Who may listen on each private broadcast channel
|--------------------------------------------------------------------------
|
| Every channel the app broadcasts on is private, and each is authorised
| here. A deactivated account may subscribe to nothing, whichever way it
| signed in (web session or Sanctum token).
|
*/

// One resident's own events: their visitor arriving, for instance.
Broadcast::channel('user.{id}', fn (User $user, int $id) => $user->isActive() && $user->id === $id);

// The live gate feed: every arrival, with visitor, vehicle and host. Gate staff
// only, the same people who may scan passes.
Broadcast::channel('gatehouse-stream', fn (User $user) => $user->isActive() && $user->can('scanPasses'));

// Estate safety alerts. Any signed-in resident or staff member, the same
// audience as the Safety Alert page; never someone outside the estate.
Broadcast::channel('community-alerts', fn (User $user) => $user->isActive());
