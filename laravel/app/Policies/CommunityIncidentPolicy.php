<?php

namespace App\Policies;

use App\Models\CommunityIncident;
use App\Models\User;

class CommunityIncidentPolicy
{
    /**
     * Determine whether the user can view the incidents feed.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view an incident.
     */
    public function view(User $user, CommunityIncident $incident): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view sensitive, confidential details and internal notes.
     */
    public function viewSensitiveDetails(User $user, CommunityIncident $incident): bool
    {
        return $user->can('manageSecurity');
    }

    /**
     * Determine whether the user can report an official incident.
     */
    public function create(User $user): bool
    {
        return $user->can('manageSecurity');
    }

    /**
     * Determine whether the user can update an incident.
     */
    public function update(User $user, CommunityIncident $incident): bool
    {
        return $user->can('manageSecurity');
    }

    /**
     * Determine whether the user can add updates to an incident.
     */
    public function addUpdate(User $user, CommunityIncident $incident): bool
    {
        return $user->can('manageSecurity');
    }

    /**
     * Determine whether the user can issue an all-clear message for an incident.
     */
    public function issueAllClear(User $user, CommunityIncident $incident): bool
    {
        return $user->can('manageSecurity');
    }

    /**
     * Determine whether the user can close an incident.
     */
    public function close(User $user, CommunityIncident $incident): bool
    {
        return $user->can('manageSecurity');
    }
}
