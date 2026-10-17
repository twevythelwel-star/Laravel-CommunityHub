<?php

namespace App\Notifications\Facilities;

use App\Models\FacilityBooking;
use App\Models\User;
use App\Notifications\EngineNotification;
use App\Services\NotificationEngine\NotificationPayload;

/** Reminds the resident before their upcoming facility reservation. */
class FacilityBookingReminder extends EngineNotification
{
    public function __construct(public FacilityBooking $booking) {}

    public function toEngine(User $notifiable): NotificationPayload
    {
        $facility = $this->booking->landmark?->name ?? 'Facility';
        $when = $this->booking->booked_on->format('D j M Y').', '.$this->booking->slotLabel();
        $feeText = $this->booking->fee_minor > 0 ? " (Fee: {$this->booking->feeFormatted()})" : '';

        return new NotificationPayload(
            title: "Upcoming Reservation Reminder: {$facility}",
            body: "Reminder: You have a booking for {$facility} tomorrow ({$when}) for {$this->booking->guests} guest(s){$feeText}. Reference {$this->booking->reference}.",
            user: $notifiable,
            actionUrl: '/dashboard/map',
            metadata: [
                'category' => 'facility_reminder',
                'booking_id' => $this->booking->id,
                'reference' => $this->booking->reference,
            ],
        );
    }

    public function subject(): FacilityBooking
    {
        return $this->booking;
    }
}
