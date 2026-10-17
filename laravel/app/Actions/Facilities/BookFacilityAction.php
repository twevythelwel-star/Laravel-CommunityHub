<?php

namespace App\Actions\Facilities;

use App\Actions\Billing\RaiseOneOffChargeAction;
use App\Models\FacilityBooking;
use App\Models\Invoice;
use App\Models\Landmark;
use App\Models\User;
use App\Notifications\Facilities\FacilityBookingConfirmed;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Books a facility for a resident, confirmed at once provided nobody holds the slot
 * already. A facility with a fee bills it through the ordinary billing (one invoice, paid
 * like any other). The resident is notified and the booking recorded in their activity.
 */
class BookFacilityAction
{
    public function __construct(private readonly RaiseOneOffChargeAction $charge) {}

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

            $invoice = $facility->fee_minor > 0 ? $this->billFee($resident, $facility, $validated) : null;

            return FacilityBooking::create([
                'reference' => FacilityBooking::generateReference(),
                'landmark_id' => $facility->id,
                'user_id' => $resident->id,
                'booked_on' => $validated['booked_on'],
                'slot' => $validated['slot'],
                'guests' => $validated['guests'],
                'fee_minor' => $facility->fee_minor,
                'invoice_id' => $invoice?->id,
                'notes' => $validated['notes'] ?? null,
                'status' => 'confirmed',
            ]);
        });

        $resident->notify(new FacilityBookingConfirmed($booking));
        $resident->recordActivity("Booked {$booking->landmark->name} ({$booking->reference})");

        return $booking;
    }

    /** @param  array{booked_on: string, slot: string}  $validated */
    private function billFee(User $resident, Landmark $facility, array $validated): Invoice
    {
        $when = Carbon::parse($validated['booked_on'])->format('D j M Y').', '.FacilityBooking::labelForSlot($validated['slot']);

        return $this->charge->execute(
            $resident,
            $facility->fee_minor,
            'facility_booking',
            "Facility Reservation: {$facility->name} ({$when})",
            $validated['booked_on'],
        );
    }
}
