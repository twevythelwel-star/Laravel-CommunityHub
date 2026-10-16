<?php

namespace App\Policies;

use App\Models\BoundaryConfig;
use App\Models\Community;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BoundaryPolicy
{
    /**
     * Determine whether the user can view the boundary.
     */
    public function view(User $user): bool
    {
        return $user->can('viewBoundary');
    }

    /**
     * Determine whether the user can manage the boundary.
     */
    public function manage(User $user, ?BoundaryConfig $boundaryConfig = null): Response
    {
        if (! $user->can('manageBoundary')) {
            return Response::deny('You do not have permission to manage boundaries.');
        }

        if ($boundaryConfig) {
            $community = app(Community::class) ?? Community::default();
            if ($boundaryConfig->community_id !== $community->id) {
                return Response::deny('Boundary configuration does not belong to this community.');
            }
        }

        return Response::allow();
    }
}
