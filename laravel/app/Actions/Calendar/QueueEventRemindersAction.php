<?php

namespace App\Actions\Calendar;

use App\Models\Community;
use App\Models\CommunityEvent;
use App\Notifications\Calendar\CommunityEventReminder;
use App\Services\Communications\AudienceResolutionService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Reminds the community of each upcoming event once, as soon as it falls within the event's
 * own reminder window (remind_before_hours, 24 by default). Run hourly by the scheduler.
 */
class QueueEventRemindersAction
{
    public function __construct(private readonly AudienceResolutionService $audience) {}

    /** @return int how many events were reminded */
    public function execute(): int
    {
        $due = $this->due();

        foreach ($due as $event) {
            $this->audience->resolveQuery(Community::default(), 'entire_community')
                ->chunkById(200, fn ($people) => Notification::send($people, new CommunityEventReminder($event)));

            $event->update(['reminded_at' => now()]);
        }

        return $due->count();
    }

    /** @return Collection<int, CommunityEvent> */
    public function due(): Collection
    {
        return CommunityEvent::query()->dueForReminder()->get()
            ->filter(fn (CommunityEvent $event) => $event->isWithinReminderWindow())
            ->values();
    }
}
