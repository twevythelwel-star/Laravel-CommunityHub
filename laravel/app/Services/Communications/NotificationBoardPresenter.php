<?php

namespace App\Services\Communications;

use App\Enums\UserRole;
use App\Models\AnnouncementRecipient;
use App\Models\Community;
use App\Models\InAppNotification;
use App\Models\Notification;
use App\Models\Property;
use App\Models\User;
use App\Services\NotificationEngine\NotificationEngine;

class NotificationBoardPresenter
{
    public function present(User $user, NotificationEngine $engine): array
    {
        $canBroadcast = $user->can('broadcastNotices');

        // Backward-compatible paginated notices for the notice board feed
        $notifications = Notification::forRole($user->role->value)
            ->with(['recipients' => fn ($q) => $q->where('user_id', $user->id)])
            ->published()
            ->latest('published_at')
            ->paginate(20)
            ->withQueryString()
            ->through(function (Notification $n) {
                $recipient = $n->recipients->first();

                return [
                    'id' => $n->id,
                    'title' => $n->title,
                    'content' => $n->content,
                    'category' => $n->category ?? 'general',
                    'priority' => $n->priority ?? 'normal',
                    'author' => $n->author_name,
                    'timestamp' => $n->published_at?->toIso8601String() ?? $n->created_at->toIso8601String(),
                    'targetRoles' => $n->target_roles,
                    'targetType' => $n->target_type,
                    'channels' => $n->channels,
                    'stats' => $n->stats,
                    'requiresAcknowledgement' => (bool) $n->requires_acknowledgement,
                    'acknowledgementDeadline' => $n->acknowledgement_deadline?->toIso8601String(),
                    'isRead' => $recipient?->read_at !== null,
                    'readAt' => $recipient?->read_at?->toIso8601String(),
                    'isAcknowledged' => $recipient?->acknowledged_at !== null,
                    'acknowledgedAt' => $recipient?->acknowledged_at?->toIso8601String(),
                    'acknowledgementNote' => $recipient?->acknowledgement_note,
                ];
            });

        // Scheduled broadcasts list
        $scheduledAnnouncements = $canBroadcast
            ? Notification::scheduled()
                ->latest('scheduled_for')
                ->get()
                ->map(fn (Notification $n) => [
                    'id' => $n->id,
                    'title' => $n->title,
                    'content' => $n->content,
                    'author' => $n->author_name,
                    'scheduledFor' => $n->scheduled_for?->toIso8601String(),
                    'targetType' => $n->target_type,
                    'channels' => $n->channels,
                    'requiresAcknowledgement' => (bool) $n->requires_acknowledgement,
                    'acknowledgementDeadline' => $n->acknowledgement_deadline?->toIso8601String(),
                ])
            : [];

        // Admin Dashboard: every announcement, one record each in `notifications`.
        $announcements = $canBroadcast
            ? Notification::with('recipients')
                ->latest('created_at')
                ->limit(50)
                ->get()
                ->map(function (Notification $n) {
                    $totalRecipients = $n->recipients->count();
                    $deliveredCount = $n->recipients->where('delivery_state', 'delivered')->count();
                    $readCount = $n->recipients->whereNotNull('read_at')->count();
                    $acknowledgedCount = $n->recipients->whereNotNull('acknowledged_at')->count();

                    return [
                        'id' => $n->id,
                        'title' => $n->title,
                        'message' => $n->content,
                        'category' => $n->category ?? 'general',
                        'priority' => $n->priority ?? 'normal',
                        'status' => $n->isExpired() ? 'expired' : $n->status,
                        'publishTime' => $n->isScheduled() ? null : $n->published_at?->toIso8601String(),
                        'scheduledTime' => $n->scheduled_for?->toIso8601String(),
                        'expiresAt' => $n->expires_at?->toIso8601String(),
                        'isExpired' => $n->isExpired(),
                        'requiresAcknowledgement' => (bool) $n->requires_acknowledgement,
                        'acknowledgementDeadline' => $n->acknowledgement_deadline?->toIso8601String(),
                        'author' => $n->author_name,
                        'targetType' => $n->target_type ?? 'entire_community',
                        'targetCriteria' => $n->target_criteria,
                        'recipientCount' => $totalRecipients,
                        'deliveredCount' => $deliveredCount,
                        'failureCount' => $n->recipients->where('delivery_state', 'failed')->count(),
                        'readCount' => $readCount,
                        'acknowledgedCount' => $acknowledgedCount,
                        'stats' => $n->stats,
                    ];
                })
            : [];

        // Summary KPI stats for Admin Dashboard
        $summaryStats = $canBroadcast
            ? [
                'total' => Notification::count(),
                'drafts' => Notification::where('status', 'draft')->count(),
                'scheduled' => Notification::scheduled()->count(),
                'published' => Notification::published()->count(),
                'expired' => Notification::expired()->count(),
                'totalRecipientsReached' => AnnouncementRecipient::where('delivery_state', 'delivered')->count(),
                'totalDeliveryFailures' => AnnouncementRecipient::where('delivery_state', 'failed')->count(),
                'totalReads' => AnnouncementRecipient::whereNotNull('read_at')->count(),
                'totalAcknowledgements' => AnnouncementRecipient::whereNotNull('acknowledged_at')->count(),
            ]
            : null;

        // Delivery Reports with proof of read vs provider acceptance vs explicit acknowledgement
        $deliveryReports = $canBroadcast
            ? Notification::with('recipients.user')
                ->where('status', 'published')
                ->has('recipients')
                ->latest('published_at')
                ->limit(20)
                ->get()
                ->map(function (Notification $n) {
                    $readUserIds = InAppNotification::whereJsonContains('data->announcement_id', $n->id)
                        ->whereNotNull('read_at')
                        ->pluck('user_id')
                        ->flip()
                        ->all();

                    $recipients = $n->recipients->map(fn (AnnouncementRecipient $r) => [
                        'id' => $r->id,
                        'userId' => $r->user_id,
                        'name' => $r->recipient_name ?? $r->user?->name ?? 'Resident',
                        'email' => $r->recipient_email,
                        'phone' => $r->recipient_phone,
                        'deliveryState' => $r->delivery_state,
                        'attemptedChannels' => $r->attempted_channels ?? ['in_app'],
                        'channelOutcomes' => $r->channel_outcomes ?? [],
                        'failureDetails' => $r->failure_details,
                        'deliveredAt' => $r->delivered_at?->toIso8601String(),
                        'isRead' => $r->read_at !== null || isset($readUserIds[$r->user_id]),
                        'readAt' => $r->read_at?->toIso8601String(),
                        'isAcknowledged' => $r->acknowledged_at !== null,
                        'acknowledgedAt' => $r->acknowledged_at?->toIso8601String(),
                        'acknowledgementNote' => $r->acknowledgement_note,
                    ]);

                    $totalRecipients = $recipients->count();
                    $deliveredCount = $recipients->where('deliveryState', 'delivered')->count();
                    $readCount = $recipients->where('isRead', true)->count();
                    $acknowledgedCount = $recipients->where('isAcknowledged', true)->count();
                    $pendingAckCount = $n->requires_acknowledgement
                        ? $recipients->where('deliveryState', 'delivered')->where('isAcknowledged', false)->count()
                        : 0;

                    return [
                        'announcementId' => $n->id,
                        'title' => $n->title,
                        'category' => $n->category,
                        'priority' => $n->priority,
                        'publishedAt' => $n->published_at?->toIso8601String(),
                        'requiresAcknowledgement' => (bool) $n->requires_acknowledgement,
                        'acknowledgementDeadline' => $n->acknowledgement_deadline?->toIso8601String(),
                        'stats' => $n->stats,
                        'totalRecipients' => $totalRecipients,
                        'deliveredCount' => $deliveredCount,
                        'failedCount' => $recipients->where('deliveryState', 'failed')->count(),
                        'readCount' => $readCount,
                        'acknowledgedCount' => $acknowledgedCount,
                        'pendingAcknowledgementCount' => $pendingAckCount,
                        'recipients' => $recipients->values(),
                    ];
                })
            : [];

        // Street, block, and section options for targeting
        $streets = Property::query()
            ->whereNotNull('street_address')
            ->where('street_address', '!=', '')
            ->distinct()
            ->orderBy('street_address')
            ->pluck('street_address');

        $blocks = Property::query()
            ->whereNotNull('lot_number')
            ->where('lot_number', '!=', '')
            ->distinct()
            ->orderBy('lot_number')
            ->pluck('lot_number')
            ->map(function ($lot) {
                if (preg_match('/(?:Block[- ]?|B[- ]?)([A-Za-z0-9]+)/i', $lot, $matches)) {
                    return $matches[1];
                }

                return null;
            })
            ->filter()
            ->unique()
            ->values();

        $sections = Property::query()
            ->whereNotNull('property_type')
            ->where('property_type', '!=', '')
            ->distinct()
            ->orderBy('property_type')
            ->pluck('property_type');

        return [
            'notifications' => $notifications,
            'scheduledAnnouncements' => $scheduledAnnouncements,
            'announcements' => $announcements,
            'summaryStats' => $summaryStats,
            'deliveryReports' => $deliveryReports,
            'streets' => $streets,
            'blocks' => $blocks,
            'sections' => $sections,
            'canBroadcast' => $canBroadcast,
            'roles' => array_column(UserRole::cases(), 'value'),
            'community' => $this->communityName(),
            'aiEnabled' => filled(config('services.googleai.key')),
            'notificationChannels' => $engine->getChannelsStatus(),
        ];
    }

    private function communityName(): string
    {
        return Community::query()
            ->where('code', config('gatepass.default_community_id'))
            ->value('name') ?? config('app.name');
    }
}
