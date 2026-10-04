<?php

namespace App\Services\Notifications\Universal\Channels;

use App\Services\Notifications\Universal\DTOs\ChannelDeliveryReport;
use App\Services\Notifications\Universal\DTOs\NotificationMessage;
use App\Services\Notifications\Universal\DTOs\NotificationRecipient;
use App\Services\Notifications\Universal\Providers\ChatOps\TeamsWebhookProvider;

class TeamsNotificationChannel extends AbstractNotificationChannel
{
    public function __construct(protected TeamsWebhookProvider $provider) {}

    public function key(): string
    {
        return 'teams';
    }

    public function label(): string
    {
        return 'Microsoft Teams Channel';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function send(NotificationRecipient $recipient, NotificationMessage $message): ChannelDeliveryReport
    {
        return $this->provider->sendTeams($recipient, $message);
    }
}
