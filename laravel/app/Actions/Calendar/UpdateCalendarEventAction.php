<?php

namespace App\Actions\Calendar;

use App\Models\Community;
use App\Models\CommunityEvent;
use App\Models\User;
use App\Notifications\Calendar\CommunityEventUpdated;
use App\Services\Communications\AudienceResolutionService;
use Illuminate\Support\Facades\Notification;

/**
 * Updates a community calendar event. When its title, time or place changes, the whole
 * community is told (the same audience as a community-wide announcement), and a moved event
 * is reminded again before its new time.
 */
class UpdateCalendarEventAction
{
    /** Changes residents need to hear about. */
    private const ARRANGEMENTS = ['title', 'start_date', 'end_date', 'location'];

    public function __construct(private readonly AudienceResolutionService $audience) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function execute(CommunityEvent $event, array $validated, User $actor): CommunityEvent
    {
        $event->update($validated);
        $arrangementsChanged = $event->wasChanged(self::ARRANGEMENTS);

        if ($event->wasChanged('start_date')) {
            $event->update(['reminded_at' => null]);
        }

        if ($arrangementsChanged) {
            $this->notifyCommunity($event, $validated['change_summary'] ?? null);
        }

        $actor->recordActivity("Updated calendar event: {$event->title}");

        return $event;
    }

    private function notifyCommunity(CommunityEvent $event, ?string $changeNote): void
    {
        $this->audience->resolveQuery(Community::default(), 'entire_community')
            ->chunkById(200, fn ($people) => Notification::send($people, new CommunityEventUpdated($event, $changeNote)));
    }
}
