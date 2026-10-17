<?php

namespace App\Actions\Facilities;

use App\Models\FacilityBooking;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Landmark;
use App\Models\User;
use App\Notifications\Facilities\FacilityBookingConfirmed;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Books a facility for a resident: free or invoiced per the facility's fee, and confirmed
 * at once, provided nobody holds the slot already. The resident is notified and the booking
 * recorded in their activity.
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

            $feeMinor = (int) ($facility->fee_minor ?? 0);
            $invoice = null;

            if ($feeMinor > 0) {
                $propertyId = DB::table('properties')->where('owner_user_id', $resident->id)->value('id');
                $reference = sprintf('INV-BK-%s-%s', now()->format('Ym'), strtoupper(bin2hex(random_bytes(3))));

                $invoice = Invoice::create([
                    'user_id' => $resident->id,
                    'property_id' => $propertyId,
                    'reference' => $reference,
                    'amount_minor' => $feeMinor,
                    'currency' => 'USD',
                    'period_start' => today(),
                    'period_end' => $validated['booked_on'],
                    'due_on' => $validated['booked_on'],
                    'status' => 'Unpaid',
                ]);

                $slotLabel = FacilityBooking::SLOTS[$validated['slot']]
                    ? FacilityBooking::SLOTS[$validated['slot']][0].'–'.FacilityBooking::SLOTS[$validated['slot']][1]
                    : $validated['slot'];

                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'category' => 'facility_booking',
                    'title' => "Facility Reservation: {$facility->name} (".Carbon::parse($validated['booked_on'])->format('D j M Y').", {$slotLabel})",
                    'amount_minor' => $feeMinor,
                    'status' => 'Unpaid',
                ]);
            }

            return FacilityBooking::create([
                'reference' => FacilityBooking::generateReference(),
                'landmark_id' => $facility->id,
                'user_id' => $resident->id,
                'booked_on' => $validated['booked_on'],
                'slot' => $validated['slot'],
                'guests' => $validated['guests'],
                'fee_minor' => $feeMinor,
                'invoice_id' => $invoice?->id,
                'notes' => $validated['notes'] ?? null,
                'status' => 'confirmed',
            ]);
        });

        $resident->notify(new FacilityBookingConfirmed($booking));
        $resident->recordActivity("Booked {$booking->landmark->name} ({$booking->reference})");

        return $booking;
    }
}
