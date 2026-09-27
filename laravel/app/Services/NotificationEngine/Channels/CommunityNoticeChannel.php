<?php

namespace App\Services\NotificationEngine\Channels;

use App\Models\Notification;
use App\Services\NotificationEngine\ChannelDispatchResult;
use App\Services\NotificationEngine\Contracts\NotificationChannelInterface;
use App\Services\NotificationEngine\NotificationPayload;
use Throwable;

/**
 * Publishes to the community notice board, which residents read by role.
 *
 * This is not a personal inbox. It was called "in_app", and a visitor-arrival
 * alert sent through it with no audience was shown to every resident. So it
 * now refuses anything that looks like a personal message:
 *  - the audience must be explicit: targetRoles, or toEveryone: true;
 *  - a payload with a personal recipient (an email or phone) is refused;
 *  - the notice is signed by $payload->author, never $payload->user, which
 *    other channels treat as the person a message is to.
 * Send personal messages by email or SMS.
 */
class CommunityNoticeChannel implements NotificationChannelInterface
{
    public function name(): string
    {
        return 'community_notice';
    }

    public function label(): string
    {
        return 'Community Notice Board';
    }

    public function provider(): string
    {
        return 'Local Database';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function send(NotificationPayload $payload): ChannelDispatchResult
    {
        if (filled($payload->recipient)) {
            return ChannelDispatchResult::failed(
                $this->name(),
                'Community notices are public. This payload has a personal recipient; send it by email or SMS instead.'
            );
        }

        $targetRoles = array_values(array_filter($payload->targetRoles ?? []));

        if ($targetRoles === [] && ! $payload->toEveryone) {
            return ChannelDispatchResult::failed(
                $this->name(),
                'A community notice needs an explicit audience: targetRoles, or toEveryone: true.'
            );
        }

        try {
            $notification = Notification::create([
                'title' => $payload->title,
                'content' => $payload->body,
                'author_id' => $payload->author?->id,
                'author_name' => $payload->author?->display_name ?? 'Community Hub Management',
                // Null is what the notice board reads as "everyone".
                'target_roles' => $targetRoles === [] ? null : $targetRoles,
                'published_at' => now(),
            ]);

            return ChannelDispatchResult::sent($this->name(), (string) $notification->id, [
                'notification_id' => $notification->id,
            ]);
        } catch (Throwable $e) {
            report($e);

            return ChannelDispatchResult::failed($this->name(), 'Community notice could not be saved: '.$e->getMessage());
        }
    }
}
