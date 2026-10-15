<?php

namespace App\Services\People;

use App\Enums\UserRole;
use App\Models\Property;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The props of the directory page (Dashboard/Directory).
 *
 * Administrators see every account whatever its status, because they are
 * the ones who reactivate a deactivated one — scoping this to active()
 * would have made deactivation a one-way door — and every household staff
 * record, with contact and ID details. Everyone else sees active residents
 * and the staff they registered themselves.
 */
class DirectoryPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function props(User $viewer): array
    {
        $isAdmin = $viewer->can('manageUsers');

        return [
            'residents' => $this->accounts($viewer, $isAdmin),
            'staff' => $this->staff($viewer, $isAdmin),
            'canManageUsers' => $isAdmin,
            'roles' => array_column(UserRole::cases(), 'value'),
        ];
    }

    private function accounts(User $viewer, bool $isAdmin): Collection
    {
        return User::query()
            ->when(! $isAdmin, fn ($q) => $q
                ->active()
                ->whereIn('role', [UserRole::Homeowner->value, UserRole::TemporaryHomeowner->value]))
            ->orderBy('display_name')
            ->with('properties')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->display_name,
                'role' => $u->role->value,
                'lot' => $u->lot,
                'street' => $u->street,
                // What they own: HOA dues are charged per property.
                'properties' => $u->properties
                    ->sortBy('id')
                    ->map(fn (Property $p) => ['id' => $p->id, 'label' => $p->label(), 'code' => $p->property_code])
                    ->values(),
                'title' => $u->title,
                'avatarUrl' => $u->avatar_url,
                'email' => $isAdmin ? $u->email : null,
                'phone' => $isAdmin ? $u->phone : null,
                'status' => $u->status,
                'createdAt' => $u->created_at?->toIso8601String(),
                // Drives the self-lockout guard in the UI, which
                // UpdateUserRequest also enforces server-side.
                'isSelf' => $u->is($viewer),
            ]);
    }

    private function staff(User $viewer, bool $isAdmin): Collection
    {
        return Staff::query()
            ->when(! $isAdmin, fn ($q) => $q->where('added_by', $viewer->id))
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
                'property' => $s->property_label,
                'status' => $s->effectiveStatus(),
                'photoUrl' => $s->photo_url,
            ]);
    }
}
