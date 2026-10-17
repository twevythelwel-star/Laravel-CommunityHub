<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\Calendar\UpdateCalendarEventAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Calendar\StoreCalendarEventRequest;
use App\Http\Requests\Calendar\UpdateCalendarEventRequest;
use App\Models\CommunityEvent;
use App\Services\Communications\CalendarPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Backs src/app/dashboard/calendar/page.tsx and event-form.tsx. */
class CalendarController extends Controller
{
    /** The community calendar; see CalendarPresenter. */
    public function index(Request $request, CalendarPresenter $presenter): Response
    {
        return Inertia::render('Dashboard/Calendar', $presenter->props($request->user()));
    }

    public function store(StoreCalendarEventRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        CommunityEvent::create([...$validated, 'created_by' => $request->user()->id]);

        return back()->with('success', 'Event created.');
    }

    public function update(UpdateCalendarEventRequest $request, CommunityEvent $communityEvent, UpdateCalendarEventAction $update): RedirectResponse
    {
        $update->execute($communityEvent, $request->validated(), $request->user());

        return back()->with('success', 'Event updated.');
    }

    public function destroy(CommunityEvent $communityEvent): RedirectResponse
    {
        $this->authorize('broadcastNotices');

        $communityEvent->delete();

        return back()->with('success', 'Event deleted.');
    }
}
