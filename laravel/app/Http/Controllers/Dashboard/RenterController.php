<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Renter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Backs src/app/dashboard/renters/page.tsx and register-renter-form.tsx. */
class RenterController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('accessHomeownerFunctions');

        $user = $request->user();
        $isHomeowner = $user->role === UserRole::Homeowner;

        $query = Renter::with(['user', 'homeowner'])
            ->when($isHomeowner, fn ($q) => $q->where('homeowner_id', $user->id))
            ->orderBy('name');

        return Inertia::render('Dashboard/Renters', [
            'renters' => $query
                ->paginate(25)
                ->through(fn (Renter $r) => [
                    'id' => $r->id,
                    'name' => $r->name,
                    'stayType' => $r->stay_type ?? 'Long-term (Renter)',
                    'contact' => $r->contact,
                    'notes' => $r->notes,
                    'status' => $r->leaseHasExpired() ? 'Expired' : $r->status,
                    'leaseStart' => $r->lease_start->toDateString(),
                    'leaseEnd' => $r->lease_end->toDateString(),
                    'lot' => $r->lot,
                    'street' => $r->street,
                    'expired' => $r->leaseHasExpired(),
                    'homeownerId' => $r->homeowner_id,
                    'homeownerName' => $r->homeowner?->display_name ?? $r->homeowner?->name,
                ]),
            'isHomeowner' => $isHomeowner,
            'propertyLot' => $user->lot,
            'propertyStreet' => $user->street,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('accessHomeownerFunctions');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'stay_type' => ['required', 'in:Long-term (Renter),Short-term (Airbnb)'],
            'contact' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:500'],
            'lease_start' => ['required', 'date'],
            'lease_end' => ['required', 'date', 'after_or_equal:lease_start'],
            'lot' => ['nullable', 'string', 'max:60'],
            'street' => ['nullable', 'string', 'max:120'],
            'user_id' => ['nullable', 'exists:users,id'],
        ]);

        $user = $request->user();

        $lot = $validated['lot'] ?? ($user->lot ?: 'Lot 14');
        $street = $validated['street'] ?? ($user->street ?: 'Hibiscus Way');
        $homeownerId = $user->role === UserRole::Homeowner ? $user->id : ($user->id);

        Renter::create([
            ...$validated,
            'homeowner_id' => $homeownerId,
            'lot' => $lot,
            'street' => $street,
            'status' => 'Active',
        ]);

        $user->recordActivity("Registered temporary homeowner {$validated['name']} ({$validated['stay_type']})");

        return back()->with('success', 'Temporary homeowner registered.');
    }

    public function update(Request $request, Renter $renter): RedirectResponse
    {
        $this->authorize('accessHomeownerFunctions');

        $user = $request->user();
        if ($user->role === UserRole::Homeowner && $renter->homeowner_id !== $user->id) {
            abort(403, 'You can only update temporary homeowners registered under your property.');
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'stay_type' => ['sometimes', 'in:Long-term (Renter),Short-term (Airbnb)'],
            'contact' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:500'],
            'lease_start' => ['sometimes', 'date'],
            'lease_end' => ['sometimes', 'date', 'after_or_equal:lease_start'],
            'status' => ['sometimes', 'in:Active,Inactive'],
        ]);

        $renter->update($validated);

        $user->recordActivity("Updated temporary homeowner {$renter->name}");

        return back()->with('success', 'Temporary homeowner updated.');
    }

    public function destroy(Request $request, Renter $renter): RedirectResponse
    {
        $this->authorize('accessHomeownerFunctions');

        $user = $request->user();
        if ($user->role === UserRole::Homeowner && $renter->homeowner_id !== $user->id) {
            abort(403, 'You can only remove temporary homeowners registered under your property.');
        }

        $renterName = $renter->name;
        $renter->delete();

        $user->recordActivity("Removed temporary homeowner {$renterName}");

        return back()->with('success', 'Temporary homeowner removed.');
    }
}
