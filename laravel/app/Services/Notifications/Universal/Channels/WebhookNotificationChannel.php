<?php

namespace App\Services\Notifications\Universal\Channels;

use App\Services\Notifications\Universal\DTOs\ChannelDeliveryReport;
use App\Services\Notifications\Universal\DTOs\NotificationMessage;
use App\Services\Notifications\Universal\DTOs\NotificationRecipient;
use App\Services\Notifications\Universal\Providers\ChatOps\SignedHttpWebhookProvider;

class WebhookNotificationChannel extends AbstractNotificationChannel
{
    public function __construct(protected SignedHttpWebhookProvider $provider) {}

    public function key(): string
    {
        return 'webhook';
    }

    public function label(): string
    {
        return 'Signed HTTP Webhook';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function send(NotificationRecipient $recipient, NotificationMessage $message): ChannelDeliveryReport
    {
        return $this->provider->dispatchSignedWebhook($recipient, $message);
    }
}
