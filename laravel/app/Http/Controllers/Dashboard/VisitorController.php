<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\Visitors\CheckInVisitorAction;
use App\Actions\Visitors\ExtendVisitorPassAction;
use App\Actions\Visitors\RegisterBulkVisitorsAction;
use App\Actions\Visitors\RegisterVisitorAction;
use App\Actions\Visitors\UpdateVisitorAction;
use App\Enums\GateId;
use App\Enums\PassCategory;
use App\Enums\PassStatus;
use App\Enums\UserRole;
use App\Enums\VisitorStatus;
use App\Events\VisitorCheckedInEvent;
use App\Exceptions\ScanNotConfirmable;
use App\Http\Controllers\Controller;
use App\Http\Requests\Visitors\CheckInVisitorRequest;
use App\Http\Requests\Visitors\CheckOutVisitorRequest;
use App\Http\Requests\Visitors\ExtendVisitorPassRequest;
use App\Http\Requests\Visitors\RegisterVisitorRequest;
use App\Http\Requests\Visitors\ResendVisitorPassRequest;
use App\Http\Requests\Visitors\StoreBulkVisitorsRequest;
use App\Http\Requests\Visitors\UpdateVisitorRequest;
use App\Models\AccessLogEntry;
use App\Models\BlocklistEntry;
use App\Models\GatePass;
use App\Models\Renter;
use App\Models\User;
use App\Models\Visitor;
use App\Services\GatePassEngine;
use App\Services\GateScanner;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Backs src/app/dashboard/visitors/page.tsx.
 *
 * The blocklist check now runs on the server at registration and again at
 * check-in. Previously `isBlocked` was a field on the mock record that nothing
 * ever computed, so a blocked name could be registered and admitted freely.
 */
class VisitorController extends Controller
{
    public function __construct(
        private readonly GatePassEngine $engine,
        private readonly GateScanner $scanner,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $isStaff = $user->can('manageSecurity');
        $tab = $request->string('tab')->toString() ?: 'all';

        // A host sees their own visitors and those of properties they manage.
        $manageableProperties = $user->propertiesForVisitorManagement();
        $scopeForResidentOrRep = fn ($q) => $q->manageableBy($user, $manageableProperties->pluck('id'));

        $countsQuery = Visitor::query()->when(! $isStaff, $scopeForResidentOrRep);

        $todayQuery = (clone $countsQuery)->where(function ($q) {
            $q->whereDate('expected_at', today())
                ->orWhereDate('checked_in_at', today())
                ->orWhereDate('checked_out_at', today());
        });
        $visitorsTodayCount = (clone $todayQuery)->count();

        $expectedCount = (clone $countsQuery)
            ->where('status', VisitorStatus::Expected->value)
            ->whereNull('expired_at')
            ->count();

        $insideCount = (clone $countsQuery)
            ->where('status', VisitorStatus::CheckedIn->value)
            ->count();

        $checkedOutCount = (clone $countsQuery)
            ->where('status', VisitorStatus::CheckedOut->value)
            ->count();

        $rejectedCount = (clone $countsQuery)
            ->where(function ($q) {
                $q->where('is_blocked', true)->orWhereNotNull('expired_at');
            })
            ->count() + ($isStaff ? AccessLogEntry::where('result', 'DENY')->count() : 0);

        $suspiciousAttemptsCount = $isStaff ? AccessLogEntry::where('result', 'DENY')->where(function ($q) {
            $q->where('deny_reason', 'like', '%Replay%')
                ->orWhere('deny_reason', 'like', '%Signature%')
                ->orWhere('deny_reason', 'like', '%Forged%')
                ->orWhere('deny_reason', 'like', '%Blocklist%')
                ->orWhere('deny_reason', 'like', '%Revoked%')
                ->orWhere('deny_reason', 'like', '%NotAGpeGatePass%');
        })->count() : 0;

        $historyCount = (clone $countsQuery)->count();

        $securityStats = [
            'visitorsToday' => $visitorsTodayCount,
            'expected' => $expectedCount,
            'currentlyInside' => $insideCount,
            'checkedOut' => $checkedOutCount,
            'rejected' => $rejectedCount,
            'suspiciousAttempts' => $suspiciousAttemptsCount,
        ];

        $tabCounts = [
            'scan' => 0,
            'expected' => $expectedCount,
            'inside' => $insideCount,
            'checkedOut' => $checkedOutCount,
            'rejected' => $rejectedCount,
            'history' => $historyCount,
        ];

        // Query visitors based on active sub-navigation tab
        $query = Visitor::query()
            ->with(['homeowner:id,name,display_name,lot,street', 'gatePass'])
            ->when(! $isStaff, $scopeForResidentOrRep)
            ->when($request->string('search')->isNotEmpty(),
                fn ($q) => $q->where('name', 'like', '%'.$request->string('search').'%'));

        switch ($tab) {
            case 'expected':
                $query->where('status', VisitorStatus::Expected->value)->whereNull('expired_at');
                break;
            case 'inside':
                $query->where('status', VisitorStatus::CheckedIn->value);
                break;
            case 'checked-out':
                $query->where('status', VisitorStatus::CheckedOut->value);
                break;
            case 'rejected':
                $query->where(function ($q) {
                    $q->where('is_blocked', true)->orWhereNotNull('expired_at');
                });
                break;
            case 'history':
                // All records unconstrained
                break;
            default:
                // 'all' or 'scan'
                if ($isStaff && $request->boolean('today', true)) {
                    $query->whereDate('expected_at', today());
                }
                if ($request->string('status')->isNotEmpty()) {
                    $query->where('status', $request->string('status'));
                }
                break;
        }

        $visitors = $query
            ->latest('expected_at')
            ->paginate(25)
            ->withQueryString()
            ->through(function (Visitor $v) use ($request) {
                $hostLot = null;
                if ($v->homeowner && filled($v->homeowner->lot)) {
                    $hostLot = '#'.ltrim($v->homeowner->lot, '#');
                } elseif (preg_match('/Lot\s*(\d+)/i', $v->homeowner_name ?? '', $matches)) {
                    $hostLot = '#'.$matches[1];
                } else {
                    $hostLot = $v->homeowner_name ?: '#Unassigned';
                }

                // Relevant timestamp based on state: Checked in time, checked out time, or expected time
                $timeDisplay = match ($v->status) {
                    VisitorStatus::CheckedIn => $v->checked_in_at ? $v->checked_in_at->format('g:i A') : $v->expected_at->format('g:i A'),
                    VisitorStatus::CheckedOut => $v->checked_out_at ? $v->checked_out_at->format('g:i A') : $v->expected_at->format('g:i A'),
                    default => $v->expected_at->format('g:i A'),
                };

                return [
                    'id' => $v->id,
                    'name' => $v->name,
                    'contact' => $v->contact,
                    'vehicle' => $v->vehicle,
                    'idType' => $v->id_type,
                    'type' => $v->type,
                    'status' => $v->status->value,
                    'expectedAt' => $v->expected_at->toIso8601String(),
                    'dateRange' => $v->date_range,
                    'homeowner' => $v->homeowner_name,
                    'hostLot' => $hostLot,
                    'timeDisplay' => $timeDisplay,
                    'idImageUrl' => $v->id_image_url,
                    'isBlocked' => $v->is_blocked,
                    'expired' => $v->expired_at !== null,
                    'checkedInAt' => $v->checked_in_at?->toIso8601String(),
                    'checkedOutAt' => $v->checked_out_at?->toIso8601String(),
                    'shareToken' => $v->share_token,
                    'guestPassUrl' => $v->share_token ? route('guest-pass.show', $v->share_token) : null,
                    'arrivalWindow' => $v->arrivalWindowLabel(),
                    'arrivalWindowStart' => $v->arrival_window_start,
                    'arrivalWindowEnd' => $v->arrival_window_end,
                    'parkingInstructions' => $v->parkingInstructions(),
                    'communityRules' => $v->communityRules(),
                    'emergencyInfo' => $v->emergencyInfo(),
                    'notes' => $v->notes,
                    'notify_email' => $v->notify_email,
                    'notify_sms' => $v->notify_sms,
                    'notify_whatsapp' => $v->notify_whatsapp,
                    'pass' => $v->gatePass ? $this->passPayload($v->gatePass, $request->user()) : null,
                ];
            });

        $userStay = null;
        if ($user->role === UserRole::TemporaryHomeowner) {
            $stay = $user->activeStay() ?? Renter::where('user_id', $user->id)->first();
            if ($stay) {
                $userStay = [
                    'stayType' => $stay->stay_type ?? 'Long-term (Renter)',
                    'leaseStart' => $stay->lease_start->toDateString(),
                    'leaseEnd' => $stay->lease_end->toDateString(),
                    'expired' => $stay->leaseHasExpired(),
                ];
            }
        }

        return Inertia::render('Dashboard/Visitors', [
            'visitors' => $visitors,
            'filters' => [
                'status' => $request->string('status')->toString(),
                'search' => $request->string('search')->toString(),
                'tab' => $tab,
            ],
            'tabCounts' => $tabCounts,
            'securityStats' => $securityStats,
            'activeTab' => $tab,
            'canManage' => $isStaff,
            'canRegister' => $user->can('registerVisitors'),
            'userStay' => $userStay,
            'graceHours' => 12,
            // An owner or authorized representative says which property a visitor is coming to.
            'hostProperties' => $manageableProperties->map(fn ($p) => ['id' => $p->id, 'label' => $p->hostLabel()])->values(),
        ]);
    }

    public function store(RegisterVisitorRequest $request, RegisterVisitorAction $action): RedirectResponse|JsonResponse
    {
        $result = $action->execute($request->validated(), $request->user());

        return $this->visitorRegistered($request, $result['visitor'], $result['pass'], $result['message']);
    }

    /** The page's flash, or JSON for a client that asked for it. */
    private function visitorRegistered(Request $request, Visitor $visitor, GatePass $pass, string $successMsg): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $successMsg,
                'visitor' => [
                    'id' => $visitor->id,
                    'name' => $visitor->name,
                    'status' => $visitor->status,
                    'expectedAt' => $visitor->expected_at?->toIso8601String(),
                    'passId' => $pass->pass_id,
                    'passStatus' => $pass->status->value,
                ],
                'guestPassUrl' => $visitor->guestPassUrl(),
                'shareToken' => $visitor->share_token,
            ], 201);
        }

        return back()
            ->with('success', $successMsg)
            ->with('guest_pass_url', $visitor->guestPassUrl())
            ->with('share_token', $visitor->share_token);
    }

    /**
     * Bulk register attendees for private gatherings, events, or contractors.
     */
    public function storeBulk(StoreBulkVisitorsRequest $request, RegisterBulkVisitorsAction $action): RedirectResponse
    {
        $result = $action->execute($request->validated(), $request->user());

        return back()->with('success', $result['message']);
    }

    public function update(UpdateVisitorRequest $request, Visitor $visitor, UpdateVisitorAction $action): RedirectResponse
    {
        $action->execute($visitor, $request->validated(), $request->user());

        return back()->with('success', 'Visitor updated.');
    }

    public function checkIn(CheckInVisitorRequest $request, Visitor $visitor, CheckInVisitorAction $action): RedirectResponse
    {
        try {
            $action->execute($visitor, $request->user(), $this->gateFrom($request), $request->string('gate')->toString() ?: 'Main Gate');
        } catch (ScanNotConfirmable $e) {
            return back()->withErrors(['visitor' => "Not checked in: {$e->getMessage()}"]);
        }

        return back()->with('success', "{$visitor->name} checked in.");
    }

    public function checkOut(CheckOutVisitorRequest $request, Visitor $visitor): RedirectResponse
    {
        if ($visitor->gatePass) {
            try {
                $this->scanner->manualCheckOut($visitor->gatePass, $request->user(), $this->gateFrom($request));
            } catch (ScanNotConfirmable $e) {
                return back()->withErrors(['visitor' => "Not checked out: {$e->getMessage()}"]);
            }

            return back()->with('success', "{$visitor->name} checked out.");
        }

        $visitor->checkOut();

        return back()->with('success', "{$visitor->name} checked out.");
    }

    public function destroy(Request $request, Visitor $visitor): RedirectResponse
    {
        $this->authorize('delete', $visitor);

        $user = $request->user();
        if ($user->role === UserRole::TemporaryHomeowner) {
            $stay = $user->activeStay() ?? Renter::where('user_id', $user->id)->first();
            if (! $stay || $stay->leaseHasExpired()) {
                return back()->withErrors([
                    'visitor' => 'Your temporary stay has expired. You cannot modify visitor entries.',
                ]);
            }
        }

        $visitor->delete();

        return back()->with('success', 'Visitor removed.');
    }

    /**
     * Dispatch or re-send guest pass via SMS, WhatsApp, or Email.
     */
    public function resendPass(ResendVisitorPassRequest $request, Visitor $visitor): RedirectResponse
    {
        $channel = $request->validated('channel');
        $channels = $channel === 'all'
            ? $visitor->passNotificationChannels()
            : [$channel];

        $visitor->resendPassNotification(empty($channels) ? ['sms'] : $channels);

        return back()->with('success', 'Guest pass dispatched via '.strtoupper($channel).'.');
    }

    /**
     * Extend a visitor pass validity window.
     */
    public function extendPass(ExtendVisitorPassRequest $request, Visitor $visitor, ExtendVisitorPassAction $action): RedirectResponse
    {
        $hours = (int) $request->validated('hours');
        $action->execute($visitor, $hours);

        return back()->with('success', "Visitor pass validity successfully extended by {$hours} hours.");
    }

    private function isOnBlocklist(string $name): bool
    {
        return BlocklistEntry::blocks($name);
    }

    /** The page sends a gate id or a gate name; anything unknown is the main gate. */
    private function gateFrom(Request $request): GateId
    {
        $gate = $request->string('gate')->toString();

        return GateId::tryFrom($gate)
            ?? GateId::tryFrom((string) array_search($gate, config('gatepass.gates'), true))
            ?? GateId::Gate01;
    }

    /**
     * The pass as the Visitors page shows it, with the lifecycle moves this
     * viewer may make. Check-in and check-out are not offered here; they go
     * through the scanner or the check-in buttons.
     *
     * @return array<string, mixed>
     */
    private function passPayload(GatePass $pass, User $viewer): array
    {
        $isSecurity = $viewer->can('manageSecurity');
        $manual = [PassStatus::Approved, PassStatus::Rejected, PassStatus::Suspended, PassStatus::Active, PassStatus::Cancelled, PassStatus::Revoked];

        $actions = array_values(array_filter($manual, fn (PassStatus $to) => $pass->canTransitionTo($to)
            && ($isSecurity || ($to === PassStatus::Cancelled && ($pass->visitor?->homeowner_id === $viewer->id || ($pass->visitor?->property_id && $viewer->canManageVisitorsForProperty($pass->visitor->property_id)))))));

        return [
            'id' => $pass->id,
            'passId' => $pass->pass_id,
            'category' => $pass->category->value,
            'status' => $pass->status->value,
            'statusLabel' => $pass->status->label(),
            'validFrom' => $pass->valid_from?->toIso8601String(),
            'validUntil' => $pass->valid_until?->toIso8601String(),
            'singleEntry' => $pass->single_entry,
            'color' => $this->engine->variantFor($pass),
            'actions' => array_map(fn (PassStatus $s) => ['status' => $s->value, 'label' => match ($s) {
                PassStatus::Approved => 'Approve',
                PassStatus::Rejected => 'Reject',
                PassStatus::Suspended => 'Suspend',
                PassStatus::Active => 'Reinstate',
                PassStatus::Cancelled => 'Cancel pass',
                PassStatus::Revoked => 'Revoke',
                default => $s->label(),
            }], $actions),
        ];
    }
}
