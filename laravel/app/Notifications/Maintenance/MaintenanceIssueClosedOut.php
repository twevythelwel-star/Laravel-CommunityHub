<?php

namespace App\Notifications\Maintenance;

use App\Models\MaintenanceIssue;
use App\Models\User;
use App\Notifications\EngineNotification;
use App\Services\NotificationEngine\NotificationPayload;

/** Tells the resident who reported an issue that it has been resolved or closed. */
class MaintenanceIssueClosedOut extends EngineNotification
{
    public function __construct(
        public MaintenanceIssue $issue,
        public string $status,
        public ?string $resolutionNotes = null,
    ) {}

    public function toEngine(User $notifiable): NotificationPayload
    {
        $message = $this->status === 'resolved'
            ? "Your maintenance request '{$this->issue->title}' has been marked as resolved."
            : "Your maintenance request '{$this->issue->title}' has been closed.";

        if ($this->resolutionNotes) {
            $message .= " Resolution details: {$this->resolutionNotes}";
        }

        return new NotificationPayload(
            title: 'Maintenance '.ucfirst($this->status).": {$this->issue->reference_number}",
            body: $message,
            user: $notifiable,
            actionUrl: '/dashboard/maintenance',
            metadata: [
                'category' => 'maintenance',
                'issue_id' => $this->issue->id,
                'reference_number' => $this->issue->reference_number,
                'status' => $this->status,
                'resolution_notes' => $this->resolutionNotes,
            ],
        );
    }

    public function subject(): MaintenanceIssue
    {
        return $this->issue;
    }
}
