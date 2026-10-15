<?php

namespace App\Services\People;

use App\Enums\UserRole;
use App\Models\Renter;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The props of the renters page (Dashboard/Renters): a homeowner sees the
 * stays at their own property; the estate office sees every stay and the
 * homeowners it can register a new one for.
 */
class RenterPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function props(User $viewer): array
    {
        $isHomeowner = $viewer->role === UserRole::Homeowner;
        $canRegister = $viewer->can('manageUsers');

        return [
            'renters' => Renter::with(['user', 'homeowner'])
                ->when($isHomeowner, fn ($q) => $q->where('homeowner_id', $viewer->id))
                ->orderBy('name')
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
            'canRegister' => $canRegister,
            // Whose property a new stay is for; only the estate office registers one.
            'homeowners' => $canRegister ? $this->homeowners() : [],
        ];
    }

    private function homeowners(): Collection
    {
        return User::where('role', UserRole::Homeowner)
            ->orderBy('name')
            ->get(['id', 'name', 'display_name', 'lot', 'street'])
            ->map(fn (User $h) => ['id' => $h->id, 'name' => $h->display_name ?: $h->name, 'property' => $h->propertyLabel()])
            ->values();
    }
}
