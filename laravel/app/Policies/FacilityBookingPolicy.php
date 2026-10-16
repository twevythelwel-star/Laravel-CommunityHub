<?php

namespace App\Policies;

use App\Models\FacilityBooking;
use App\Models\User;

/**
 * Residents book community facilities for themselves and cancel their own bookings;
 * administrators can book and cancel any booking.
 */
class FacilityBookingPolicy
{
    public function create(User $user): bool
    {
        return $user->role->isResident() || $user->role->isAdministrative();
    }

    public function cancel(User $user, FacilityBooking $booking): bool
    {
        return $booking->status === 'confirmed'
            && ($booking->user_id === $user->id || $user->role->isAdministrative());
    }
}
