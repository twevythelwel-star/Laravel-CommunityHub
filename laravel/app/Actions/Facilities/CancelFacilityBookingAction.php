<?php

namespace App\Actions\Facilities;

use App\Models\FacilityBooking;
use App\Models\User;
use App\Notifications\Facilities\FacilityBookingConfirmed;

/** Cancels a booking, freeing its slot, cancels any unpaid booking invoice, and tells the resident who made it. */
class CancelFacilityBookingAction
{
    public function execute(FacilityBooking $booking, User $actor): void
    {
        $booking->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_by_user_id' => $actor->id,
        ]);

        if ($booking->invoice && $booking->invoice->status === 'Unpaid') {
            $booking->invoice->update(['status' => 'Cancelled']);
            $booking->invoice->items()->update(['status' => 'Cancelled']);
        }

        $booking->user?->notify(new FacilityBookingConfirmed($booking, cancelled: true));
        $actor->recordActivity("Cancelled facility booking {$booking->reference}");
    }
}
