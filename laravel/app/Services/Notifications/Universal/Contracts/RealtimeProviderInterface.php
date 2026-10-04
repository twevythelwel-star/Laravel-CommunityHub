<?php

namespace App\Services\Notifications\Universal\Contracts;

use App\Services\Notifications\Universal\DTOs\ChannelDeliveryReport;

interface RealtimeProviderInterface
{
    public function providerKey(): string;

    public function isConfigured(): bool;

    public function broadcastRealtime(string $channelName, string $eventName, array $payload): ChannelDeliveryReport;
}
