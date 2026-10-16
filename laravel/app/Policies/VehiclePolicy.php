<?php

namespace App\Policies;

use App\Models\HouseholdMember;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Auth\Access\Response;

class VehiclePolicy
{
    /**
     * Seeing all estate vehicles: security and administration.
     */
    public function viewAny(User $user): bool
    {
        return $user->role->isAdministrative() || $user->role->isOperational();
    }

    /**
     * Seeing a vehicle's record (owner, property, parking): gate staff see any
     * plate, everyone else only their own.
     */
    public function view(User $user, Vehicle $vehicle): bool
    {
        return (int) $vehicle->user_id === $user->id || $user->can('scanPasses');
    }

    /**
     * Editing or removing it: its owner, or security and administration.
     * Any account could once edit or delete any vehicle by id, its ANPR flag
     * and status included.
     */
    public function manage(User $user, Vehicle $vehicle): Response
    {
        return (int) $vehicle->user_id === $user->id || $user->can('manageSecurity')
            ? Response::allow()
            : Response::deny('You can only change your own vehicles.');
    }

    /** A vehicle can only be tied to a member of the user's own household. */
    public function assignToMember(User $user, ?int $memberId): Response
    {
        if ($memberId === null || $user->role->isAdministrative()) {
            return Response::allow();
        }

        return HouseholdMember::whereKey($memberId)->whereHas('household', fn ($q) => $q->where('primary_homeowner_id', $user->id))->exists()
            ? Response::allow()
            : Response::deny('That person is not in your household.');
    }
}
