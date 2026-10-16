<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\GatePass\DeactivateAccountAction;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Directory\StoreDirectoryPropertyRequest;
use App\Http\Requests\Directory\StoreStaffRequest;
use App\Http\Requests\Directory\StoreUserRequest;
use App\Http\Requests\Directory\UpdateStaffRequest;
use App\Http\Requests\Directory\UpdateUserRequest;
use App\Models\Property;
use App\Models\Staff;
use App\Models\User;
use App\Services\People\DirectoryPresenter;
use App\Services\PropertyOwnershipService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Backs src/app/dashboard/directory/page.tsx and create-user-form.tsx.
 *
 * The page kept 175 lines of mock users in `useState`. Create, edit, status
 * toggle and delete all mutated that array and raised a toast; nothing reached
 * a server. `canManageDirectory` was a hardcoded `role === 'Admin'` check in
 * JSX rather than the `manageUsers` gate the routes actually enforce.
 *
 * **There is deliberately no delete.** The page offered one, behind a
 * confirmation dialog, with no endpoint behind it. Hard-deleting a user would
 * cascade through their visitors, warning responses and activity log — the
 * access history a gated community exists to keep. The app already models
 * removal properly as deactivation: `status`, `deactivated_at` and the
 * `EnsureUserIsActive` middleware that logs a deactivated account out on its
 * next request. Delete is now Deactivate, which is reversible and keeps the
 * record.
 */
class DirectoryController extends Controller
{
    public function __construct(private readonly PropertyOwnershipService $ownership) {}

    /** See DirectoryPresenter for what administrators and residents each see. */
    public function index(Request $request, DirectoryPresenter $presenter): Response
    {
        return Inertia::render('Dashboard/Directory', $presenter->props($request->user()));
    }

    public function storeStaff(StoreStaffRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $user = $request->user();

        Staff::create([
            ...$validated,
            'property_label' => $user->propertyLabel(),
            'added_by' => $user->id,
            'status' => 'Active',
        ]);

        $user->recordActivity("Registered staff member {$validated['name']}");

        return back()->with('success', 'Staff member registered.');
    }

    public function updateStaff(UpdateStaffRequest $request, Staff $staff): RedirectResponse
    {
        $this->authorize('update', $staff);

        $staff->update($request->staffAttributes());

        return back()->with('success', 'Staff record updated.');
    }

    public function destroyStaff(Staff $staff): RedirectResponse
    {
        $this->authorize('delete', $staff);

        $staff->delete();

        return back()->with('success', 'Staff member removed.');
    }

    public function storeUser(StoreUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $this->allowOrFail('assignRole', [User::class, $validated['role']], 'role');

        $created = User::create([
            ...$validated,
            'uid' => (string) Str::uuid(),
            'status' => 'Active',
        ]);

        $this->recordAccountProperty($created);

        $request->user()->recordActivity("Created user {$created->email}");

        return back()->with('success', 'User created.');
    }

    public function updateUser(UpdateUserRequest $request, User $user, DeactivateAccountAction $deactivate): RedirectResponse
    {
        $validated = $request->validated();
        if (isset($validated['role'])) {
            $this->allowOrFail('assignRole', [User::class, $validated['role']], 'role');
        }
        $this->allowOrFail('manageAccount', [$user], 'role');

        // Reactivating clears deactivated_at too: isActive() needs both, and an
        // account that deactivated itself carries one.
        $status = $validated['status'] ?? null;
        $user->update([...$validated, ...($status === 'Active' ? ['deactivated_at' => null] : [])]);

        if ($status === 'Inactive' && $user->wasChanged('status')) {
            $deactivate->execute($user, $request->user());
        }

        $this->recordAccountProperty($user);

        $request->user()->recordActivity("Updated user {$user->email}");

        return back()->with('success', 'User updated.');
    }

    /**
     * Records that a homeowner owns another property, which is then billed HOA
     * dues of its own.
     */
    public function storeProperty(StoreDirectoryPropertyRequest $request, User $user): RedirectResponse
    {
        $this->allowOrFail('assignProperty', [User::class, $user], 'lot_number');

        $validated = $request->validated();

        try {
            $property = $this->ownership->assign($user, $validated['lot_number'], $validated['street_address'] ?? null);
        } catch (\DomainException $e) {
            return back()->withErrors(['lot_number' => $e->getMessage()]);
        }

        $request->user()->recordActivity("Recorded {$property->label()} as owned by {$user->email}");

        return back()->with('success', "{$property->label()} added to {$user->display_name}'s properties.");
    }

    /**
     * The owner no longer holds the property: it stays on record, unowned, and
     * stops being billed to them. Invoices already issued are untouched.
     */
    public function destroyProperty(Request $request, Property $property): RedirectResponse
    {
        $owner = $property->owner;
        $this->allowOrFail('manageProperties', [User::class, $owner], 'lot_number');

        $this->ownership->release($property);

        $request->user()->recordActivity("Released {$property->label()}".($owner ? " from {$owner->email}" : ''));

        return back()->with('success', "{$property->label()} removed from ".($owner?->display_name ?? 'its owner')."'s properties.");
    }

    /**
     * A new homeowner's lot becomes their first recorded property. After
     * that, properties are managed explicitly: changing the lot on the
     * account does not move or add one.
     */
    private function recordAccountProperty(User $user): void
    {
        try {
            $this->ownership->recordAccountProperty($user);
        } catch (\DomainException) {
            // The lot is someone else's on record; the office resolves that in
            // the Properties dialog rather than the account form failing.
        }
    }

    /**
     * A UserPolicy denial, shown on the form field it concerns rather than
     * as a 403 page.
     *
     * @param  array<int, mixed>  $arguments
     */
    private function allowOrFail(string $ability, array $arguments, string $field): void
    {
        $response = Gate::inspect($ability, $arguments);

        if ($response->denied()) {
            throw ValidationException::withMessages([$field => $response->message()]);
        }
    }
}
