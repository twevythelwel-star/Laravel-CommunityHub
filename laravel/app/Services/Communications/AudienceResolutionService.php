<?php

namespace App\Services\Communications;

use App\Models\AnnouncementRecipient;
use App\Models\Community;
use App\Models\Notification as AnnouncementModel;
use App\Models\User;
use App\Notifications\CommunityAnnouncementNotification;
use App\Services\NotificationEngine\NotificationEngine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

class AudienceResolutionService
{
    /**
     * Builds the scoped query for active, non-deactivated recipients within the community.
     */
    public function resolveQuery(Community $community, string $targetType, ?array $criteria = null): Builder
    {
        $base = User::query()
            ->where('status', 'Active')
            ->whereNull('deactivated_at');

        // Community matching clause
        $communityScope = function (Builder $q) use ($community) {
            $q->where(function ($sub) use ($community) {
                $sub->whereHas('properties', fn ($pq) => $pq->where('community_id', $community->id))
                    ->orWhereDoesntHave('properties');
            });
        };

        $base->where($communityScope);

        return match ($targetType) {
            'entire_community' => $base,

            'street' => $base->where(function (Builder $q) use ($community, $criteria) {
                $street = $criteria['street'] ?? '';
                $q->where('street', $street)
                    ->orWhereHas('properties', fn ($pq) => $pq->where('community_id', $community->id)->where('street_address', $street));
            }),

            'block' => $base->where(function (Builder $q) use ($community, $criteria) {
                $block = $criteria['block'] ?? '';
                $q->where('lot', 'LIKE', "Block-{$block}-%")
                    ->orWhere('lot', 'LIKE', "{$block}-%")
                    ->orWhereHas('properties', fn ($pq) => $pq->where('community_id', $community->id)
                        ->where(fn ($lp) => $lp->where('lot_number', 'LIKE', "Block-{$block}-%")->orWhere('lot_number', 'LIKE', "{$block}-%")));
            }),

            'section' => $base->where(function (Builder $q) use ($community, $criteria) {
                $section = $criteria['section'] ?? '';
                $q->where('lot', 'LIKE', "{$section}%")
                    ->orWhereHas('properties', fn ($pq) => $pq->where('community_id', $community->id)
                        ->where(fn ($sp) => $sp->where('property_type', $section)->orWhere('lot_number', 'LIKE', "{$section}%")));
            }),

            'custom_group' => $base->where(function (Builder $q) use ($community, $criteria) {
                $q->where(function ($customQuery) use ($community, $criteria) {
                    $hasAny = false;

                    if (! empty($criteria['user_ids']) && is_array($criteria['user_ids'])) {
                        $customQuery->whereIn('id', $criteria['user_ids']);
                        $hasAny = true;
                    }

                    if (! empty($criteria['streets']) && is_array($criteria['streets'])) {
                        $streets = $criteria['streets'];
                        $method = $hasAny ? 'orWhere' : 'where';
                        $customQuery->$method(function ($sq) use ($community, $streets) {
                            $sq->whereIn('street', $streets)
                                ->orWhereHas('properties', fn ($pq) => $pq->where('community_id', $community->id)->whereIn('street_address', $streets));
                        });
                        $hasAny = true;
                    }

                    if (! empty($criteria['blocks']) && is_array($criteria['blocks'])) {
                        $blocks = $criteria['blocks'];
                        $method = $hasAny ? 'orWhere' : 'where';
                        $customQuery->$method(function ($bq) use ($community, $blocks) {
                            foreach ($blocks as $block) {
                                $bq->orWhere('lot', 'LIKE', "%{$block}%")
                                    ->orWhereHas('properties', fn ($pq) => $pq->where('community_id', $community->id)->where('lot_number', 'LIKE', "%{$block}%"));
                            }
                        });
                        $hasAny = true;
                    }

                    if (! $hasAny) {
                        $customQuery->whereRaw('1 = 0');
                    }
                });
            }),

            default => $base,
        };
    }

    /**
     * Resolves live audience metrics for composer pre-flight validation.
     *
     * @return array{totalRecipients: int, inAppCount: int, emailCount: int, phoneCount: int}
     */
    public function calculateAudienceMetrics(Community $community, string $targetType, ?array $criteria = null): array
    {
        $query = $this->resolveQuery($community, $targetType, $criteria);

        $uniqueRecipients = (clone $query)->distinct()->count('users.id');
        $email = (clone $query)->whereNotNull('email')->where('email', '!=', '')->distinct()->count('users.id');
        $phone = (clone $query)->whereNotNull('phone')->where('phone', '!=', '')->distinct()->count('users.id');

        // Overlap detection: when criteria matches users across overlapping boundaries
        $rawCount = (clone $query)->count();
        $overlapCount = max(0, $rawCount - $uniqueRecipients);

        // Sample recipients for audience preview
        $sample = (clone $query)
            ->select(['users.id', 'users.display_name', 'users.name', 'users.street', 'users.lot', 'users.email', 'users.phone'])
            ->limit(10)
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->display_name ?? $u->name,
                'street' => $u->street ?? 'Unassigned',
                'lot' => $u->lot ?? 'Unassigned',
                'hasEmail' => filled($u->email),
                'hasPhone' => filled($u->phone),
            ])
            ->all();

        return [
            'totalRecipients' => $uniqueRecipients,
            'uniqueRecipients' => $uniqueRecipients,
            'overlapCount' => $overlapCount,
            'hasOverlaps' => $overlapCount > 0,
            'inAppCount' => $uniqueRecipients,
            'emailCount' => $email,
            'phoneCount' => $phone,
            'sample' => $sample,
        ];
    }

    /**
     * Executes batched dispatch to all resolved recipients without memory bloat.
     */
    public function dispatchInBatches(Community $community, AnnouncementModel $announcement, NotificationEngine $engine): array
    {
        if ($announcement->isCancelled()) {
            Log::info("[AudienceResolutionService] Skipping cancelled announcement #{$announcement->id}");

            return ['cancelled' => true];
        }

        $channels = $announcement->channels ?? ['in_app'];
        $notification = new CommunityAnnouncementNotification($announcement, $channels);

        $stats = [
            'in_app' => 0,
            'email' => 0,
            'sms' => 0,
            'whatsapp' => 0,
            'failed' => 0,
            'total' => 0,
        ];

        $targetType = $announcement->target_type ?? 'entire_community';
        $criteria = $announcement->target_criteria ?? [];

        $this->resolveQuery($community, $targetType, $criteria)
            ->select(['id', 'email', 'phone', 'display_name', 'name', 'status'])
            ->chunkById(250, function ($userBatch) use ($notification, $engine, &$stats, $announcement) {
                // Re-verify cancellation between chunks
                if ($announcement->fresh()->isCancelled()) {
                    return false;
                }

                foreach ($userBatch as $recipient) {
                    // Campaign deduplication: never deliver the same announcement twice to one resident.
                    $alreadyDelivered = AnnouncementRecipient::where('notification_id', $announcement->id)
                        ->where('user_id', $recipient->id)
                        ->where('delivery_state', 'delivered')
                        ->exists();

                    if ($alreadyDelivered) {
                        continue;
                    }

                    $outcomes = $notification->deliver($recipient, $engine);

                    $stats['total']++;
                    if (($outcomes['in_app'] ?? null) === 'delivered') {
                        $stats['in_app']++;
                    }
                    if (($outcomes['email'] ?? null) === 'sent' || ($outcomes['email'] ?? null) === 'delivered') {
                        $stats['email']++;
                    }
                    if (($outcomes['sms'] ?? null) === 'sent' || ($outcomes['sms'] ?? null) === 'delivered') {
                        $stats['sms']++;
                    }
                    if (($outcomes['whatsapp'] ?? null) === 'sent' || ($outcomes['whatsapp'] ?? null) === 'delivered') {
                        $stats['whatsapp']++;
                    }
                    if (in_array('failed', $outcomes, true)) {
                        $stats['failed']++;
                    }

                    // Record per-recipient snapshot, delivery state, and channel outcomes
                    $hasFailed = in_array('failed', $outcomes, true);
                    $hasDelivered = in_array('delivered', $outcomes, true) || in_array('sent', $outcomes, true);
                    $deliveryState = ($hasDelivered && ! $hasFailed) ? 'delivered' : ($hasDelivered && $hasFailed ? 'partially_delivered' : 'failed');

                    AnnouncementRecipient::updateOrCreate(
                        [
                            'notification_id' => $announcement->id,
                            'user_id' => $recipient->id,
                        ],
                        [
                            'recipient_name' => $recipient->display_name ?? $recipient->name,
                            'recipient_email' => $recipient->email,
                            'recipient_phone' => $recipient->phone,
                            'delivery_state' => $deliveryState,
                            'attempted_channels' => array_keys($outcomes),
                            'channel_outcomes' => $outcomes,
                            'delivered_at' => $hasDelivered ? now() : null,
                        ]
                    );
                }

                return true;
            });

        // Mark announcement as published and save stats
        $announcement->update([
            'status' => 'published',
            'sent_at' => now(),
            'published_at' => $announcement->published_at ?? now(),
            'stats' => $stats,
        ]);

        return $stats;
    }
}
