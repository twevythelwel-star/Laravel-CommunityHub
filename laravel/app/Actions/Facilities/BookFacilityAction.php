<?php

namespace App\Actions\Facilities;

use App\Models\FacilityBooking;
use App\Models\Landmark;
use App\Models\User;
use App\Notifications\Facilities\FacilityBookingConfirmed;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Books a facility for a resident: free and confirmed at once, provided nobody holds the
 * slot already. The resident is notified and the booking recorded in their activity.
 */
class BookFacilityAction
{
    /**
     * @param  array{landmark_id: int, booked_on: string, slot: string, guests: int, notes?: ?string}  $validated
     */
    public function execute(User $resident, array $validated): FacilityBooking
    {
        $booking = DB::transaction(function () use ($resident, $validated) {
            $facility = Landmark::lockForUpdate()->findOrFail($validated['landmark_id']);

            $taken = $facility->bookings()->confirmed()
                ->whereDate('booked_on', $validated['booked_on'])
                ->where('slot', $validated['slot'])
                ->exists();
            if ($taken) {
                throw ValidationException::withMessages(['slot' => "{$facility->name} is already booked for that time. Please choose another slot or day."]);
            }

            return FacilityBooking::create([
                'reference' => FacilityBooking::generateReference(),
                'landmark_id' => $facility->id,
                'user_id' => $resident->id,
                'booked_on' => $validated['booked_on'],
                'slot' => $validated['slot'],
                'guests' => $validated['guests'],
                'notes' => $validated['notes'] ?? null,
                'status' => 'confirmed',
            ]);
        });

        $resident->notify(new FacilityBookingConfirmed($booking));
        $resident->recordActivity("Booked {$booking->landmark->name} ({$booking->reference})");

        return $booking;
    }
}
