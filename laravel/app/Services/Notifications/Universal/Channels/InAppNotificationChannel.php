<?php

namespace App\Services\Notifications\Universal\Channels;

use App\Models\InAppNotification;
use App\Services\Notifications\Universal\Contracts\RealtimeProviderInterface;
use App\Services\Notifications\Universal\DTOs\ChannelDeliveryReport;
use App\Services\Notifications\Universal\DTOs\NotificationMessage;
use App\Services\Notifications\Universal\DTOs\NotificationRecipient;
use App\Services\Notifications\Universal\Providers\Realtime\AblyRealtimeProvider;
use App\Services\Notifications\Universal\Providers\Realtime\PusherRealtimeProvider;
use InvalidArgumentException;
use Throwable;

class InAppNotificationChannel extends AbstractNotificationChannel
{
    /** @var array<string, RealtimeProviderInterface> */
    protected array $realtimeProviders = [];

    public function __construct(
        PusherRealtimeProvider $pusher,
        AblyRealtimeProvider $ably
    ) {
        $this->realtimeProviders = [
            'pusher' => $pusher,
            'ably' => $ably,
        ];
    }

    public function key(): string
    {
        return 'in_app';
    }

    public function label(): string
    {
        return 'In-App Live Notification';
    }

    public function resolveRealtimeProvider(?string $providerName = null): RealtimeProviderInterface
    {
        $name = strtolower($providerName ?? (string) config('notifications.default_providers.realtime', 'pusher'));

        if (! isset($this->realtimeProviders[$name])) {
            throw new InvalidArgumentException("Unknown realtime provider [{$name}]. Supported: ".implode(', ', array_keys($this->realtimeProviders)));
        }

        return $this->realtimeProviders[$name];
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function send(NotificationRecipient $recipient, NotificationMessage $message): ChannelDeliveryReport
    {
        try {
            // 1. Persist notification to database for inbox & badge persistence
            $notification = InAppNotification::create([
                'user_id' => $recipient->userId,
                'tenant_id' => $recipient->tenantId,
                'category' => $message->category,
                'title' => $message->title,
                'body' => $message->body,
                'action_url' => $message->actionUrl,
                'priority' => $message->priority,
                'data' => $message->data,
                'read_at' => null,
            ]);

            // 2. Broadcast realtime event (Pusher / Ably / WebSocket)
            $channelName = $recipient->userId
                ? "private-user.{$recipient->userId}"
                : 'presence-community-alerts';

            $realtimeProvider = $this->resolveRealtimeProvider();
            $realtimeReport = $realtimeProvider->broadcastRealtime(
                $channelName,
                'NotificationReceived',
                [
                    'id' => $notification->id,
                    'title' => $notification->title,
                    'body' => $notification->body,
                    'priority' => $notification->priority,
                    'category' => $notification->category,
                    'action_url' => $notification->action_url,
                    'created_at' => $notification->created_at->toISOString(),
                ]
            );

            return ChannelDeliveryReport::delivered(
                $this->key(),
                $this->activeProviderName(),
                (string) $notification->id,
                [
                    'notification_id' => $notification->id,
                    'realtime_provider' => $realtimeProvider->providerKey(),
                    'realtime_status' => $realtimeReport->status,
                ]
            );
        } catch (Throwable $e) {
            return ChannelDeliveryReport::failed($this->key(), $this->activeProviderName(), $e->getMessage());
        }
    }
}
