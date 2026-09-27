<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Amenities\StoreAmenityBookingRequest;
use App\Models\Amenity;
use App\Models\AmenityBooking;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Amenity reservations. Backs amenity-booking-dialog.tsx, which used to show a
 * made-up confirmation number and save nothing.
 */
class AmenityBookingController extends Controller
{
    public function store(StoreAmenityBookingRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $amenity = Amenity::findOrFail($validated['amenity_id']);

        try {
            // The unique slot_key index decides who gets a contested slot. The
            // savepoint keeps a losing insert from aborting an outer transaction
            // on Postgres.
            $booking = DB::transaction(fn () => AmenityBooking::create([
                'amenity_id' => $amenity->id,
                'user_id' => $request->user()->id,
                'booked_on' => $validated['booked_on'],
                'slot' => $validated['slot'],
                'guests' => $validated['guests'],
                'notes' => $validated['notes'] ?? null,
            ]));
        } catch (UniqueConstraintViolationException) {
            return back()->withErrors(['slot' => "{$amenity->name} is already booked for that slot. Choose another time or day."]);
        }

        $request->user()->recordActivity("Booked {$amenity->name} ({$booking->reference})");

        return back()
            ->with('success', "{$amenity->name} reserved. Booking ref: {$booking->reference}")
            ->with('booking', ['reference' => $booking->reference]);
    }

    /** A resident cancels their own upcoming booking; an administrator any. */
    public function destroy(Request $request, AmenityBooking $booking): RedirectResponse
    {
        $user = $request->user();
        abort_unless($booking->user_id === $user->id || $user->role->isAdministrative(), 403);

        if ($booking->status !== AmenityBooking::STATUS_CONFIRMED || $booking->booked_on->isBefore(today())) {
            return back()->withErrors(['booking' => 'Only upcoming, confirmed bookings can be cancelled.']);
        }

        $booking->cancel();
        $user->recordActivity("Cancelled amenity booking {$booking->reference}");

        return back()->with('success', "Booking {$booking->reference} cancelled.");
    }
}
