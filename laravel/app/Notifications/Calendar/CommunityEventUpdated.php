<?php

namespace App\Notifications\Calendar;

use App\Models\CommunityEvent;
use App\Models\User;
use App\Notifications\EngineNotification;
use App\Services\NotificationEngine\NotificationPayload;

/** Notifies residents when an event's date, time, location, or arrangements have changed. */
class CommunityEventUpdated extends EngineNotification
{
    public function __construct(
        public CommunityEvent $event,
        public ?string $changeNote = null
    ) {}

    public function toEngine(User $notifiable): NotificationPayload
    {
        $typeLabel = $this->event->typeLabel();
        $schedule = $this->event->formattedSchedule();
        $location = $this->event->location ? " Location: {$this->event->location}." : '';
        $note = $this->changeNote ? " Note: {$this->changeNote}" : '';

        return new NotificationPayload(
            title: "Schedule Update: {$this->event->title}",
            body: "Arrangements for {$typeLabel} \"{$this->event->title}\" have changed. New schedule: {$schedule}.{$location}{$note}",
            user: $notifiable,
            actionUrl: '/dashboard/calendar',
            metadata: [
                'category' => 'calendar_update',
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
