<?php

namespace App\Services\Facilities;

use App\Models\BillingSetting;
use App\Models\FacilityBooking;
use App\Models\Landmark;
use App\Models\User;

/**
 * What the booking dialog shows: which slots of a facility are taken on a day (without
 * saying by whom), the facility's guest limit, booking fee, and the viewer's own upcoming bookings.
 */
class FacilityAvailabilityPresenter
{
    /** @return array<string, mixed> */
    public function props(User $viewer, ?Landmark $facility, ?string $date): array
    {
        return [
            'takenSlots' => $facility && $date
                ? $facility->bookings()->confirmed()->whereDate('booked_on', $date)->pluck('slot')->values()
                : [],
            'guestLimit' => $facility?->guestLimit() ?? FacilityBooking::DEFAULT_CAPACITY,
            'feeMinor' => $facility?->fee_minor ?? 0,
            'feeFormatted' => $facility?->feeFormatted(BillingSetting::currencyCode()) ?? 'Free',
            'myBookings' => FacilityBooking::with(['landmark:id,name', 'invoice:id,reference,status'])
                ->where('user_id', $viewer->id)->confirmed()->upcoming()
                ->orderBy('booked_on')->limit(10)->get()
                ->map(fn (FacilityBooking $booking) => [
                    'id' => $booking->id,
                    'reference' => $booking->reference,
                    'facility' => $booking->landmark?->name,
                    'date' => $booking->booked_on->toDateString(),
                    'slot' => $booking->slot,
                    'slotLabel' => $booking->slotLabel(),
                    'guests' => $booking->guests,
                    'feeMinor' => $booking->fee_minor,
                    'feeFormatted' => $booking->feeFormatted(),
                    'invoiceReference' => $booking->invoice?->reference,
                    'invoiceStatus' => $booking->invoice?->status,
                ]),
        ];
    }
}
