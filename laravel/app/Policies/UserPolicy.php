<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * The System Admin tier, on top of `manageUsers`: only a System Admin may
 * grant System Admin, change a System Admin's account, or manage an
 * administrator's properties. Without these, `manageUsers` was enough to
 * deactivate the only account that can grant System Admin and take the
 * tier over.
 *
 * The directory shows a denial as an error on the form field, so each one
 * carries its message.
 */
class UserPolicy
{
    public function assignRole(User $actor, string $role): Response
    {
        return $role !== UserRole::SystemAdmin->value || $this->isSystemAdmin($actor)
            ? Response::allow()
            : Response::deny('Only a System Admin can grant System Admin.');
    }

    public function manageAccount(User $actor, User $account): Response
    {
        return $account->role !== UserRole::SystemAdmin || $this->isSystemAdmin($actor)
            ? Response::allow()
            : Response::deny('Only a System Admin can modify a System Admin account.');
    }

    public function manageProperties(User $actor, ?User $owner): Response
    {
        return ! $owner?->role->isAdministrative() || $this->isSystemAdmin($actor)
            ? Response::allow()
            : Response::deny("Only a System Admin can manage an administrator's properties.");
    }

    private function isSystemAdmin(User $user): bool
    {
        return $user->role === UserRole::SystemAdmin;
    }
}
