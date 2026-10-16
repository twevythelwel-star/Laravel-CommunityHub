<?php

namespace App\Policies;

use App\Models\User;

/**
 * Residents report issues and follow their own; administrators, staff and
 * security see every report and triage it (status, priority, assignment,
 * internal notes).
 */
class MaintenanceIssuePolicy
{
    public function triage(User $user): bool
    {
        return $user->role->isAdministrative() || $user->role->isOperational();
    }
}
