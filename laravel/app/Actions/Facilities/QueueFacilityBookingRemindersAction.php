<?php

namespace App\Actions\Facilities;

use App\Models\FacilityBooking;
use App\Notifications\Facilities\FacilityBookingReminder;
use Illuminate\Database\Eloquent\Builder;

/**
 * Queues a reminder, the day before, for each confirmed facility booking happening
 * tomorrow that has not been reminded yet (run each morning by the scheduler).
 */
class QueueFacilityBookingRemindersAction
{
    /** @return int how many reminders were queued */
    public function execute(): int
    {
        $queued = 0;

        // Loaded up front: marking each reminded changes the query, which would make
        // chunked iteration skip bookings.
        $this->due()->with(['landmark', 'user'])->get()->each(function (FacilityBooking $booking) use (&$queued) {
            if ($booking->user) {
                $booking->user->notify(new FacilityBookingReminder($booking));
                $booking->update(['reminded_at' => now()]);
                $queued++;
            }
        });

        return $queued;
    }

    /** @return Builder<FacilityBooking> */
    public function due(): Builder
    {
        return FacilityBooking::query()->dueForReminder();
    }
}
