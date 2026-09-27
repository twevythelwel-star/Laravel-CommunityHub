<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ResidentMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A user's personal inbox. Every query goes through the signed-in user's own
 * messages, so another person's message is a 404, not a 403: its existence is
 * not revealed.
 */
class InboxController extends Controller
{
    /** Enough history to be useful without an unbounded page. */
    private const SHOWN = 100;

    public function index(Request $request): Response
    {
        return Inertia::render('Dashboard/Inbox', [
            'messages' => $request->user()->inboxMessages()
                ->latest()
                ->latest('id')
                ->limit(self::SHOWN)
                ->get()
                ->map(fn (ResidentMessage $m) => [
                    'id' => $m->id,
                    'kind' => $m->kind,
                    'title' => $m->title,
                    'body' => $m->body,
                    'actionUrl' => $m->action_url,
                    'read' => $m->read_at !== null,
                    'receivedAt' => $m->created_at->toIso8601String(),
                ]),
        ]);
    }

    public function markRead(Request $request, int $message): RedirectResponse
    {
        $request->user()->inboxMessages()->findOrFail($message)->update(['read_at' => now()]);

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->inboxMessages()->unread()->update(['read_at' => now()]);

        return back();
    }
}
