<?php

namespace App\Services\Communications;

use App\Enums\UserRole;
use App\Models\CommunityEvent;
use App\Models\Renter;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Support\Collection;

/**
 * The props of the community calendar (Dashboard/Calendar): estate events,
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
                'startDate' => $e->start_date->toIso8601String(),
                'endDate' => $e->end_date?->toIso8601String(),
                'imageUrl' => $e->image_url,
            ]),
            'entrySlots' => config('visitor_pass.entry_slots', []),
            'myVisitors' => $this->recentVisitors($user),
            'userStay' => $this->stay($user),
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

    /**
     * A temporary homeowner's current stay, else their latest, for the lease
     * markers; null for everyone else.
     *
     * @return array{stayType: string, leaseStart: string, leaseEnd: string, expired: bool}|null
     */
    private function stay(User $user): ?array
    {
        if ($user->role !== UserRole::TemporaryHomeowner) {
            return null;
        }

        $stay = $user->activeStay() ?? Renter::where('user_id', $user->id)->first();

        return $stay ? [
            'stayType' => $stay->stay_type ?? 'Long-term (Renter)',
            'leaseStart' => $stay->lease_start->toDateString(),
            'leaseEnd' => $stay->lease_end->toDateString(),
            'expired' => $stay->leaseHasExpired(),
        ] : null;
    }
}
