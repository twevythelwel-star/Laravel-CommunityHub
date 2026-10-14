<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\Communications\BroadcastEmergencyAction;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Notifications\BroadcastEmergencyNotificationRequest;
use App\Http\Requests\Notifications\StoreNotificationRequest;
use App\Http\Requests\Notifications\SuggestAudienceRequest;
use App\Models\Community;
use App\Models\Notification;
use App\Services\NotificationEngine\NotificationEngine;
use App\Services\NotificationTargetingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Backs src/app/dashboard/notifications/page.tsx and notification-form.tsx.
 *
 * Three things the original page only appeared to do:
 *
 *   1. "Send Notification" called console.log and raised a toast saying the
 *      notice had been sent. Nothing was stored and nobody received it.
 *   2. The notice list was two hardcoded objects, so a published notice could
 *      never have appeared there anyway.
 *   3. The AI helper imported a client-side Genkit flow that never called a
 *      model, under a caption reading "Powered by Google Gemini".
 *
 * And one it did not do at all: the flow is named "generate targeted
 * notifications", the table carries `target_roles` and Notification::forRole()
 * filters on it — but no control ever set an audience, so every notice went to
 * everyone. The composer now picks the audience explicitly.
 */
class NotificationController extends Controller
{
    /**
     * The notices this viewer's role may see, and the composer.
     *
     * aiEnabled says whether the AI helper will reach a model at all. Without
     * a key the service returns the original stub's four canned lines, and
     * the composer should not be told Gemini wrote them.
     */
    public function index(Request $request, NotificationEngine $engine): Response
    {
        $user = $request->user();

        return Inertia::render('Dashboard/Notifications', [
            'notifications' => Notification::forRole($user->role->value)
                ->latest('published_at')
                ->paginate(20)
                ->withQueryString()
                ->through(fn (Notification $n) => [
                    'id' => $n->id,
                    'title' => $n->title,
                    'content' => $n->content,
                    'author' => $n->author_name,
                    'timestamp' => $n->published_at->toIso8601String(),
                    'targetRoles' => $n->target_roles,
                ]),
            'canBroadcast' => $user->can('broadcastNotices'),
            'roles' => array_column(UserRole::cases(), 'value'),
            'community' => $this->communityName(),
            'aiEnabled' => filled(config('services.googleai.key')),
            'notificationChannels' => $engine->getChannelsStatus(),
        ]);
    }

    public function store(StoreNotificationRequest $request): RedirectResponse
    {
        $user = $request->user();
        $roles = $request->targetRoles();

        $notification = Notification::create([
            'title' => $request->validated('title'),
            'content' => $request->validated('content'),
            'target_roles' => $roles,
            'author_id' => $user->id,
            'author_name' => $user->display_name,
            'published_at' => now(),
        ]);

        $user->recordActivity("Published notice: {$notification->title}");

        return back()->with('success', $roles === null
            ? 'Notice published to the whole community.'
            : 'Notice published to '.implode(', ', $roles).'.');
    }

    /**
     * Drafts notice copy from a pasted document.
     *
     * Consent is checked here rather than only in the dialog. The promise made
     * to the user is that their text reaches Google only if they agree, and a
     * promise enforced in the browser is not enforced at all — the endpoint was
     * reachable by anyone with `broadcastNotices` regardless of what they had
     * agreed to, and the agreement itself lived in localStorage, so clearing
     * site data silently revoked a consent no record was ever kept of.
     */
    public function suggestAudience(SuggestAudienceRequest $request, NotificationTargetingService $targeting): JsonResponse
    {
        $user = $request->user();

        if (! $user->ai_consent) {
            return response()->json([
                'message' => 'Your consent is needed before any text is sent to Google for processing.',
                'reason' => 'ai_consent_required',
            ], 403);
        }

        $validated = $request->validated();

        /*
         | The community is not the composer's to type. The original offered a
         | free-text "Target Community" box defaulting to "Green Meadows", which
         | only ever decided what the model was told the estate was called.
         */
        $result = $targeting->generate($validated['document'], $this->communityName());

        $user->recordActivity('Sent a document to the AI drafting service');

        return response()->json($result);
    }

    /** An emergency broadcast across push, SMS, WhatsApp, email and in-app; see BroadcastEmergencyAction. */
    public function broadcastEmergency(BroadcastEmergencyNotificationRequest $request, BroadcastEmergencyAction $broadcast): RedirectResponse|JsonResponse
    {
        $sent = $broadcast->execute($request->user(), $request->validated());

        $summary = "Emergency broadcast dispatched: {$sent['in_app']} In-App, {$sent['sms']} SMS, {$sent['whatsapp']} WhatsApp, {$sent['email']} Emails, {$sent['push']} Push alerts.";

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $summary, 'stats' => $sent]);
        }

        return back()->with('success', $summary);
    }

    private function communityName(): string
    {
        return Community::query()
            ->where('code', config('gatepass.default_community_id'))
            ->value('name') ?? config('app.name');
    }
}
