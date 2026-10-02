<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
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
    public function index(Request $request): Response
    {
        $user = $request->user();
        $isAdmin = $user->can('manageUsers');

        return Inertia::render('Dashboard/Directory', [
            /*
             | Administrators see every account whatever its status, because
             | they are the ones who reactivate a deactivated one — scoping this
             | to active() would have made deactivation a one-way door.
             | Everyone else sees the resident directory, active only.
             */
            'residents' => User::query()
                ->when(! $isAdmin, fn ($q) => $q
                    ->active()
                    ->whereIn('role', [
                        UserRole::Homeowner->value,
                        UserRole::TemporaryHomeowner->value,
                    ]))
                ->orderBy('display_name')
                ->get()
                ->map(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->display_name,
                    'role' => $u->role->value,
                    'lot' => $u->lot,
                    'street' => $u->street,
                    'title' => $u->title,
                    'avatarUrl' => $u->avatar_url,
                    // Contact details are only exposed to administrators.
                    'email' => $isAdmin ? $u->email : null,
                    'phone' => $isAdmin ? $u->phone : null,
                    'status' => $u->status,
                    'createdAt' => $u->created_at?->toIso8601String(),
                    // Drives the self-lockout guard in the UI, which updateUser
                    // also enforces server-side.
                    'isSelf' => $u->is($user),
                ]),

            'staff' => Staff::query()
                ->when(! $isAdmin, fn ($q) => $q->where('added_by', $user->id))
                ->orderBy('name')
                ->get()
                ->map(fn (Staff $s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'job' => $s->job,
                    'idType' => $s->id_type,
                    'idNumber' => $isAdmin ? $s->id_number : null,
                    'idExpiry' => $s->id_expiry->toDateString(),
                    'idImageUrl' => $s->id_image_url,
                    'property' => $s->property,
                    'status' => $s->effectiveStatus(),
                    'photoUrl' => $s->photo_url,
                ]),

            'canManageUsers' => $isAdmin,
            'roles' => array_column(UserRole::cases(), 'value'),
        ]);
    }

    public function storeStaff(Request $request): RedirectResponse
    {
        $this->authorize('registerStaff');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'job' => ['required', 'string', 'max:120'],
            'id_type' => ['required', 'string', 'max:60'],
            'id_number' => ['required', 'string', 'max:60'],
            'id_expiry' => ['required', 'date', 'after:today'],
            'id_image_url' => ['nullable', 'url', 'max:2048'],
            'photo_url' => ['nullable', 'url', 'max:2048'],
        ]);

        $user = $request->user();

        Staff::create([
            ...$validated,
            'property' => $user->propertyLabel(),
            'added_by' => $user->id,
            'status' => 'Active',
        ]);

        $user->recordActivity("Registered staff member {$validated['name']}");

        return back()->with('success', 'Staff member registered.');
    }

    public function updateStaff(Request $request, Staff $staff): RedirectResponse
    {
        $this->authorizeStaff($request, $staff);

        // Mirrors every field the StaffForm submits, so an edit does not silently
        // discard the ID details the form displayed.
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'job' => ['sometimes', 'string', 'max:120'],
            'property' => ['sometimes', 'string', 'max:160'],
            'id_type' => ['sometimes', 'string', 'max:60'],
            'id_number' => ['sometimes', 'string', 'max:60'],
            'id_expiry' => ['sometimes', 'date'],
            'photo_url' => ['nullable', 'url', 'max:2048'],
            // "Expired ID" is derived from id_expiry, never set directly.
            'status' => ['sometimes', 'in:Active,Inactive'],
        ]);

        $staff->update($validated);

        return back()->with('success', 'Staff record updated.');
    }

    public function destroyStaff(Request $request, Staff $staff): RedirectResponse
    {
        $this->authorizeStaff($request, $staff);

        $staff->delete();

        return back()->with('success', 'Staff member removed.');
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'display_name' => ['nullable', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:40'],
            'role' => ['required', 'string', 'in:'.implode(',', array_column(UserRole::cases(), 'value'))],
            'title' => ['nullable', 'string', 'max:120'],
            'lot' => ['nullable', 'string', 'max:60'],
            'street' => ['nullable', 'string', 'max:120'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        // Only a System Admin may mint another System Admin.
        if ($validated['role'] === UserRole::SystemAdmin->value
            && $request->user()->role !== UserRole::SystemAdmin) {
            return back()->withErrors(['role' => 'Only a System Admin can create another System Admin.']);
        }

        $created = User::create([
            ...$validated,
            'uid' => (string) Str::uuid(),
            'status' => 'Active',
        ]);

        $request->user()->recordActivity("Created user {$created->email}");

        return back()->with('success', 'User created.');
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'display_name' => ['sometimes', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'role' => ['sometimes', 'string', 'in:'.implode(',', array_column(UserRole::cases(), 'value'))],
            'title' => ['nullable', 'string', 'max:120'],
            'lot' => ['nullable', 'string', 'max:60'],
            'street' => ['nullable', 'string', 'max:120'],
            'status' => ['sometimes', 'in:Active,Inactive'],
        ]);

        $actor = $request->user();

        if (($validated['role'] ?? null) === UserRole::SystemAdmin->value
            && $actor->role !== UserRole::SystemAdmin) {
            return back()->withErrors(['role' => 'Only a System Admin can grant System Admin.']);
        }

        /*
         | An Admin must not be able to edit, demote or deactivate a System
         | Admin. Without this, `manageUsers` was enough to deactivate the only
         | account that can grant System Admin and take the tier over.
         */
        if ($user->role === UserRole::SystemAdmin && $actor->role !== UserRole::SystemAdmin) {
            return back()->withErrors(['role' => 'Only a System Admin can modify a System Admin account.']);
        }

        // Guard against an admin locking themselves out.
        if ($user->is($actor) && ($validated['status'] ?? 'Active') === 'Inactive') {
            return back()->withErrors(['status' => 'You cannot deactivate your own account here.']);
        }

        $user->update($validated);

        if (! $user->isActive()) {
            $user->tokens()->delete();
        }

        $actor->recordActivity("Updated user {$user->email}");

        return back()->with('success', 'User updated.');
    }

    private function authorizeStaff(Request $request, Staff $staff): void
    {
        $user = $request->user();

        if ($staff->added_by !== $user->id && ! $user->can('manageUsers')) {
            abort(403, 'You can only manage staff you registered.');
        }
    }
}
