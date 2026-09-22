<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\UserRole;
use App\Enums\VisitorStatus;
use App\Events\VisitorCheckedInEvent;
use App\Http\Controllers\Controller;
use App\Models\AccessLogEntry;
use App\Models\BlocklistEntry;
use App\Models\Renter;
use App\Models\Visitor;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
    public function index(Request $request): Response
    {
        $user = $request->user();
        $isStaff = $user->can('manageSecurity');
        $tab = $request->string('tab')->toString() ?: 'all';

        // Calculate live counters for Security & Residents
        $countsQuery = Visitor::query()->when(! $isStaff, fn ($q) => $q->where('homeowner_id', $user->id));

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

        $historyCount = (clone $countsQuery)->count();

        $tabCounts = [
            'expected' => $expectedCount,
            'inside' => $insideCount,
            'checkedOut' => $checkedOutCount,
            'rejected' => $rejectedCount,
            'history' => $historyCount,
        ];

        // Query visitors based on active sub-navigation tab
        $query = Visitor::query()
            ->with('homeowner:id,name,display_name,lot,street')
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
            ->through(function (Visitor $v) {
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
            'activeTab' => $tab,
            'canManage' => $isStaff,
            'canRegister' => $user->can('registerVisitors'),
            'userStay' => $userStay,
            'graceHours' => 12,
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
        ]);

        $user = $request->user();

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

        // Send the pass on whichever chosen channels can actually deliver it.
        $channels = $visitor->passNotificationChannels();

        if ($channels !== []) {
            $visitor->sendPassNotification($channels);
        }

        return back()->with('success', 'Visitor registered.');
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
        return BlocklistEntry::inForce()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])
            ->exists();
    }
}
