<?php

namespace App\Policies;

use App\Models\ParkingPass;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Auth\Access\Response;

class ParkingPassPolicy
{
    /**
     * Determine whether the user can view the parking pass.
     */
    public function view(User $user, ParkingPass $pass): bool
    {
        return $pass->user_id === $user->id || $user->can('scanPasses') || $user->can('manageSecurity');
    }

    /**
     * Determine whether the user can issue the parking pass.
     *
     * @param  array<string, mixed>  $validated
     */
    public function issue(User $user, array $validated): Response
    {
        $isStaff = $user->can('manageSecurity');

        if (! $isStaff) {
            if (in_array($validated['category'] ?? null, ['accessible', 'loading_zone'], true)) {
                return Response::deny('Accessible and loading-zone passes are issued by the estate office.');
            }

            if (! empty($validated['vehicle_id'])) {
                $ownsVehicle = Vehicle::whereKey($validated['vehicle_id'])->where('user_id', $user->id)->exists();
                if (! $ownsVehicle) {
                    return Response::deny('You can only use your own vehicle.');
                }
            }
        }

        return Response::allow();
    }
}
