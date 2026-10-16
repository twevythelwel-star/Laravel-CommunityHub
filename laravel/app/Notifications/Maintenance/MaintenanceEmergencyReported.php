<?php

namespace App\Notifications\Maintenance;

use App\Models\MaintenanceIssue;
use App\Models\User;
use App\Notifications\EngineNotification;
use App\Services\NotificationEngine\NotificationPayload;

/** Tells responders that a resident has reported an emergency hazard. */
class MaintenanceEmergencyReported extends EngineNotification
{
    public function __construct(public MaintenanceIssue $issue) {}

    public function toEngine(User $notifiable): NotificationPayload
    {
        return new NotificationPayload(
            title: "URGENT Hazard Reported: {$this->issue->reference_number}",
            body: "Emergency issue '{$this->issue->title}' reported at {$this->issue->location}.",
            user: $notifiable,
            actionUrl: '/dashboard/maintenance',
            metadata: ['category' => 'maintenance', 'priority' => 'urgent', 'issue_id' => $this->issue->id],
        );
    }

    public function subject(): MaintenanceIssue
    {
        return $this->issue;
    }
}
