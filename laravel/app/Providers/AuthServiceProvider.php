<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Central authorization map for the six roles.
 *
 * In the original app these checks were scattered through JSX as
 * `user.role === 'Admin' && ...`, which only hid UI — the underlying data was
 * still reachable. Defining them as gates means the server enforces them and
 * the same booleans drive menu visibility via HandleInertiaRequests.
 */
class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // System Admin passes every gate unless a gate explicitly denies.
        Gate::before(function (User $user, string $ability) {
            return $user->role === UserRole::SystemAdmin ? true : null;
        });

        Gate::define('manageUsers', fn (User $user) => $user->role->isAdministrative());

        Gate::define('manageSecurity', fn (User $user) => in_array(
            $user->role,
            [UserRole::SystemAdmin, UserRole::Admin, UserRole::Security],
            true,
        ));

        Gate::define('scanPasses', fn (User $user) => in_array(
            $user->role,
            [UserRole::SystemAdmin, UserRole::Admin, UserRole::Security],
            true,
        ));

        Gate::define('manageBoundary', fn (User $user) => $user->role->isAdministrative());

        Gate::define('manageBilling', fn (User $user) => $user->role->isAdministrative());

        Gate::define('reviewFeedback', fn (User $user) => $user->role->isAdministrative());

        Gate::define('manageBlocklist', fn (User $user) => in_array(
            $user->role,
            [UserRole::SystemAdmin, UserRole::Admin, UserRole::Security],
            true,
        ));

        Gate::define('broadcastNotices', fn (User $user) => $user->role->isAdministrative());

        Gate::define('manageFundraisers', fn (User $user) => $user->role->isAdministrative());

        // Residents register their own visitors and household staff.
        Gate::define('registerVisitors', fn (User $user) => $user->role->isResident()
            || $user->role->isAdministrative());

        Gate::define('registerStaff', fn (User $user) => $user->role->isResident()
            || $user->role->isAdministrative());

        // Only deeded homeowners get homeowner-only functions; the pass policy
        // marks renters as restricted_from_homeowner_functions.
        Gate::define('accessHomeownerFunctions', fn (User $user) => $user->role === UserRole::Homeowner
            || $user->role->isAdministrative());
    }
}
