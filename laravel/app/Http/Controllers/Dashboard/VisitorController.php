<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\VisitorStatus;
use App\Http\Controllers\Controller;
use App\Models\AccessLogEntry;
use App\Models\BlocklistEntry;
use App\Models\Visitor;
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

        $visitors = Visitor::query()
            ->when(! $isStaff, fn ($q) => $q->where('homeowner_id', $user->id))
            ->when($request->string('status')->isNotEmpty(),
                fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->string('search')->isNotEmpty(),
                fn ($q) => $q->where('name', 'like', '%'.$request->string('search').'%'))
            // Security works today's gate queue; residents want their newest first.
            ->when($isStaff && $request->boolean('today', true),
                fn ($q) => $q->whereDate('expected_at', today()))
            ->latest('expected_at')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Visitor $v) => [
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
                'idImageUrl' => $v->id_image_url,
                'isBlocked' => $v->is_blocked,
                'expired' => $v->expired_at !== null,
                'shareToken' => $v->share_token,
                'guestPassUrl' => $v->share_token ? route('guest-pass.show', $v->share_token) : null,
            ]);

        return Inertia::render('Dashboard/Visitors', [
            'visitors' => $visitors,
            'filters' => $request->only('status', 'search'),
            'canManage' => $isStaff,
            'canRegister' => $user->can('registerVisitors'),
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
        ]);

        // Server-side blocklist enforcement.
        $blocked = $this->isOnBlocklist($validated['name']);

        if ($blocked) {
            return back()->withErrors([
                'name' => 'This person is on the community blocklist and cannot be registered. Contact security.',
            ]);
        }

        $user = $request->user();

        Visitor::create([
            ...$validated,
            'status' => 'Expected',
            'homeowner_id' => $user->id,
            'homeowner_name' => $user->display_name,
            'is_blocked' => false,
        ]);

        $user->recordActivity("Registered visitor {$validated['name']}");

        return back()->with('success', 'Visitor registered.');
    }

    public function update(Request $request, Visitor $visitor): RedirectResponse
    {
        $this->authorizeVisitor($request, $visitor);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'type' => ['sometimes', 'in:One-time,Recurring'],
            'expected_at' => ['sometimes', 'date'],
            'date_range' => ['nullable', 'string', 'max:120'],
        ]);

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
        event(new \App\Events\VisitorCheckedInEvent($visitor, $gate));

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
