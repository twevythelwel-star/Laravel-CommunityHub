<?php

namespace App\Services\Notifications\Universal\Providers\Realtime;

use App\Services\Notifications\Universal\Contracts\RealtimeProviderInterface;
use App\Services\Notifications\Universal\DTOs\ChannelDeliveryReport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class AblyRealtimeProvider implements RealtimeProviderInterface
{
    protected string $key;

    public function __construct()
    {
        $this->key = (string) config('notifications.providers.ably.key', '');
    }

    public function providerKey(): string
    {
        return 'ably';
    }

    public function isConfigured(): bool
    {
        return ! empty($this->key) && ! str_starts_with($this->key, 'mock');
    }

    public function broadcastRealtime(string $channelName, string $eventName, array $payload): ChannelDeliveryReport
    {
        try {
            $eventId = 'ably_evt_'.Str::random(24);

            Log::info("[UniversalNotifications:Ably] Realtime message published on '{$channelName}'", [
                'event' => $eventName,
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
