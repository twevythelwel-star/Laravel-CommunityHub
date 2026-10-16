<?php

namespace App\Policies;

use App\Models\Property;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PropertyPolicy
{
    /**
     * Determine whether the user can view the property details.
     */
    public function view(User $user, Property $property): Response
    {
        if ($user->role->isAdministrative()) {
            return Response::allow();
        }

        if ($property->owner_user_id === $user->id || $user->properties()->whereKey($property->id)->exists()) {
            return Response::allow();
        }

        if ($user->allAuthorizedProperties()->contains('id', $property->id)) {
            return Response::allow();
        }

        if ($user->lot && (strcasecmp((string) $property->lot_number, (string) $user->lot) === 0 || strcasecmp((string) $property->property_code, (string) $user->lot) === 0)) {
            return Response::allow();
        }

        return Response::deny('You are not authorized to view the requested property.');
    }

    /**
     * Determine whether the user can manage the property.
     */
    public function manage(User $user, Property $property): Response
    {
        if ($user->role->isAdministrative()) {
            return Response::allow();
        }

        if ($property->owner_user_id === $user->id || $user->properties()->whereKey($property->id)->exists()) {
            return Response::allow();
        }

        if ($user->allAuthorizedProperties()->contains('id', $property->id)) {
            return Response::allow();
        }

        return Response::deny('You are not authorized to manage the requested property.');
    }
}
