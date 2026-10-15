<?php

namespace App\Policies;

use App\Models\Staff;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * A resident manages the household staff they registered; administrators
 * manage all of them.
 */
class StaffPolicy
{
    public function update(User $user, Staff $staff): Response
    {
        return $staff->added_by === $user->id || $user->can('manageUsers')
            ? Response::allow()
            : Response::deny('You can only manage staff you registered.');
    }

    public function delete(User $user, Staff $staff): Response
    {
        return $this->update($user, $staff);
    }
}
