<?php

namespace App\Http\Controllers\Dashboard;

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
        $this->authorize('manageUsers');

        return Inertia::render('Dashboard/Renters', [
            'renters' => Renter::with('user')
                ->orderBy('name')
                ->paginate(25)
                ->through(fn (Renter $r) => [
                    'id' => $r->id,
                    'name' => $r->name,
                    'status' => $r->leaseHasExpired() ? 'Inactive' : $r->status,
                    'leaseStart' => $r->lease_start->toDateString(),
                    'leaseEnd' => $r->lease_end->toDateString(),
                    'lot' => $r->lot,
                    'street' => $r->street,
                    'expired' => $r->leaseHasExpired(),
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manageUsers');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'user_id' => ['nullable', 'exists:users,id'],
            'lease_start' => ['required', 'date'],
            'lease_end' => ['required', 'date', 'after:lease_start'],
            'lot' => ['nullable', 'string', 'max:60'],
            'street' => ['nullable', 'string', 'max:120'],
        ]);

        Renter::create([...$validated, 'status' => 'Active']);

        return back()->with('success', 'Renter registered.');
    }

    public function update(Request $request, Renter $renter): RedirectResponse
    {
        $this->authorize('manageUsers');

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'lease_start' => ['sometimes', 'date'],
            'lease_end' => ['sometimes', 'date', 'after:lease_start'],
            'status' => ['sometimes', 'in:Active,Inactive'],
        ]);

        $renter->update($validated);

        return back()->with('success', 'Renter updated.');
    }

    public function destroy(Renter $renter): RedirectResponse
    {
        $this->authorize('manageUsers');

        $renter->delete();

        return back()->with('success', 'Renter removed.');
    }
}
