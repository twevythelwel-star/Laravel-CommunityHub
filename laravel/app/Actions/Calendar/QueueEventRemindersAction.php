<?php

namespace App\Actions\Calendar;

use App\Models\CommunityEvent;
use App\Models\User;
use App\Notifications\Calendar\CommunityEventReminder;
use Illuminate\Database\Eloquent\Builder;

/**
 * Queues automated reminders for upcoming community events and calendar entries
 * taking place within the next 24 hours that have not yet been reminded.
 */
class QueueEventRemindersAction
{
    /** @return int how many events were reminded */
    public function execute(): int
    {
        $eventsReminded = 0;

        $this->due()->each(function (CommunityEvent $event) use (&$eventsReminded) {
            User::whereIn('role', ['Homeowner', 'Renter'])->chunk(100, function ($residents) use ($event) {
                foreach ($residents as $resident) {
                    $resident->notify(new CommunityEventReminder($event));
                }
            });

            $event->update(['reminded_at' => now()]);
            $eventsReminded++;
        });

        return $eventsReminded;
    }

    /** @return Builder<CommunityEvent> */
    public function due(): Builder
    {
        return CommunityEvent::query()->dueForReminder();
    }
}
