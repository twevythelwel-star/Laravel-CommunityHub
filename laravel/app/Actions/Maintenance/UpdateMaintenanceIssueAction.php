<?php

namespace App\Actions\Maintenance;

use App\Models\MaintenanceIssue;
use App\Models\MaintenanceIssueUpdate;
use App\Models\User;
use App\Notifications\Maintenance\MaintenanceIssueClosedOut;
use App\Notifications\Maintenance\MaintenanceWorkAssigned;
use App\Services\Maintenance\MaintenancePhotos;
use Illuminate\Http\UploadedFile;

/**
 * Triage of a maintenance issue: status, priority, assignment, notes and
 * photos. Assigning someone tells them in-app (and moves a fresh report to
 * "assigned"); resolving or closing tells the resident who reported it.
 * Every change leaves a timeline entry, internal or public.
 */
class UpdateMaintenanceIssueAction
{
    public function __construct(private readonly MaintenancePhotos $photos) {}

    /**
     * @param  array<string, mixed>  $validated
     * @param  array<int, UploadedFile>|null  $photos
     */
    public function execute(MaintenanceIssue $issue, User $actor, array $validated, ?array $photos): void
    {
        $fromStatus = $issue->status;
        $toStatus = $validated['status'] ?? $fromStatus;
        $resolutionNotes = $validated['resolution_notes'] ?? null;

        $assignmentChanged = array_key_exists('assigned_to_user_id', $validated)
            && $validated['assigned_to_user_id'] != $issue->assigned_to_user_id;
        if ($assignmentChanged) {
            $toStatus = $this->assign($issue, $validated['assigned_to_user_id'], $toStatus);
        }

        if (! empty($validated['priority'])) {
            $issue->priority = $validated['priority'];
        }

        $statusChanged = $fromStatus !== $toStatus;
        $this->moveTo($issue, $actor, $toStatus, $resolutionNotes);
        $issue->save();

        $this->logTimeline($issue, $actor, $fromStatus, $toStatus, $validated, $assignmentChanged, $statusChanged, $this->photos->store($photos));

        if ($statusChanged && in_array($toStatus, ['resolved', 'closed'], true)) {
            $this->tellReporter($issue, $toStatus, $resolutionNotes);
        }
    }

    /** Assign (or unassign) the work order; returns the status it should move to. */
    private function assign(MaintenanceIssue $issue, ?int $assigneeId, string $toStatus): string
    {
        $issue->assigned_to_user_id = $assigneeId;
        $issue->assigned_at = $assigneeId ? now() : null;

        if (! $assigneeId) {
            return $toStatus;
        }

        User::find($assigneeId)?->notify(new MaintenanceWorkAssigned($issue));

        // A fresh report moves on to "assigned" unless the status was set explicitly.
        return $toStatus === 'reported' ? 'assigned' : $toStatus;
    }

    private function moveTo(MaintenanceIssue $issue, User $actor, string $toStatus, ?string $resolutionNotes): void
    {
        $issue->status = $toStatus;

        if ($toStatus === 'resolved') {
            $issue->resolved_at = now();
        } elseif ($toStatus === 'closed') {
            $issue->closed_at = now();
            $issue->closed_by_user_id = $actor->id;
        }

        if ($resolutionNotes && in_array($toStatus, ['resolved', 'closed'], true)) {
            $issue->resolution_notes = $resolutionNotes;
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  list<array<string, mixed>>  $photos
     */
    private function logTimeline(MaintenanceIssue $issue, User $actor, string $fromStatus, string $toStatus, array $validated, bool $assignmentChanged, bool $statusChanged, array $photos): void
    {
        $note = ($validated['note'] ?? null) ?: (($validated['resolution_notes'] ?? null) ?: null);

        if (! $note && $assignmentChanged) {
            $note = $issue->assignedTo ? "Assigned to {$issue->assignedTo->display_name}" : 'Unassigned';
        } elseif (! $note && $statusChanged) {
            $note = 'Status updated from '.ucwords(str_replace('_', ' ', $fromStatus)).' to '.ucwords(str_replace('_', ' ', $toStatus));
        }

        if (! $note && $photos === []) {
            return;
        }

        MaintenanceIssueUpdate::create([
            'maintenance_issue_id' => $issue->id,
            'user_id' => $actor->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'note' => $note,
            'is_internal' => (bool) ($validated['is_internal'] ?? false),
            'photos' => $photos,
        ]);
    }

    private function tellReporter(MaintenanceIssue $issue, string $toStatus, ?string $resolutionNotes): void
    {
        $issue->reporter?->notify(new MaintenanceIssueClosedOut($issue, $toStatus, $resolutionNotes));
    }
}
