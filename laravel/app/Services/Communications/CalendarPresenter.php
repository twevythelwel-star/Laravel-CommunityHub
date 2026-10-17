<?php

namespace App\Services\Communications;

use App\Models\CommunityEvent;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Support\Collection;

/**
 * The props of the community calendar (Dashboard/Calendar): estate events and items
 * (public holidays, office closures, meetings, maintenance windows, community events),
 * the entry windows from config/visitor_pass.php, the viewer's own visitors
 * from the past week on, and a temporary homeowner's lease dates.
 */
class CalendarPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function props(User $user): array
    {
        return [
            'events' => CommunityEvent::orderBy('start_date')->get()->map(fn (CommunityEvent $e) => [
                'id' => $e->id,
                'title' => $e->title,
                'description' => $e->description,
                'eventType' => $e->event_type ?? CommunityEvent::TYPE_EVENT,
                'eventTypeLabel' => $e->typeLabel(),
                'location' => $e->location,
                'isAllDay' => (bool) $e->is_all_day,
                'startDate' => $e->start_date->toIso8601String(),
                'endDate' => $e->end_date?->toIso8601String(),
                'imageUrl' => $e->image_url,
                'changeSummary' => $e->change_summary,
            ]),
            'eventTypes' => CommunityEvent::TYPES,
            'entrySlots' => config('visitor_pass.entry_slots', []),
            'myVisitors' => $this->recentVisitors($user),
            'userStay' => $user->staySummary(),
            'canManage' => $user->can('broadcastNotices'),
            'canRegisterVisitors' => $user->can('registerVisitors'),
        ];
    }

    private function recentVisitors(User $user): Collection
    {
        return Visitor::where('homeowner_id', $user->id)
            ->whereDate('expected_at', '>=', now()->subDays(7))
            ->orderBy('expected_at')
            ->get()
            ->map(fn (Visitor $v) => [
                'id' => $v->id,
                'name' => $v->name,
                'type' => $v->type,
                'status' => $v->status->value,
                'expectedAt' => $v->expected_at->toIso8601String(),
                'dateRange' => $v->date_range,
            ]);
    }
}
