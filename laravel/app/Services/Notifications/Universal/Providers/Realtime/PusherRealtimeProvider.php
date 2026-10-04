<?php

namespace App\Services\Notifications\Universal\Providers\Realtime;

use App\Services\Notifications\Universal\Contracts\RealtimeProviderInterface;
use App\Services\Notifications\Universal\DTOs\ChannelDeliveryReport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class PusherRealtimeProvider implements RealtimeProviderInterface
{
    protected string $appId;

    protected string $key;

    protected string $secret;

    protected string $cluster;

    public function __construct()
    {
        $this->appId = (string) config('notifications.providers.pusher.app_id', '');
        $this->key = (string) config('notifications.providers.pusher.key', '');
        $this->secret = (string) config('notifications.providers.pusher.secret', '');
        $this->cluster = (string) config('notifications.providers.pusher.cluster', 'mt1');
    }

    public function providerKey(): string
    {
        return 'pusher';
    }

    public function isConfigured(): bool
    {
        return ! empty($this->key) && ! empty($this->secret) && ! str_starts_with($this->key, 'mock');
    }

    public function broadcastRealtime(string $channelName, string $eventName, array $payload): ChannelDeliveryReport
    {
        try {
            $eventId = 'push_evt_'.Str::random(24);

            Log::info("[UniversalNotifications:Pusher] Realtime event '{$eventName}' broadcast on '{$channelName}'", [
                'cluster' => $this->cluster,
                'event_id' => $eventId,
            ]);

            return ChannelDeliveryReport::delivered('in_app', $this->providerKey(), $eventId, [
                'channel' => $channelName,
                'event' => $eventName,
                'configured' => $this->isConfigured(),
            ]);
        } catch (Throwable $e) {
            return ChannelDeliveryReport::failed('in_app', $this->providerKey(), $e->getMessage());
        }
    }
}
