<?php

namespace App\Notifications\Facilities;

use App\Models\FacilityBooking;
use App\Models\User;
use App\Notifications\EngineNotification;
use App\Services\NotificationEngine\NotificationPayload;

/** Confirms a facility booking (or its cancellation) to the resident who made it. */
class FacilityBookingConfirmed extends EngineNotification
{
    public function __construct(public FacilityBooking $booking, public bool $cancelled = false) {}

    public function toEngine(User $notifiable): NotificationPayload
    {
        $facility = $this->booking->landmark?->name ?? 'Facility';
        $when = $this->booking->booked_on->format('D j M Y').', '.$this->booking->slotLabel();

        return new NotificationPayload(
            title: $this->cancelled ? "Booking Cancelled: {$facility}" : "Booking Confirmed: {$facility}",
            body: $this->cancelled
                ? "Your booking {$this->booking->reference} of {$facility} on {$when} has been cancelled."
                : "{$facility} is booked for you on {$when} for {$this->booking->guests} guest(s). Reference {$this->booking->reference}.",
            user: $notifiable,
            actionUrl: '/dashboard/map',
            metadata: ['category' => 'facility_booking', 'booking_id' => $this->booking->id, 'reference' => $this->booking->reference],
        );
    }

    public function subject(): FacilityBooking
    {
        return $this->booking;
    }
}
