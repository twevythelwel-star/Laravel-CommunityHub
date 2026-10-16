<?php

namespace App\Notifications\Maintenance;

use App\Models\MaintenanceIssue;
use App\Models\User;
use App\Notifications\EngineNotification;
use App\Services\NotificationEngine\NotificationPayload;

/** Tells a staff member they have been assigned a work order. */
class MaintenanceWorkAssigned extends EngineNotification
{
    public function __construct(public MaintenanceIssue $issue) {}

    public function toEngine(User $notifiable): NotificationPayload
    {
        return new NotificationPayload(
            title: "Assigned Work Order: {$this->issue->reference_number}",
            body: "You have been assigned to '{$this->issue->title}' at {$this->issue->location}.",
            user: $notifiable,
            actionUrl: '/dashboard/maintenance',
            metadata: [
                'category' => 'maintenance',
                'priority' => $this->issue->priority === 'emergency' ? 'urgent' : 'normal',
                'issue_id' => $this->issue->id,
            ],
        );
    }

    public function subject(): MaintenanceIssue
    {
        return $this->issue;
    }
}
