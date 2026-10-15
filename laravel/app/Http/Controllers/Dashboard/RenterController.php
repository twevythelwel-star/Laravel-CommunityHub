<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Renters\StoreRenterRequest;
use App\Http\Requests\Renters\UpdateRenterRequest;
use App\Models\Property;
use App\Models\Renter;
use App\Models\User;
use App\Services\People\RenterPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Backs src/app/dashboard/renters/page.tsx and register-renter-form.tsx. */
class RenterController extends Controller
{
    /** See RenterPresenter for what homeowners and the estate office each see. */
    public function index(Request $request, RenterPresenter $presenter): Response
    {
        $this->authorize('viewAny', Renter::class);

        return Inertia::render('Dashboard/Renters', $presenter->props($request->user()));
    }

    /**
     * Registering a resident is the estate office's: a temporary homeowner's
     * stay is what lets their account sign in and register visitors. The
     * administrator says whose property it is; the address is that homeowner's.
     */
    public function store(StoreRenterRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $homeowner = User::findOrFail($validated['homeowner_id']);
        $lot = $validated['lot'] ?? $homeowner->lot;

        Renter::create([
            ...$validated,
            'property_id' => $this->rentedProperty($homeowner, $validated['lot'] ?? null)?->id,
            'lot' => $lot,
            'street' => $validated['street'] ?? $homeowner->street,
            'status' => 'Active',
        ]);

        $request->user()->recordActivity("Registered temporary homeowner {$validated['name']} ({$validated['stay_type']}) for {$homeowner->propertyLabel()}");

        return back()->with('success', 'Temporary homeowner registered.');
    }

    /**
     * Which of the homeowner's properties the stay is for: the one at the
     * lot given, or their only one. An owner of several with no lot given
     * gets none recorded rather than a guess.
     */
    private function rentedProperty(User $homeowner, ?string $lot): ?Property
    {
        $owned = $homeowner->properties()->orderBy('id')->get();
        $bare = fn (?string $value) => strtolower(preg_replace('/^(unit|lot|#)\s*/i', '', trim((string) $value)));

        if (filled($lot)) {
            return $owned->first(fn (Property $p) => $bare($p->lot_number) === $bare($lot));
        }

        return $owned->count() === 1 ? $owned->first() : null;
    }

    public function update(UpdateRenterRequest $request, Renter $renter): RedirectResponse
    {
        $validated = $request->validated();
        $renter->update($validated);

        $request->user()->recordActivity("Updated temporary homeowner {$renter->name}");

        return back()->with('success', 'Temporary homeowner updated.');
    }

    public function destroy(Request $request, Renter $renter): RedirectResponse
    {
        $this->authorize('delete', $renter);

        $renterName = $renter->name;
        $renter->delete();

        $request->user()->recordActivity("Removed temporary homeowner {$renterName}");

        return back()->with('success', 'Temporary homeowner removed.');
    }
}
