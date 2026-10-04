<?php

namespace App\Services\Notifications\Universal\Contracts;

use App\Services\Notifications\Universal\DTOs\ChannelDeliveryReport;
use App\Services\Notifications\Universal\DTOs\NotificationMessage;
use App\Services\Notifications\Universal\DTOs\NotificationRecipient;

interface NotificationChannelInterface
{
    /** Unique lowercase channel identifier: email, sms, whatsapp, push, slack, teams, webhook, in_app, database */
    public function key(): string;

    /** Human-readable display label */
    public function label(): string;

    /** Name of the active underlying provider or engine */
    public function activeProviderName(): string;

    /** Check if channel has all necessary configurations and credentials */
    public function isAvailable(): bool;

    /** Dispatch the notification through this channel to the recipient */
    public function send(NotificationRecipient $recipient, NotificationMessage $message): ChannelDeliveryReport;
}
