<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\GateId;
use App\Enums\PassCategory;
use App\Enums\PassStatus;
use App\Enums\UserRole;
use App\Enums\VisitorStatus;
use App\Events\VisitorCheckedInEvent;
use App\Exceptions\ScanNotConfirmable;
use App\Http\Controllers\Controller;
use App\Models\AccessLogEntry;
use App\Models\BlocklistEntry;
use App\Models\GatePass;
use App\Models\Renter;
use App\Models\User;
use App\Models\Visitor;
use App\Services\GatePassEngine;
use App\Services\GateScanner;
use Carbon\Carbon;
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

        // Calculate live counters for Security Dashboard & Residents
        $countsQuery = Visitor::query()->when(! $isStaff, fn ($q) => $q->where('homeowner_id', $user->id));

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
            ->when(! $isStaff, fn ($q) => $q->where('homeowner_id', $user->id))
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
            // An owner of several properties says which one a visitor is coming to.
            'hostProperties' => $user->properties()->orderBy('id')->get()
                ->map(fn ($p) => ['id' => $p->id, 'label' => $p->label()])
                ->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('registerVisitors');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'contact' => ['nullable', 'string', 'max:120'],
            'vehicle' => ['nullable', 'string', 'max:120'],
            'id_type' => ['nullable', 'string', 'max:60'],
            'id_number' => ['nullable', 'string', 'max:60'],
            'type' => ['required', 'in:One-time,Recurring'],
            // A pre-clearance in the past is almost always a mistyped date, and
            // it would be swept straight away by visitors:expire-no-shows.
            'expected_at' => ['required', 'date', 'after:-1 hour'],
            'date_range' => ['nullable', 'string', 'max:120'],
            'id_image_url' => ['nullable', 'url', 'max:2048'],
            'notify_email' => ['nullable', 'boolean'],
            'notify_sms' => ['nullable', 'boolean'],
            'notify_whatsapp' => ['nullable', 'boolean'],
            // A contractor's pass waits for security to approve it.
            'pass_category' => ['nullable', 'in:VISITOR,CONTRACTOR'],
            // One of the host's own properties; no one else's address goes on the pass.
            'property_id' => ['nullable', Rule::exists('properties', 'id')->where('owner_user_id', $request->user()->id)],
        ], [
            'property_id.exists' => 'Choose one of your own properties.',
        ]);

        $user = $request->user();
        $passCategory = PassCategory::from($validated['pass_category'] ?? PassCategory::Visitor->value);
        unset($validated['pass_category']);

        // Server-side stay timeframe enforcement for Temporary Homeowners
        if ($user->role === UserRole::TemporaryHomeowner) {
            $stay = $user->activeStay() ?? Renter::where('user_id', $user->id)->first();
            if (! $stay || $stay->leaseHasExpired()) {
                return back()->withErrors([
                    'expected_at' => 'Your temporary stay has expired or is inactive. You cannot register visitors.',
                ]);
            }

            $expectedDate = Carbon::parse($validated['expected_at'])->startOfDay();
            $leaseStart = $stay->lease_start->startOfDay();
            $leaseEnd = $stay->lease_end->endOfDay();

            if ($expectedDate->lt($leaseStart) || $expectedDate->gt($leaseEnd)) {
                return back()->withErrors([
                    'expected_at' => "Visitors can only be registered within your approved stay timeframe ({$stay->lease_start->format('M d, Y')} to {$stay->lease_end->format('M d, Y')}).",
                ]);
            }
        }

        // Server-side blocklist enforcement.
        $blocked = $this->isOnBlocklist($validated['name']);

        if ($blocked) {
            return back()->withErrors([
                'name' => 'This person is on the community blocklist and cannot be registered. Contact security.',
            ]);
        }

        $visitor = Visitor::create([
            ...$validated,
            'status' => 'Expected',
            'homeowner_id' => $user->id,
            'homeowner_name' => $user->display_name,
            'is_blocked' => false,
            'notify_email' => $validated['notify_email'] ?? true,
            'notify_sms' => $validated['notify_sms'] ?? false,
            'notify_whatsapp' => $validated['notify_whatsapp'] ?? false,
        ]);

        $user->recordActivity("Registered visitor {$validated['name']}");

        $pass = $this->engine->issueGuestPass($visitor, $user, $passCategory);

        // Send the pass on whichever chosen channels can actually deliver it.
        // A contractor still awaiting approval is told once security approves.
        $channels = $visitor->passNotificationChannels();

        if ($channels !== [] && $pass->status !== PassStatus::Requested) {
            $visitor->sendPassNotification($channels);
        }

        return back()->with('success', $pass->status === PassStatus::Requested
            ? 'Contractor registered. Their pass is waiting for security approval.'
            : 'Visitor registered.');
    }

    /**
     * Bulk register attendees for private gatherings, events, or contractors.
     */
    public function storeBulk(Request $request): RedirectResponse
    {
        $this->authorize('registerVisitors');

        $validated = $request->validate([
            'expected_at' => ['required', 'date', 'after:-1 hour'],
            'pass_category' => ['nullable', 'in:VISITOR,CONTRACTOR'],
            'visitors' => ['required', 'array', 'min:1', 'max:50'],
            'visitors.*.name' => ['required', 'string', 'max:120'],
            'visitors.*.contact' => ['nullable', 'string', 'max:120'],
            'visitors.*.vehicle' => ['nullable', 'string', 'max:120'],
            'notify_email' => ['nullable', 'boolean'],
            'notify_sms' => ['nullable', 'boolean'],
            'notify_whatsapp' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();
        $passCategory = PassCategory::from($validated['pass_category'] ?? PassCategory::Visitor->value);
        $expectedAt = Carbon::parse($validated['expected_at']);

        // Check stay timeframe for temporary residents
        if ($user->role === UserRole::TemporaryHomeowner) {
            $stay = $user->activeStay() ?? Renter::where('user_id', $user->id)->first();
            if (! $stay || $stay->leaseHasExpired()) {
                return back()->withErrors([
                    'expected_at' => 'Your temporary stay has expired or is inactive. You cannot register visitors.',
                ]);
            }

            $stayStart = $stay->lease_start->startOfDay();
            $stayEnd = $stay->lease_end->endOfDay();
            if ($expectedAt->lt($stayStart) || $expectedAt->gt($stayEnd)) {
                return back()->withErrors([
                    'expected_at' => "Visitors can only be scheduled within your approved stay timeframe ({$stay->lease_start->format('M d, Y')} to {$stay->lease_end->format('M d, Y')}).",
                ]);
            }
        }

        $createdCount = 0;
        $blockedNames = [];

        DB::transaction(function () use ($validated, $user, $expectedAt, $passCategory, &$createdCount, &$blockedNames) {
            foreach ($validated['visitors'] as $item) {
                $name = trim($item['name']);
                if ($this->isOnBlocklist($name)) {
                    $blockedNames[] = $name;

                    continue;
                }

                $visitor = Visitor::create([
                    'homeowner_id' => $user->id,
                    'homeowner_name' => $user->display_name,
                    'name' => $name,
                    'contact' => $item['contact'] ?? null,
                    'vehicle' => $item['vehicle'] ?? null,
                    'type' => 'One-time',
                    'expected_at' => $expectedAt,
                    'status' => 'Expected',
                    'is_blocked' => false,
                    'notify_email' => $validated['notify_email'] ?? false,
                    'notify_sms' => $validated['notify_sms'] ?? false,
                    'notify_whatsapp' => $validated['notify_whatsapp'] ?? false,
                ]);

                $pass = $this->engine->issueGuestPass($visitor, $user, $passCategory);
                $createdCount++;

                $channels = $visitor->passNotificationChannels();
                if ($channels !== [] && $pass->status !== PassStatus::Requested) {
                    $visitor->sendPassNotification($channels);
                }
            }
        });

        $msg = "Registered {$createdCount} event attendee(s) with pre-cleared guest passes.";
        if (! empty($blockedNames)) {
            $msg .= ' Note: '.count($blockedNames).' attendee(s) could not be registered due to active community blocklist restrictions: '.implode(', ', $blockedNames);
        }

        return back()->with('success', $msg);
    }

    public function update(Request $request, Visitor $visitor): RedirectResponse
    {
        $this->authorizeVisitor($request, $visitor);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'contact' => ['nullable', 'string', 'max:120'],
            'vehicle' => ['nullable', 'string', 'max:120'],
            'type' => ['sometimes', 'in:One-time,Recurring'],
            'expected_at' => ['sometimes', 'date'],
            'date_range' => ['nullable', 'string', 'max:120'],
            'notify_email' => ['nullable', 'boolean'],
            'notify_sms' => ['nullable', 'boolean'],
            'notify_whatsapp' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();

        // Server-side blocklist check if name was changed
        if (isset($validated['name']) && $validated['name'] !== $visitor->name) {
            if ($this->isOnBlocklist($validated['name'])) {
                return back()->withErrors([
                    'name' => 'This person is on the community blocklist and cannot be registered. Contact security.',
                ]);
            }
        }

        // Temporary Homeowners can only edit visitors within their approved timeframe
        if ($user->role === UserRole::TemporaryHomeowner) {
            $stay = $user->activeStay() ?? Renter::where('user_id', $user->id)->first();
            if (! $stay || $stay->leaseHasExpired()) {
                return back()->withErrors([
                    'expected_at' => 'Your temporary stay has expired or is inactive. You cannot modify visitors.',
                ]);
            }

            if (isset($validated['expected_at'])) {
                $expectedDate = Carbon::parse($validated['expected_at'])->startOfDay();
                $leaseStart = $stay->lease_start->startOfDay();
                $leaseEnd = $stay->lease_end->endOfDay();

                if ($expectedDate->lt($leaseStart) || $expectedDate->gt($leaseEnd)) {
                    return back()->withErrors([
                        'expected_at' => "Visitors can only be scheduled within your approved stay timeframe ({$stay->lease_start->format('M d, Y')} to {$stay->lease_end->format('M d, Y')}).",
                    ]);
                }
            }
        }

        $visitor->update($validated);

        if ($visitor->gatePass) {
            $this->engine->syncGuestPass($visitor->gatePass, $visitor->fresh());
        }

        return back()->with('success', 'Visitor updated.');
    }

    public function checkIn(Request $request, Visitor $visitor): RedirectResponse
    {
        $this->authorize('manageSecurity');

        // A lapsed pre-clearance must be re-authorised by the resident. The page
        // disables the button, but a disabled button is not an access control.
        if ($visitor->expired_at !== null) {
            return back()->withErrors([
                'visitor' => 'This clearance expired after the no-show grace period. Ask the resident to re-register the visitor.',
            ]);
        }

        if ($visitor->status === VisitorStatus::CheckedIn) {
            return back()->withErrors(['visitor' => 'This visitor is already checked in.']);
        }

        // Re-check at the gate: someone may have been added to the blocklist
        // after they were registered.
        if ($this->isOnBlocklist($visitor->name)) {
            $visitor->update(['is_blocked' => true]);

            AccessLogEntry::create([
                'user_id' => $visitor->homeowner_id,
                'user_name' => $visitor->name,
                'user_role' => 'Visitor',
                'method' => 'Visitor Pass',
                'gate' => $request->string('gate')->toString() ?: 'Main Gate',
                'result' => 'DENY',
                'deny_reason' => 'BLOCKLIST: Visitor is on the community blocklist.',
                'scanned_by' => $request->user()->id,
                'occurred_at' => now(),
            ]);

            return back()->withErrors(['visitor' => 'Denied — this visitor is on the blocklist.']);
        }

        // A visitor with a pass is checked in through it, so the pass rules
        // (state, validity window) apply to the manual button too.
        if ($visitor->gatePass) {
            try {
                $this->scanner->manualCheckIn($visitor->gatePass, $request->user(), $this->gateFrom($request));
            } catch (ScanNotConfirmable $e) {
                return back()->withErrors(['visitor' => "Not checked in: {$e->getMessage()}"]);
            }

            return back()->with('success', "{$visitor->name} checked in.");
        }

        $visitor->checkIn();

        $gate = $request->string('gate')->toString() ?: 'Main Gate';
        event(new VisitorCheckedInEvent($visitor, $gate));

        AccessLogEntry::create([
            'user_id' => $visitor->homeowner_id,
            'user_name' => $visitor->name,
            'user_role' => 'Visitor',
            'method' => 'Visitor Pass',
            'gate' => $gate,
            'result' => 'ALLOW',
            'scanned_by' => $request->user()->id,
            'occurred_at' => now(),
        ]);

        return back()->with('success', "{$visitor->name} checked in.");
    }

    public function checkOut(Request $request, Visitor $visitor): RedirectResponse
    {
        $this->authorize('manageSecurity');

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
        $this->authorizeVisitor($request, $visitor);

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
    public function resendPass(Request $request, Visitor $visitor): RedirectResponse
    {
        $this->authorizeVisitor($request, $visitor);

        $validated = $request->validate([
            'channel' => ['required', 'string', 'in:sms,whatsapp,email,all'],
        ]);

        $channel = $validated['channel'];
        $channels = $channel === 'all'
            ? $visitor->passNotificationChannels()
            : [$channel];

        if (empty($channels)) {
            $channels = ['sms'];
        }

        $visitor->resendPassNotification($channels);

        return back()->with('success', 'Guest pass dispatched via '.strtoupper($channel).'.');
    }

    /**
     * Extend a visitor pass validity window.
     */
    public function extendPass(Request $request, Visitor $visitor): RedirectResponse
    {
        $this->authorizeVisitor($request, $visitor);

        $validated = $request->validate([
            'hours' => ['required', 'integer', 'min:1', 'max:72'],
        ]);

        $hours = (int) $validated['hours'];
        $base = $visitor->expected_at && $visitor->expected_at->isFuture()
            ? $visitor->expected_at
            : now();

        $newExpectedAt = $base->copy()->addHours($hours);

        $visitor->update([
            'expected_at' => $newExpectedAt,
            'expired_at' => null,
            'status' => $visitor->status === VisitorStatus::CheckedIn ? VisitorStatus::CheckedIn : VisitorStatus::Expected,
        ]);

        if ($visitor->gatePass) {
            $passBase = $visitor->gatePass->valid_until && $visitor->gatePass->valid_until->isFuture()
                ? $visitor->gatePass->valid_until
                : now();

            $visitor->gatePass->update([
                'valid_until' => $passBase->copy()->addHours($hours),
                'status' => $visitor->gatePass->status === PassStatus::Expired ? PassStatus::Active : $visitor->gatePass->status,
            ]);
        }

        return back()->with('success', "Visitor pass validity successfully extended by {$hours} hours.");
    }

    /** A resident may only touch their own visitors; security may touch any. */
    private function authorizeVisitor(Request $request, Visitor $visitor): void
    {
        $user = $request->user();

        if ($visitor->homeowner_id !== $user->id && ! $user->can('manageSecurity')) {
            abort(403, 'You can only manage visitors you registered.');
        }
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
            && ($isSecurity || ($to === PassStatus::Cancelled && $pass->visitor?->homeowner_id === $viewer->id))));

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
