<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Amenities\SaveAmenityRequest;
use App\Models\Amenity;
use App\Models\AmenityBooking;
use App\Models\Landmark;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Administrators manage bookable amenities and see who has booked them.
 *
 * An amenity with any booking history is deactivated rather than deleted:
 * deleting would cascade away the bookings, and the record of who held a slot.
 */
class AmenityController extends Controller
{
    public function index(): Response
    {
        $amenities = Amenity::query()
            ->with('landmark:id,name')
            ->withExists('bookings as has_bookings')
            ->with(['bookings' => fn ($q) => $q->confirmed()
                ->whereDate('booked_on', '>=', today())
                ->orderBy('booked_on')
                ->with('user:id,name,display_name,lot,street')])
            ->orderBy('name')
            ->get();

        return Inertia::render('Dashboard/Amenities', [
            'amenities' => $amenities->map(fn (Amenity $a) => [
                'id' => $a->id,
                'name' => $a->name,
                'category' => $a->category,
                'maxGuests' => $a->max_guests,
                'opensAt' => substr((string) $a->opens_at, 0, 5),
                'closesAt' => substr((string) $a->closes_at, 0, 5),
                'isActive' => $a->is_active,
                'landmarkId' => $a->landmark_id,
                'landmarkName' => $a->landmark?->name,
                'hasBookings' => (bool) $a->has_bookings,
                'upcomingBookings' => $a->bookings->map(fn (AmenityBooking $b) => [
                    'id' => $b->id,
                    'reference' => $b->reference,
                    'date' => $b->booked_on->toDateString(),
                    'slot' => $b->slot,
                    'guests' => $b->guests,
                    'notes' => $b->notes,
                    'residentName' => $b->user->display_name ?: $b->user->name,
                    'residentLot' => trim(($b->user->lot ?? '').' '.($b->user->street ?? '')) ?: null,
                ])->values(),
            ]),
            // Security gates are not bookable places.
            'landmarks' => Landmark::query()
                ->where('category', '!=', 'Security Gate')
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function store(SaveAmenityRequest $request): RedirectResponse
    {
        $amenity = Amenity::create($request->validated());
        $request->user()->recordActivity("Added amenity {$amenity->name}");

        return back()->with('success', "{$amenity->name} added.");
    }

    public function update(SaveAmenityRequest $request, Amenity $amenity): RedirectResponse
    {
        $amenity->update($request->validated());
        $request->user()->recordActivity("Updated amenity {$amenity->name}");

        return back()->with('success', "{$amenity->name} updated.");
    }

    public function destroy(Request $request, Amenity $amenity): RedirectResponse
    {
        if ($amenity->bookings()->exists()) {
            return back()->withErrors([
                'amenity' => "{$amenity->name} has bookings on record, so it can't be deleted. Deactivate it instead.",
            ]);
        }

        $amenity->delete();
        $request->user()->recordActivity("Deleted amenity {$amenity->name}");

        return back()->with('success', "{$amenity->name} deleted.");
    }
}
