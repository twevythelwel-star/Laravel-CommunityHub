<?php

namespace App\Notifications\Calendar;

use App\Models\CommunityEvent;
use App\Models\User;
use App\Notifications\EngineNotification;
use App\Services\NotificationEngine\NotificationPayload;

/** Reminds residents of an upcoming community event or calendar item. */
class CommunityEventReminder extends EngineNotification
{
    public function __construct(public CommunityEvent $event) {}

    public function toEngine(User $notifiable): NotificationPayload
    {
        $typeLabel = $this->event->typeLabel();
        $schedule = $this->event->formattedSchedule();
        $location = $this->event->location ? " at {$this->event->location}" : '';

        return new NotificationPayload(
            title: "Upcoming {$typeLabel}: {$this->event->title}",
            body: "Reminder: \"{$this->event->title}\" is scheduled for {$schedule}{$location}.",
            user: $notifiable,
            actionUrl: '/dashboard/calendar',
            metadata: [
                'category' => 'calendar_reminder',
                'event_id' => $this->event->id,
                'event_type' => $this->event->event_type,
            ],
        );
    }

    public function subject(): CommunityEvent
    {
        return $this->event;
    }
}
