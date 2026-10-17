<?php

namespace App\Actions\Facilities;

use App\Models\FacilityBooking;
use App\Notifications\Facilities\FacilityBookingReminder;
use Illuminate\Database\Eloquent\Builder;

/**
 * Queues reminders for confirmed facility bookings occurring tomorrow that have not
 * yet been reminded.
 */
class QueueFacilityBookingRemindersAction
{
    /** @return int how many reminders were queued */
    public function execute(): int
    {
        $queued = 0;

        $this->due()->with(['landmark', 'user'])->each(function (FacilityBooking $booking) use (&$queued) {
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
