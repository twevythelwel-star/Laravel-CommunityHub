<?php

namespace App\Policies;

use App\Models\DelegatedAccess;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * An authorization is managed (rules, passes, emergency mode, revocation,
 * audit trail) by the homeowner who granted it, or by an administrator.
 */
class DelegatedAccessPolicy
{
    public function manage(User $user, DelegatedAccess $delegation): Response
    {
        return $user->id === $delegation->grantor_user_id || $user->role->isAdministrative()
            ? Response::allow()
            : Response::deny('Unauthorized access to this delegation boundary.');
    }
}
