<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\Facilities\BookFacilityAction;
use App\Actions\Facilities\CancelFacilityBookingAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Facilities\CancelFacilityBookingRequest;
use App\Http\Requests\Facilities\StoreFacilityBookingRequest;
use App\Models\FacilityBooking;
use App\Models\Landmark;
use App\Services\Facilities\FacilityAvailabilityPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Facility booking from the community map. Who may book or cancel is FacilityBookingPolicy.
 */
class FacilityBookingController extends Controller
{
    public function index(Request $request, FacilityAvailabilityPresenter $presenter): JsonResponse
    {
        $facility = Landmark::where('bookable', true)->find($request->integer('landmark_id'));
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query('date')) ? $request->query('date') : null;

        return response()->json($presenter->props($request->user(), $facility, $date));
    }

    public function store(StoreFacilityBookingRequest $request, BookFacilityAction $book): JsonResponse
    {
        $booking = $book->execute($request->user(), $request->validated());

        return response()->json([
            'reference' => $booking->reference,
            'facility' => $booking->landmark->name,
            'date' => $booking->booked_on->toDateString(),
            'slot' => $booking->slot,
            'guests' => $booking->guests,
            'fee_minor' => $booking->fee_minor,
            'fee_formatted' => $booking->feeFormatted(),
            'invoice_reference' => $booking->invoice?->reference,
        ], 201);
    }

    public function cancel(CancelFacilityBookingRequest $request, FacilityBooking $facilityBooking, CancelFacilityBookingAction $cancel): JsonResponse
    {
        $cancel->execute($facilityBooking, $request->user());

        return response()->json(['success' => true]);
    }
}
