<?php

namespace App\Services\NotificationEngine\Channels;

use App\Models\InAppNotification;
use App\Models\Notification;
use App\Models\User;
use App\Services\NotificationEngine\ChannelDispatchResult;
use App\Services\NotificationEngine\Contracts\NotificationChannelInterface;
use App\Services\NotificationEngine\NotificationPayload;
use Throwable;

class InAppChannel implements NotificationChannelInterface
{
    public function name(): string
    {
        return 'in_app';
    }

    public function label(): string
    {
        return 'In-App Notification Feed';
    }

    public function provider(): string
    {
        return 'CommunityHub Notice Board & Ledger';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    /**
     * A message addressed to a person goes to that person's notifications; only a broadcast
     * (role-targeted or flagged) is published on the community notice board.
     */
    public function send(NotificationPayload $payload): ChannelDispatchResult
    {
        try {
            if ($addressee = $this->addressee($payload)) {
                return $this->deliverTo($addressee, $payload);
            }

            if ($payload->targetRoles !== null || ($payload->metadata['broadcast'] ?? false)) {
                return $this->publish($payload);
            }

            return ChannelDispatchResult::failed($this->name(), 'In-app message has no addressee; refusing to publish it to the community.');
        } catch (Throwable $e) {
            report($e);

            return ChannelDispatchResult::failed($this->name(), 'In-App persistence failed: '.$e->getMessage());
        }
    }

    private function addressee(NotificationPayload $payload): ?User
    {
        if ($payload->user) {
            return $payload->user;
        }

        return ctype_digit((string) $payload->recipient) ? User::find((int) $payload->recipient) : null;
    }

    private function deliverTo(User $user, NotificationPayload $payload): ChannelDispatchResult
    {
        $notification = InAppNotification::create([
            'user_id' => $user->id,
            'category' => $payload->metadata['category'] ?? $payload->metadata['type'] ?? 'general',
            'title' => $payload->title,
            'body' => $payload->body,
            'action_url' => $payload->actionUrl ?? $payload->metadata['url'] ?? null,
            'priority' => $payload->metadata['priority'] ?? 'normal',
            'data' => $payload->metadata,
        ]);

        return ChannelDispatchResult::sent($this->name(), (string) $notification->id, [
            'in_app_notification_id' => $notification->id,
        ]);
    }

    private function publish(NotificationPayload $payload): ChannelDispatchResult
    {
        $notification = Notification::create([
            'title' => $payload->title,
            'content' => $payload->body,
            'author_name' => 'Community Hub Management',
            'target_roles' => $payload->targetRoles,
            'published_at' => now(),
        ]);

        return ChannelDispatchResult::sent($this->name(), (string) $notification->id, [
            'notification_id' => $notification->id,
        ]);
    }
}
