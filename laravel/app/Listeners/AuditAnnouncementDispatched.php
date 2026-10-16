<?php

namespace App\Listeners;

use App\Events\Communications\AnnouncementDispatched;

class AuditAnnouncementDispatched
{
    public function handle(AnnouncementDispatched $event): void
    {
        $announcement = $event->announcement;
        $author = $announcement->author;

        if ($author) {
            $author->recordActivity(sprintf(
                "Broadcast dispatched: '%s' to %s (%d In-App, %d Email, %d SMS, %d WhatsApp)",
                $announcement->title,
                $announcement->target_type,
                $event->stats['in_app'] ?? 0,
                $event->stats['email'] ?? 0,
                $event->stats['sms'] ?? 0,
                $event->stats['whatsapp'] ?? 0
            ));
        }
    }
}
