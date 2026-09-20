<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\CommunityEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Backs src/app/dashboard/calendar/page.tsx and event-form.tsx. */
class CalendarController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Dashboard/Calendar', [
            'events' => CommunityEvent::orderBy('start_date')
                ->get()
                ->map(fn (CommunityEvent $e) => [
                    'id' => $e->id,
                    'title' => $e->title,
                    'description' => $e->description,
                    'startDate' => $e->start_date->toIso8601String(),
                    'endDate' => $e->end_date?->toIso8601String(),
                    'imageUrl' => $e->image_url,
                ]),
            'canManage' => $request->user()->can('broadcastNotices'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('broadcastNotices');

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'description' => ['required', 'string', 'max:5000'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'image_url' => ['nullable', 'url', 'max:2048'],
        ]);

        CommunityEvent::create([...$validated, 'created_by' => $request->user()->id]);

        return back()->with('success', 'Event created.');
    }

    public function update(Request $request, CommunityEvent $communityEvent): RedirectResponse
    {
        $this->authorize('broadcastNotices');

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:160'],
            'description' => ['sometimes', 'string', 'max:5000'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'image_url' => ['nullable', 'url', 'max:2048'],
        ]);

        $communityEvent->update($validated);

        return back()->with('success', 'Event updated.');
    }

    public function destroy(CommunityEvent $communityEvent): RedirectResponse
    {
        $this->authorize('broadcastNotices');

        $communityEvent->delete();

        return back()->with('success', 'Event deleted.');
    }
}
