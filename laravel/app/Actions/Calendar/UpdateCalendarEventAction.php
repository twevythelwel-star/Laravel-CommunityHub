<?php

namespace App\Actions\Calendar;

use App\Models\CommunityEvent;
use App\Models\User;
use App\Notifications\Calendar\CommunityEventUpdated;
use Carbon\Carbon;

/**
 * Updates a community calendar event and, if date, time, location or critical arrangements
 * changed, automatically notifies community residents of the updated schedule.
 */
class UpdateCalendarEventAction
{
    /**
     * @param  array<string, mixed>  $validated
     */
    public function execute(CommunityEvent $event, array $validated, User $actor): CommunityEvent
    {
        $oldStart = $event->start_date?->toIso8601String();
        $oldEnd = $event->end_date?->toIso8601String();
        $oldLocation = $event->location;
        $oldTitle = $event->title;

        $event->update($validated);

        $dateChanged = isset($validated['start_date']) && Carbon::parse($validated['start_date'])->toIso8601String() !== $oldStart;
        $endChanged = array_key_exists('end_date', $validated) && ($validated['end_date'] ? Carbon::parse($validated['end_date'])->toIso8601String() : null) !== $oldEnd;
        $locationChanged = array_key_exists('location', $validated) && $validated['location'] !== $oldLocation;
        $titleChanged = isset($validated['title']) && $validated['title'] !== $oldTitle;

        $arrangementsChanged = $dateChanged || $endChanged || $locationChanged || $titleChanged;

        if ($arrangementsChanged) {
            $changeNote = $validated['change_summary'] ?? null;

            // Notify all residents with community access
            User::whereIn('role', ['Homeowner', 'Renter'])->chunk(100, function ($residents) use ($event, $changeNote) {
                foreach ($residents as $resident) {
                    $resident->notify(new CommunityEventUpdated($event, $changeNote));
                }
            });
        }

        $actor->recordActivity("Updated calendar event: {$event->title}");

        return $event;
    }
}
