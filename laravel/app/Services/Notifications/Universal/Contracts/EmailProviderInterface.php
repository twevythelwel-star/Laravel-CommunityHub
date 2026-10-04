<?php

namespace App\Services\Notifications\Universal\Contracts;

use App\Services\Notifications\Universal\DTOs\ChannelDeliveryReport;
use App\Services\Notifications\Universal\DTOs\NotificationMessage;
use App\Services\Notifications\Universal\DTOs\NotificationRecipient;

interface EmailProviderInterface
{
    public function providerKey(): string;

    public function isConfigured(): bool;

    public function sendEmail(NotificationRecipient $recipient, NotificationMessage $message): ChannelDeliveryReport;
}
