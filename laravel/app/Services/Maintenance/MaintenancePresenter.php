<?php

namespace App\Services\Maintenance;

use App\Enums\UserRole;
use App\Models\MaintenanceIssue;
use App\Models\MaintenanceIssueUpdate;
use App\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The props of the maintenance page (Dashboard/Maintenance). Residents see
 * their own reports and the public part of each timeline; those who triage
 * see every report, internal notes, and the staff they can assign.
 */
class MaintenancePresenter
{
    /**
     * @param  array{category?: ?string, status?: ?string, priority?: ?string, search?: ?string}  $filters
     * @return array<string, mixed>
     */
    public function props(User $viewer, array $filters): array
    {
        $canTriage = $viewer->can('triage', MaintenanceIssue::class);
        $scope = fn () => MaintenanceIssue::query()->when(! $canTriage, fn ($q) => $q->where('reporter_id', $viewer->id));

        return [
            'issues' => $this->filtered($scope(), $filters)
                ->with(['reporter:id,name,display_name,email,role,lot,street', 'assignedTo:id,name,display_name,role', 'updates.user:id,name,display_name,role'])
                ->latest()
                ->paginate(15)
                ->withQueryString()
                ->through(fn (MaintenanceIssue $issue) => $this->issue($issue, $canTriage)),
            'stats' => [
                'total' => $scope()->count(),
                'reported' => $scope()->where('status', 'reported')->count(),
                'in_progress' => $scope()->whereIn('status', ['triaged', 'assigned', 'in_progress'])->count(),
                'resolved' => $scope()->whereIn('status', ['resolved', 'closed'])->count(),
                'emergency' => $scope()->where('priority', 'emergency')->whereNotIn('status', ['resolved', 'closed', 'cancelled'])->count(),
            ],
            'staffRoster' => $canTriage ? $this->staffRoster() : [],
            'filters' => $filters,
            'canTriage' => $canTriage,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filtered(Builder $query, array $filters): Builder
    {
        return $query
            ->when(filled($filters['category'] ?? null), fn ($q) => $q->where('category', $filters['category']))
            ->when(filled($filters['status'] ?? null), fn ($q) => $q->where('status', $filters['status']))
            ->when(filled($filters['priority'] ?? null), fn ($q) => $q->where('priority', $filters['priority']))
            ->when(filled($filters['search'] ?? null), function ($q) use ($filters) {
                $term = '%'.$filters['search'].'%';
                $q->where(fn ($sub) => $sub->where('reference_number', 'like', $term)
                    ->orWhere('title', 'like', $term)
                    ->orWhere('location', 'like', $term)
                    ->orWhere('description', 'like', $term));
            });
    }

    /**
     * @return array<string, mixed>
     */
    private function issue(MaintenanceIssue $issue, bool $canTriage): array
    {
        return [
            'id' => $issue->id,
            'referenceNumber' => $issue->reference_number,
            'category' => $issue->category,
            'title' => $issue->title,
            'description' => $issue->description,
            'location' => $issue->location,
            'latitude' => $issue->latitude ? (float) $issue->latitude : null,
            'longitude' => $issue->longitude ? (float) $issue->longitude : null,
            'priority' => $issue->priority,
            'status' => $issue->status,
            'photos' => $issue->photos ?? [],
            'resolutionNotes' => $issue->resolution_notes,
            'reporter' => [...$this->person($issue->reporter), 'lot' => $issue->reporter->lot, 'street' => $issue->reporter->street],
            'assignedTo' => $issue->assignedTo ? $this->person($issue->assignedTo) : null,
            'assignedAt' => $issue->assigned_at?->toIso8601String(),
            'resolvedAt' => $issue->resolved_at?->toIso8601String(),
            'closedAt' => $issue->closed_at?->toIso8601String(),
            'createdAt' => $issue->created_at->toIso8601String(),
            'updatesCount' => $issue->updates->count(),
            // Internal notes are for those who triage.
            'updates' => $issue->updates
                ->filter(fn (MaintenanceIssueUpdate $u) => $canTriage || ! $u->is_internal)
                ->map(fn (MaintenanceIssueUpdate $u) => [
                    'id' => $u->id,
                    'fromStatus' => $u->from_status,
                    'toStatus' => $u->to_status,
                    'note' => $u->note,
                    'isInternal' => $u->is_internal,
                    'photos' => $u->photos ?? [],
                    'user' => $this->person($u->user),
                    'createdAt' => $u->created_at->toIso8601String(),
                ])->values(),
        ];
    }

    /**
     * @return array{id: int, name: string, role: string}
     */
    private function person(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->display_name ?: $user->name,
            'role' => $user->role->value,
        ];
    }

    /** Active administrators, staff and security, who can be assigned a work order. */
    private function staffRoster(): Collection
    {
        return User::query()
            ->whereIn('role', [UserRole::Staff, UserRole::Admin, UserRole::SystemAdmin, UserRole::Security])
            ->where('status', 'Active')
            ->orderBy('name')
            ->get(['id', 'name', 'display_name', 'role', 'email'])
            ->map(fn (User $u) => [...$this->person($u), 'email' => $u->email]);
    }
}
