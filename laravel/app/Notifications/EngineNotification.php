<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Channels\NotificationEngineChannel;
use App\Services\NotificationEngine\NotificationPayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;

/**
 * A queued notification to one person, delivered through the community NotificationEngine,
 * retried on failure and tracked in notification_deliveries. Features extend this rather
 * than writing in-app rows or calling providers themselves.
 */
abstract class EngineNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [15, 60, 300];

    /** What the recipient sees, with their contact details. */
    abstract public function toEngine(User $notifiable): NotificationPayload;

    /** What this notification is about, recorded with its deliveries. */
    abstract public function subject(): ?Model;

    /** @return list<string> */
    public function engineChannels(User $notifiable): array
    {
        return ['in_app'];
    }

    /** @return list<class-string> */
    public function via(User $notifiable): array
    {
        return [NotificationEngineChannel::class];
    }
}
