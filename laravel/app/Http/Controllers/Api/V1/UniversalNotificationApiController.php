<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\InAppNotification;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Services\Notifications\Universal\DTOs\NotificationMessage;
use App\Services\Notifications\Universal\DTOs\NotificationRecipient;
use App\Services\Notifications\Universal\UniversalNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UniversalNotificationApiController extends Controller
{
    public function __construct(
        protected UniversalNotificationService $notifications
    ) {}

    /**
     * Get channels catalog with active providers and readiness.
     */
    public function channels(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Universal notification channels catalog retrieved successfully.',
            'data' => $this->notifications->getChannelsCatalog(),
        ]);
    }

    /**
     * Dispatch a universal notification across specified channels.
     *
     * Administrators only (`broadcastNotices`): this reaches any resident, or
     * any email address, phone number or webhook URL, in the estate's name.
     */
    public function dispatchNotification(Request $request): JsonResponse
    {
        $this->authorize('broadcastNotices');

        $validated = $request->validate([
            'channels' => ['nullable', 'array'],
            'channels.*' => ['string'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string'],
            'webhook_url' => ['nullable', 'url'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'action_url' => ['nullable', 'string', 'max:512'],
            'priority' => ['nullable', 'string', 'in:low,normal,high,urgent'],
            'category' => ['nullable', 'string', 'max:48'],
            'data' => ['nullable', 'array'],
        ]);

        $recipientUser = ! empty($validated['user_id'])
            ? User::find($validated['user_id'])
            : $request->user();

        $recipient = $recipientUser
            ? NotificationRecipient::fromUser($recipientUser)
            : new NotificationRecipient(
                name: $validated['name'] ?? 'Direct Recipient',
                email: $validated['email'] ?? null,
                phone: $validated['phone'] ?? null,
                webhookUrl: $validated['webhook_url'] ?? null
            );

        if (! empty($validated['webhook_url'])) {
            $recipient->webhookUrl = $validated['webhook_url'];
        }

        $message = new NotificationMessage(
            title: $validated['title'],
            body: $validated['body'],
            actionUrl: $validated['action_url'] ?? null,
            priority: $validated['priority'] ?? 'normal',
            category: $validated['category'] ?? 'general',
            data: $validated['data'] ?? []
        );

        $summary = $this->notifications->dispatch($recipient, $message, $validated['channels'] ?? null);

        return response()->json([
            'success' => true,
            'message' => 'Notification dispatched across channels.',
            'data' => $summary->toArray(),
        ], 200);
    }

    /**
     * Retrieve the authenticated user's in-app notification inbox.
     */
    public function inbox(Request $request): JsonResponse
    {
        // Always the caller's own; a `user_id` parameter is not honoured.
        $query = InAppNotification::query()->where('user_id', $request->user()->id);

        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }

        $unreadCount = (clone $query)->unread()->count();
        $notifications = $query->latest()->paginate((int) $request->query('per_page', 20));

        return response()->json([
            'success' => true,
            'unread_count' => $unreadCount,
            'data' => $notifications->items(),
            'pagination' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'total' => $notifications->total(),
            ],
        ]);
    }

    /**
     * Mark an in-app notification as read.
     */
    public function markAsRead(int $id): JsonResponse
    {
        $notification = InAppNotification::findOrFail($id);
        $notification->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read.',
            'data' => [
                'id' => $notification->id,
                'read_at' => $notification->read_at?->toISOString(),
            ],
        ]);
    }

    /**
     * Mark all notifications for the user as read.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $userId = $request->user()?->id ?? $request->input('user_id');

        $query = InAppNotification::query()->unread();
        if ($userId) {
            $query->where('user_id', $userId);
        }

        $updatedCount = $query->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => "Marked {$updatedCount} notifications as read.",
            'updated_count' => $updatedCount,
        ]);
    }

    /**
     * List recent notification delivery logs across all channels.
     */
    public function deliveries(Request $request): JsonResponse
    {
        // The delivery log holds every recipient's address and message; an
        // account sees its own unless it is one that sends to everyone.
        $query = NotificationDelivery::query()->latest();

        if (! $request->user()->can('broadcastNotices')) {
            $query->where('user_id', $request->user()->id);
        }

        if ($channel = $request->query('channel')) {
            $query->where('channel', $channel);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $deliveries = $query->paginate((int) $request->query('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $deliveries->items(),
            'pagination' => [
                'current_page' => $deliveries->currentPage(),
                'last_page' => $deliveries->lastPage(),
                'total' => $deliveries->total(),
            ],
        ]);
    }
}
