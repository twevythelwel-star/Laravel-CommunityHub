<?php

namespace App\Actions\Facilities;

use App\Models\FacilityBooking;
use App\Models\User;
use App\Notifications\Facilities\FacilityBookingConfirmed;

/**
 * Cancels a booking, freeing its slot, and tells the resident who made it. An unpaid booking
 * fee is cancelled with it; a fee already (partly) paid is left for a refund.
 */
class CancelFacilityBookingAction
{
    public function execute(FacilityBooking $booking, User $actor): void
    {
        $booking->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_by_user_id' => $actor->id,
        ]);

        $booking->invoice?->cancelUnpaid();

        $booking->user?->notify(new FacilityBookingConfirmed($booking, cancelled: true));
        $actor->recordActivity("Cancelled facility booking {$booking->reference}");
    }
}
