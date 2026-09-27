<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Amenities\StoreAmenityBookingRequest;
use App\Models\Amenity;
use App\Models\AmenityBooking;
use App\Services\NotificationEngine\ChannelDispatchResult;
use App\Services\NotificationEngine\NotificationEngine;
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

    /**
     * A resident cancels their own upcoming booking; an administrator any.
     *
     * When the canceller is not the resident who booked, the resident is told
     * by email/SMS per their preferences, and the canceller is told whether
     * that actually went out.
     */
    public function destroy(Request $request, AmenityBooking $booking, NotificationEngine $notifications): RedirectResponse
    {
        $user = $request->user();
        abort_unless($booking->user_id === $user->id || $user->role->isAdministrative(), 403);

        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        if ($booking->status !== AmenityBooking::STATUS_CONFIRMED || $booking->booked_on->isBefore(today())) {
            return back()->withErrors(['booking' => 'Only upcoming, confirmed bookings can be cancelled.']);
        }

        $booking->cancel($user, $validated['reason'] ?? null);
        $user->recordActivity("Cancelled amenity booking {$booking->reference}");

        if (! $booking->wasCancelledByOthers()) {
            return back()->with('success', "Booking {$booking->reference} cancelled.");
        }

        $results = $notifications->notifyBookingCancelled($booking->load('user.preferences', 'amenity'));

        return back()->with('success', "Booking {$booking->reference} cancelled. ".$this->notificationOutcome($results));
    }

    /**
     * What actually happened to the resident's notice, in words for the admin.
     *
     * @param  array<string, ChannelDispatchResult>  $results
     */
    private function notificationOutcome(array $results): string
    {
        $inbox = ($results['inbox'] ?? null)?->isSent()
            ? 'The resident has a message in their inbox'
            : 'The message could not be added to the resident\'s inbox';

        $external = array_diff_key($results, ['inbox' => true]);
        if ($external === []) {
            return "{$inbox}; they have email and SMS notifications turned off.";
        }

        $sent = array_keys(array_filter($external, fn (ChannelDispatchResult $r) => $r->isSent()));
        $notSent = array_keys(array_filter($external, fn (ChannelDispatchResult $r) => ! $r->isSent()));
        $names = fn (array $channels) => implode(' and ', array_map(fn ($c) => $c === 'sms' ? 'SMS' : $c, $channels));

        return $inbox.match (true) {
            $notSent === [] => ' and was notified by '.$names($sent).'.',
            $sent === [] => '; '.$names($notSent).' could not be sent (not configured or failed).',
            default => ' and was notified by '.$names($sent).'; '.$names($notSent).' could not be sent.',
        };
    }
}
