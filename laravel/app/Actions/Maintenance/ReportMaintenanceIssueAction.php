<?php

namespace App\Actions\Maintenance;

use App\Enums\UserRole;
use App\Models\InAppNotification;
use App\Models\MaintenanceIssue;
use App\Models\MaintenanceIssueUpdate;
use App\Models\User;
use App\Services\Maintenance\MaintenancePhotos;
use Illuminate\Http\UploadedFile;

/**
 * A resident reports a maintenance issue: it gets a reference number and
 * the first timeline entry, and an emergency alerts administrators, staff
 * and security in-app straight away.
 */
class ReportMaintenanceIssueAction
{
    public function __construct(private readonly MaintenancePhotos $photos) {}

    /**
     * @param  array{category: string, title: string, description: string, location: string, latitude?: ?float, longitude?: ?float, priority: string}  $validated
     * @param  array<int, UploadedFile>|null  $photos
     */
    public function execute(User $reporter, array $validated, ?array $photos): MaintenanceIssue
    {
        $issue = MaintenanceIssue::create([
            'reference_number' => MaintenanceIssue::generateReferenceNumber(),
            'reporter_id' => $reporter->id,
            'category' => $validated['category'],
            'title' => $validated['title'],
            'description' => $validated['description'],
            'location' => $validated['location'],
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'priority' => $validated['priority'],
            'status' => 'reported',
            'photos' => $this->photos->store($photos),
        ]);

        MaintenanceIssueUpdate::create([
            'maintenance_issue_id' => $issue->id,
            'user_id' => $reporter->id,
            'from_status' => null,
            'to_status' => 'reported',
            'note' => 'Issue reported by resident.',
            'is_internal' => false,
        ]);

        if ($issue->priority === 'emergency') {
            $this->alertResponders($issue);
        }

        return $issue;
    }

    private function alertResponders(MaintenanceIssue $issue): void
    {
        User::whereIn('role', [UserRole::Admin, UserRole::Staff, UserRole::Security])->get()
            ->each(fn (User $staff) => InAppNotification::create([
                'user_id' => $staff->id,
                'title' => "URGENT Hazard Reported: {$issue->reference_number}",
                'body' => "Emergency issue '{$issue->title}' reported at {$issue->location}.",
                'category' => 'maintenance',
                'priority' => 'urgent',
                'action_url' => '/dashboard/maintenance',
            ]));
    }
}
