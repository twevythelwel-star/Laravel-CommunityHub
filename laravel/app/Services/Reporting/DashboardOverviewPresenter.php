<?php

namespace App\Services\Reporting;

use App\Enums\UserRole;
use App\Enums\VisitorStatus;
use App\Models\AccessLogEntry;
use App\Models\Business;
use App\Models\Community;
use App\Models\CommunityEvent;
use App\Models\Fundraiser;
use App\Models\Invoice;
use App\Models\Landmark;
use App\Models\Notification;
use App\Models\User;
use App\Models\Visitor;
use App\Services\GeofenceService;
use App\Services\Occupancy\OccupancyMetricsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The props of the dashboard home (Dashboard/Overview): stat tiles,
 * announcements, events, today's visitor passes, recent visitors and
 * payments, the resident spotlight, fundraisers, perks and map landmarks.
 *
 * The original page read from module-level mock arrays and JSX literals,
 * identical for every user on every day. Everything here is a real
 * aggregate scoped to the viewer: residents see their own visitors and
 * invoices, security and administrators the estate.
 */
class DashboardOverviewPresenter
{
    public function __construct(
        private readonly GeofenceService $geofence,
        private readonly OccupancyMetricsService $occupancy,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function props(User $user): array
    {
        $isStaff = $user->can('manageSecurity');

        return [
            'stats' => $this->stats($user, $isStaff),
            'permissions' => $this->tilePermissions($user, $isStaff),
            'announcements' => $this->announcements($user),
            'upcomingEvents' => $this->upcomingEvents(),
            'visitorPasses' => $this->visitorPassesToday($user, $isStaff),
            'recentVisitors' => $this->recentVisitors($user, $isStaff),
            'recentPayments' => $this->recentPayments($user),
            'residents' => $this->residentSpotlight($user),
            'fundraisers' => $this->activeFundraisers(),
            'perks' => $this->perks(),
            'landmarks' => $this->landmarks(),
        ];
    }

    /** Visitors the viewer may see: their own, or the estate's for security and admins. */
    private function visitors(User $user, bool $isStaff): Builder
    {
        return Visitor::query()->when(! $isStaff, fn (Builder $q) => $q->where('homeowner_id', $user->id));
    }

    /**
     * Which tiles to show. Replaces five inline `['System Admin', 'Admin'].includes(role)`
     * checks in the JSX, which only hid tiles rather than the data.
     *
     * @return array<string, bool>
     */
    private function tilePermissions(User $user, bool $isStaff): array
    {
        return [
            'viewActiveResidents' => $isStaff,
            'viewUpcomingVisitors' => $user->role->isResident()
                || $user->role === UserRole::Security
                || $user->role->isAdministrative(),
            'viewRecentVisitors' => $isStaff || $user->role->isResident(),
            'viewBilling' => $user->can('manageBilling'),
            'viewFundraisers' => $user->role->isResident() || $user->role->isAdministrative(),
            'manageBoundary' => $user->can('manageBoundary'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function stats(User $user, bool $isStaff): array
    {
        $collectedThisMonth = Invoice::query()
            ->where('status', 'Paid')
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year);

        $outstanding = Invoice::outstanding();

        return [
            'month' => now()->format('F'),

            'activeResidents' => $isStaff ? User::active()->count() : null,
            // The tile showed "+12 from last month" as a literal.
            'residentsJoinedThisMonth' => $isStaff
                ? User::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count()
                : null,

            'totalCollected' => (float) ((int) $collectedThisMonth->sum('amount_minor') / 100),
            'collectedHouseholds' => (clone $collectedThisMonth)->distinct('user_id')->count('user_id'),

            'outstandingDues' => (float) ((int) $outstanding->sum('amount_minor') / 100),
            'outstandingHouseholds' => (clone $outstanding)->distinct('user_id')->count('user_id'),

            'upcomingVisitors' => $this->visitors($user, $isStaff)
                ->where('status', VisitorStatus::Expected->value)
                ->whereNull('expired_at')
                ->where('expected_at', '>=', now())
                ->count(),
            'visitorsToday' => $this->visitors($user, $isStaff)
                ->whereDate('expected_at', today())
                ->whereNull('expired_at')
                ->count(),
            'visitorsOnSite' => $this->visitors($user, $isStaff)->onSite()->count(),

            'entriesToday' => $isStaff ? AccessLogEntry::whereDate('occurred_at', today())->count() : null,
            'deniedToday' => $isStaff ? AccessLogEntry::denied()->whereDate('occurred_at', today())->count() : null,

            'myOutstandingBalance' => (float) ((int) $user->invoices()->outstanding()->sum('amount_minor') / 100),
            'myRentersCount' => $user->role === UserRole::Homeowner
                ? $user->renters()->where('status', 'Active')->count()
                : null,

            'occupancy' => $isStaff ? $this->occupancyStats() : null,
        ];
    }

    /**
     * "Currently inside" and "expected today", from the same occupancy
     * service as the Who's On Property page, so the two never disagree.
     *
     * @return array{currentlyInside: array<string, int>, expectedToday: array<string, int>}
     */
    private function occupancyStats(): array
    {
        $summary = $this->occupancy->getDualSummary();

        $card = fn (array $counts): array => [
            'total' => $counts['total'],
            'residents' => $counts['residents'],
            'renters' => $counts['long_term_guests'] + $counts['short_term_guests'],
            'visitors' => $counts['visitors'],
            'staff' => $counts['staff'],
            'contractors' => $counts['contractors'],
            'legacyContacts' => $counts['legacy_contacts'],
        ];

        return [
            'currentlyInside' => $card($summary['inside']),
            'expectedToday' => $card($summary['expected']),
        ];
    }

    private function announcements(User $user): Collection
    {
        return Notification::query()
            ->forRole($user->role->value)
            ->latest('published_at')
            ->limit(5)
            ->get()
            ->map(fn (Notification $n) => [
                'id' => $n->id,
                'title' => $n->title,
                'content' => $n->content,
                'author' => $n->author_name,
                'timestamp' => $n->published_at->toIso8601String(),
            ]);
    }

    private function upcomingEvents(): Collection
    {
        return CommunityEvent::upcoming()
            ->limit(4)
            ->get()
            ->map(fn (CommunityEvent $e) => [
                'id' => $e->id,
                'title' => $e->title,
                'summary' => str($e->description)->limit(90)->toString(),
                'startDate' => $e->start_date->toIso8601String(),
                'imageUrl' => $e->image_url,
            ]);
    }

    /** Today's clearances, for the "Visitor Gate Passes" strip (once three hardcoded cards). */
    private function visitorPassesToday(User $user, bool $isStaff): Collection
    {
        return $this->visitors($user, $isStaff)
            ->whereDate('expected_at', today())
            ->whereNull('expired_at')
            ->orderBy('expected_at')
            ->limit(6)
            ->get()
            ->map(fn (Visitor $v) => [
                'id' => $v->id,
                'name' => $v->name,
                'status' => $v->status->value,
                'expectedAt' => $v->expected_at->toIso8601String(),
                'host' => $v->homeowner_name,
                'isBlocked' => $v->is_blocked,
            ]);
    }

    private function recentVisitors(User $user, bool $isStaff): Collection
    {
        return $this->visitors($user, $isStaff)
            ->latest('expected_at')
            ->limit(8)
            ->get()
            ->map(fn (Visitor $v) => [
                'id' => $v->id,
                'name' => $v->name,
                'type' => $v->type,
                'status' => $v->status->value,
                'expectedAt' => $v->expected_at->toIso8601String(),
                'homeowner' => $v->homeowner_name,
                'isBlocked' => $v->is_blocked,
            ]);
    }

    /** Estate-wide payments for billing admins, otherwise the viewer's own. */
    private function recentPayments(User $user): Collection
    {
        $query = $user->can('manageBilling')
            ? Invoice::with('user:id,display_name,lot')
            : $user->invoices()->with('user:id,display_name,lot');

        return $query
            ->latest('period_start')
            ->limit(5)
            ->get()
            ->map(fn (Invoice $i) => [
                'id' => $i->id,
                'reference' => $i->reference,
                'homeowner' => trim(($i->user?->display_name ?? 'Resident').($i->user?->lot ? " ({$i->user->lot})" : '')),
                'amount' => $i->amount(),
                'currency' => $i->currency,
                'status' => $i->isOverdue() ? 'Overdue' : $i->status,
                'date' => $i->due_on->toDateString(),
                'paidAt' => $i->paid_at?->toIso8601String(),
            ]);
    }

    /**
     * Resident spotlight (once four hardcoded cards). The phone number is
     * only for those who may manage users, as in the directory; otherwise the
     * tile would publish every resident's number to every other resident.
     */
    private function residentSpotlight(User $viewer): Collection
    {
        return User::active()
            ->whereIn('role', [UserRole::Homeowner->value, UserRole::TemporaryHomeowner->value])
            ->orderBy('display_name')
            ->limit(6)
            ->get()
            ->map(fn (User $r) => [
                'id' => $r->id,
                'name' => $r->display_name,
                'initials' => $this->initials($r->display_name),
                'lot' => $r->lot,
                'role' => $r->role === UserRole::Homeowner ? 'Res' : 'Tenant',
                'avatarUrl' => $r->avatar_url,
                'phone' => $viewer->can('manageUsers') ? $r->phone : null,
            ]);
    }

    private function activeFundraisers(): Collection
    {
        return Fundraiser::with('donations')
            ->active()
            ->get()
            ->map(fn (Fundraiser $f) => [
                'id' => $f->id,
                'title' => $f->title,
                'description' => $f->description,
                'goal' => $f->goal(),
                'raised' => $f->raised(),
                'progress' => $f->progressPercent(),
                'donorCount' => $f->donorCount(),
                'currency' => $f->goal_currency,
                'startDate' => $f->start_date->toDateString(),
                'endDate' => $f->end_date->toDateString(),
                'status' => $f->status,
            ]);
    }

    private function perks(): Collection
    {
        return Business::with('vouchers')
            ->where('active', true)
            ->limit(6)
            ->get()
            ->map(fn (Business $b) => [
                'id' => $b->id,
                'name' => $b->name,
                'logoUrl' => $b->logo_url,
                'aiHint' => $b->ai_hint,
                'vouchers' => $b->vouchers->map(fn ($v) => [
                    'id' => $v->id,
                    'title' => $v->title,
                    'description' => $v->description,
                ])->values(),
            ]);
    }

    /**
     * Landmarks from the database rather than the COMMUNITY_LANDMARKS constant
     * the page used to import, so one an administrator adds is visible to
     * everybody. The boundary is shared globally by HandleInertiaRequests.
     *
     * @return Collection<int, array<string, mixed>>|array{}
     */
    private function landmarks(): Collection|array
    {
        $community = Community::query()->where('code', config('gatepass.default_community_id'))->first();

        return $community
            ? $community->landmarks->map(fn (Landmark $l) => [
                'id' => $l->id,
                'name' => $l->name,
                'category' => $l->category,
                'description' => $l->description,
                'coordinates' => [$l->lat, $l->lng],
                'elevation' => $this->geofence->estimateElevation($l->lat, $l->lng),
                'color' => $this->landmarkColor($l->category),
            ])
            : [];
    }

    /** Up to two initials from a display name, for the avatar fallback. */
    private function initials(string $name): string
    {
        return collect(preg_split('/\s+/', trim($name)))
            ->filter()
            ->take(2)
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }

    /** Pin colour per landmark category, matching LANDMARK_PIN_CONFIGS. */
    private function landmarkColor(?string $category): string
    {
        return match ($category) {
            'Security Gate' => '#10B981',
            'Community Center' => '#EF4444',
            'Park' => '#3B82F6',
            default => '#64748B',
        };
    }
}
