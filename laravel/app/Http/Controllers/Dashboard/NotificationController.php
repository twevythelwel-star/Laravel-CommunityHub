<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\Communications\BroadcastEmergencyAction;
use App\Actions\Communications\PublishAnnouncementAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Notifications\AcknowledgeAnnouncementRequest;
use App\Http\Requests\Notifications\BroadcastEmergencyNotificationRequest;
use App\Http\Requests\Notifications\CancelAnnouncementRequest;
use App\Http\Requests\Notifications\PreviewAudienceRequest;
use App\Http\Requests\Notifications\ReadAnnouncementRequest;
use App\Http\Requests\Notifications\StoreNotificationRequest;
use App\Http\Requests\Notifications\SuggestAudienceRequest;
use App\Models\Community;
use App\Models\Notification;
use App\Services\Communications\AudienceResolutionService;
use App\Services\Communications\NotificationBoardPresenter;
use App\Services\NotificationEngine\NotificationEngine;
use App\Services\NotificationTargetingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Backs src/app/dashboard/notifications/page.tsx and notification-form.tsx.
 */
class NotificationController extends Controller
{
    public function index(Request $request, NotificationBoardPresenter $presenter, NotificationEngine $engine): Response
    {
        return Inertia::render('Dashboard/Notifications', $presenter->present($request->user(), $engine));
    }

    public function store(StoreNotificationRequest $request, PublishAnnouncementAction $action): RedirectResponse
    {
        $notification = $action->execute(
            $request->user(),
            $request->validated(),
            $request->targetRoles(),
            $request->channels()
        );

        return back()->with('success', $notification->isScheduled()
            ? "Announcement scheduled for {$notification->scheduled_for}."
            : ($notification->target_roles === null ? 'Notice published to the whole community.' : 'Notice published to '.implode(', ', $notification->target_roles).'.'));
    }

    public function previewAudience(PreviewAudienceRequest $request, AudienceResolutionService $resolver): JsonResponse
    {
        $communityId = $request->validated('community_id') ?? $request->user()->community_id;
        $community = $communityId ? Community::find($communityId) : Community::query()->where('code', config('gatepass.default_community_id'))->first() ?? Community::first();

        $metrics = $resolver->calculateAudienceMetrics(
            $community,
            $request->validated('target_type'),
            $request->validated('target_criteria')
        );

        return response()->json($metrics);
    }

    public function cancel(CancelAnnouncementRequest $request, Notification $announcement): RedirectResponse
    {
        $announcement->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        $request->user()->recordActivity("Cancelled scheduled announcement: {$announcement->title}");

        return back()->with('success', 'Scheduled announcement was cancelled.');
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

    /** Mark an announcement as read by the current authenticated user. */
    /** A resident marks a notice read; see Notification::markReadBy(). */
    public function markAsRead(ReadAnnouncementRequest $request, Notification $announcement): JsonResponse|RedirectResponse
    {
        $recipient = $announcement->markReadBy($request->user());

        return $request->wantsJson()
            ? response()->json(['success' => true, 'read_at' => $recipient->read_at])
            : back()->with('success', 'Notice marked as read.');
    }

    /** Explicitly acknowledge an important announcement (separate from reading it). */
    /** A resident confirms they have read and understood a notice. */
    public function acknowledge(AcknowledgeAnnouncementRequest $request, Notification $announcement): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $recipient = $announcement->recipientRecordFor($user)->acknowledge($request->validated('note'));
        $user->recordActivity("Resident acknowledged notice '{$announcement->title}'");

        return $request->wantsJson()
            ? response()->json(['success' => true, 'acknowledged_at' => $recipient->acknowledged_at, 'note' => $recipient->acknowledgement_note])
            : back()->with('success', "Thank you — your acknowledgement of '{$announcement->title}' has been recorded.");
    }

    private function communityName(): string
    {
        return Community::query()
            ->where('code', config('gatepass.default_community_id'))
            ->value('name') ?? config('app.name');
    }
}
