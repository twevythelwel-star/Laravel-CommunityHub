<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\BoundaryConfig;
use App\Models\DelegatedAccess;
use App\Models\Donation;
use App\Models\GatePass;
use App\Models\HouseholdMember;
use App\Models\Invoice;
use App\Models\Notification;
use App\Models\OwnershipEntity;
use App\Models\ParkingPass;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Renter;
use App\Models\Staff;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Visitor;
use App\Models\WebhookSubscription;
use App\Policies\AnnouncementPolicy;
use App\Policies\BoundaryPolicy;
use App\Policies\DelegatedAccessPolicy;
use App\Policies\DonationPolicy;
use App\Policies\GatePassPolicy;
use App\Policies\HouseholdMemberPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\OwnershipEntityPolicy;
use App\Policies\ParkingPassPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\PropertyPolicy;
use App\Policies\RenterPolicy;
use App\Policies\StaffPolicy;
use App\Policies\TransactionPolicy;
use App\Policies\UserPolicy;
use App\Policies\VehiclePolicy;
use App\Policies\VisitorPolicy;
use App\Policies\WebhookSubscriptionPolicy;
use App\Services\Occupancy\OccupancyCategoryResolver;
use App\Support\EntityAccess;
use Illuminate\Auth\Access\Response;
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
        Donation::class => DonationPolicy::class,
        HouseholdMember::class => HouseholdMemberPolicy::class,
        Renter::class => RenterPolicy::class,
        Staff::class => StaffPolicy::class,
        User::class => UserPolicy::class,
        DelegatedAccess::class => DelegatedAccessPolicy::class,
        Transaction::class => TransactionPolicy::class,
        WebhookSubscription::class => WebhookSubscriptionPolicy::class,
        Vehicle::class => VehiclePolicy::class,
        Property::class => PropertyPolicy::class,
        ParkingPass::class => ParkingPassPolicy::class,
        Payment::class => PaymentPolicy::class,
        Invoice::class => InvoicePolicy::class,
        BoundaryConfig::class => BoundaryPolicy::class,
        OwnershipEntity::class => OwnershipEntityPolicy::class,
        Notification::class => AnnouncementPolicy::class,
    ];

    public function boot(): void
    {
        Gate::policy(Visitor::class, VisitorPolicy::class);
        Gate::policy(GatePass::class, GatePassPolicy::class);
        Gate::policy(Donation::class, DonationPolicy::class);
        Gate::policy(HouseholdMember::class, HouseholdMemberPolicy::class);
        Gate::policy(Renter::class, RenterPolicy::class);
        Gate::policy(Staff::class, StaffPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(DelegatedAccess::class, DelegatedAccessPolicy::class);
        Gate::policy(Transaction::class, TransactionPolicy::class);
        Gate::policy(WebhookSubscription::class, WebhookSubscriptionPolicy::class);
        Gate::policy(Vehicle::class, VehiclePolicy::class);
        Gate::policy(Property::class, PropertyPolicy::class);
        Gate::policy(ParkingPass::class, ParkingPassPolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(BoundaryConfig::class, BoundaryPolicy::class);
        Gate::policy(OwnershipEntity::class, OwnershipEntityPolicy::class);
        Gate::policy(Notification::class, AnnouncementPolicy::class);

        // System Admin passes every gate unless a gate explicitly denies.
        Gate::before(function (User $user, string $ability) {
            return $user->role === UserRole::SystemAdmin ? true : null;
        });

        Gate::define('queryEntity', fn (User $user, string $entity) => EntityAccess::allows($user, $entity));

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
         | Running the platform rather than the estate: dispatching queue jobs,
         | load benchmarks, and the application and audit logs. The logs carry
         | residents' personal data, and an audit trail anyone could write to
         | would prove nothing, so this is the System Admin's alone.
         */
        Gate::define('operatePlatform', fn (User $user) => $user->role === UserRole::SystemAdmin);

        // Scramble's generated API reference (/docs/api) outside local: a map of every endpoint.
        Gate::define('viewApiDocs', fn (User $user) => $user->role === UserRole::SystemAdmin);

        /*
         | Issuing documents in the estate's name — invoices, receipts,
         | statements, certificates, letters, government forms — from content
         | the caller supplies. In anyone else's hands that is a forgery kit.
         */
        Gate::define('issueDocuments', fn (User $user) => $user->role->isAdministrative());

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

        // Residents and authorized entity representatives register their own visitors and household staff.
        Gate::define('registerVisitors', fn (User $user) => $user->role->isResident()
            || $user->role->isAdministrative()
            || $user->canManageVisitorsForAnyProperty());

        Gate::define('registerStaff', fn (User $user) => $user->role->isResident()
            || $user->role->isAdministrative());

        // Only deeded homeowners get homeowner-only functions; the pass policy
        // marks renters as restricted_from_homeowner_functions.
        Gate::define('accessHomeownerFunctions', fn (User $user) => $user->role === UserRole::Homeowner
            || $user->role->isAdministrative());

        // Residents in distress (and admins on their behalf) can trigger emergency panic dispatch.
        Gate::define('triggerEmergencyPanic', fn (User $user) => $user->role->isResident()
            || $user->role->isAdministrative());

        // Silent assistance. Residents and administrators keep a safety
        // profile, a duress PIN and the QR hold gesture; someone acting under
        // an active appointment may also ask for help; staff ask through their
        // own gate credential.
        Gate::define('keepSafetyProfile', fn (User $user) => $user->role->isResident()
            || $user->role->isAdministrative());
        Gate::define('requestSafetyAssistance', fn (User $user) => $user->can('keepSafetyProfile')
            || $user->activeAuthorizedAppointments()->exists());
        Gate::define('useStaffSafetyCredential', fn (User $user) => $user->role->isOperational());

        // Who runs the estate's occupancy picture and an emergency muster:
        // security, administrators and staff. They see every unit and export
        // the rosters; anyone else sees the totals and their own unit only.
        Gate::define('manageOccupancy', fn (User $user) => $user->role->isAdministrative()
            || $user->role->isSecurity()
            || $user->role->isStaff());

        // Who is at one unit: the occupancy managers, or that unit's own resident.
        Gate::define('viewUnitOccupancy', function (User $user, ?string $unit) {
            if ($user->can('manageOccupancy')) {
                return true;
            }

            $units = app(OccupancyCategoryResolver::class);
            $own = $units->normalizeUnit($user->lot);

            return $own !== null && strcasecmp((string) $units->normalizeUnit($unit), $own) === 0
                ? Response::allow()
                : Response::deny('You can only see who is at your own unit.');
        });
    }
}
