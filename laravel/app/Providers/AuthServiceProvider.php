<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\GatePass;
use App\Models\User;
use App\Models\Visitor;
use App\Policies\GatePassPolicy;
use App\Policies\VisitorPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Central authorization map for the six roles and domain policies.
 *
 * In the original app these checks were scattered through JSX as
 * `user.role === 'Admin' && ...`, which only hid UI — the underlying data was
 * still reachable. Defining them as gates means the server enforces them and
 * the same booleans drive menu visibility via HandleInertiaRequests.
 */
class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model-to-policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected array $policies = [
        Visitor::class => VisitorPolicy::class,
        GatePass::class => GatePassPolicy::class,
    ];

    public function boot(): void
    {
        Gate::policy(Visitor::class, VisitorPolicy::class);
        Gate::policy(GatePass::class, GatePassPolicy::class);

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

        Gate::define('manageBoundary', fn (User $user) => $user->role === UserRole::SystemAdmin);
        Gate::define('manageLandmarks', fn (User $user) => $user->role->isAdministrative());
        Gate::define('viewBoundary', fn (User $user) => $user->role->isAdministrative()
            || $user->role === UserRole::Security);

        /*
         | The estate's own information: the map, the perks directory, the
         | rules, the visitor book and the blocklist.
         |
         | Staff hold a gate pass and nothing else. They are not resident here
         | and they do not police the gate, and the sidebar has never offered
         | them any of these pages — but every one of the routes was open, so
         | the whole set was reachable by typing the URL.
         */
        Gate::define('accessEstateInformation', fn (User $user) => $user->role->isAdministrative()
            || $user->role->isResident()
            || $user->role === UserRole::Security);

        /*
         | Resident community life: fundraising, the events calendar and estate
         | updates. Security and Staff are employed by the estate rather than
         | living in it, which is what the menu has always said and what the
         | routes now enforce.
         */
        Gate::define('accessCommunityLife', fn (User $user) => $user->role->isAdministrative()
            || $user->role->isResident());

        /* The application's own release history. */
        Gate::define('viewAppChangelog', fn (User $user) => $user->role === UserRole::SystemAdmin);

        /*
         | Who may open the payments area at all: administrators, Homeowners and
         | Temporary Homeowners. Security and Staff are not billed by the estate
         | and have no invoices, so the page held nothing for them — and merely
         | loading it created a Wallet row against their account, because
         | BillingController::index() calls Wallet::firstOrCreate().
         |
         | `manageBilling` below is the narrower right to change the estate's
         | rates and mark other households' invoices paid.
         */
        Gate::define('accessBilling', fn (User $user) => $user->role->isAdministrative()
            || $user->role->isResident());

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
