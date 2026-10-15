<?php

namespace App\Http\Controllers;

use App\Actions\Visitors\RegisterEventGuestAction;
use App\Http\Requests\Events\CreateEventInviteRequest;
use App\Http\Requests\Events\SubmitEventRsvpRequest;
use App\Models\Community;
use App\Models\EventInvite;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class EventRsvpController extends Controller
{
    /**
     * Resident creates a shareable RSVP link for their event.
     */
    public function createInvite(CreateEventInviteRequest $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validated();

        $token = 'inv_'.Str::lower(Str::random(12));

        $invite = EventInvite::create([
            'host_id' => $user->id,
            'title' => $validated['title'],
            'token' => $token,
            'expected_at' => Carbon::parse($validated['expected_at']),
            'max_guests' => $validated['max_guests'] ?? 50,
            'notes' => $validated['notes'] ?? null,
            'is_active' => true,
        ]);

        return back()->with([
            'success' => "Event RSVP link generated: {$invite->title}",
            'rsvp_url' => $invite->rsvpUrl(),
        ]);
    }

    /**
     * Display public RSVP form for attendees.
     */
    public function show(string $token): View
    {
        $invite = EventInvite::with('host')->where('token', $token)->firstOrFail();
        $community = Community::first();

        return view('blade.event-rsvp', [
            'invite' => $invite,
            'community' => $community,
            'isClosed' => ! $invite->isOpen(),
        ]);
    }

    /**
     * Process attendee registration; see RegisterEventGuestAction.
     */
    public function submit(SubmitEventRsvpRequest $request, string $token, RegisterEventGuestAction $register): View
    {
        $invite = EventInvite::with('host')->where('token', $token)->firstOrFail();

        ['visitor' => $visitor, 'smsSent' => $smsSent] = $register->execute($invite, $request->validated());

        return view('blade.event-rsvp-success', [
            'visitor' => $visitor,
            'invite' => $invite,
            'community' => Community::first(),
            'smsSent' => $smsSent,
        ]);
    }
}
