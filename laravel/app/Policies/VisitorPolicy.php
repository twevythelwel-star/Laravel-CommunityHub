<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Visitor;

class VisitorPolicy
{
    /**
     * Determine whether the user can view any visitors.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('accessEstateInformation') || $user->can('manageSecurity');
    }

    /**
     * Determine whether the user can view the specific visitor.
     */
    public function view(User $user, Visitor $visitor): bool
    {
        return $user->can('manageSecurity') || $user->id === $visitor->homeowner_id;
    }

    /**
     * Determine whether the user can create/register visitors.
     */
    public function create(User $user): bool
    {
        return $user->can('registerVisitors');
    }

    /**
     * Determine whether the user can update the visitor pass.
     */
    public function update(User $user, Visitor $visitor): bool
    {
        return $user->can('manageSecurity') || $user->id === $visitor->homeowner_id;
    }

    /**
     * Determine whether the user can delete/cancel the visitor.
     */
    public function delete(User $user, Visitor $visitor): bool
    {
        return $user->can('manageSecurity') || $user->id === $visitor->homeowner_id;
    }

    /**
     * Determine whether security can check in the visitor.
     */
    public function checkIn(User $user, Visitor $visitor): bool
    {
        return $user->can('scanPasses');
    }
}
