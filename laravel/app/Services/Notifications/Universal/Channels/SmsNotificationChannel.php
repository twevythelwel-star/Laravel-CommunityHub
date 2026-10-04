<?php

namespace App\Services\Notifications\Universal\Channels;

use App\Services\Notifications\Universal\Contracts\SmsProviderInterface;
use App\Services\Notifications\Universal\DTOs\ChannelDeliveryReport;
use App\Services\Notifications\Universal\DTOs\NotificationMessage;
use App\Services\Notifications\Universal\DTOs\NotificationRecipient;
use App\Services\Notifications\Universal\Providers\Messaging\TwilioSmsProvider;
use InvalidArgumentException;

class SmsNotificationChannel extends AbstractNotificationChannel
{
    /** @var array<string, SmsProviderInterface> */
    protected array $providers = [];

    public function __construct(TwilioSmsProvider $twilio)
    {
        $this->providers = [
            'twilio' => $twilio,
        ];
    }

    public function key(): string
    {
        return 'sms';
    }

    public function label(): string
    {
        return 'SMS Gateway';
    }

    public function resolveProvider(?string $providerName = null): SmsProviderInterface
    {
        $name = strtolower($providerName ?? $this->activeProviderName());

        if (! isset($this->providers[$name])) {
            throw new InvalidArgumentException("Unknown SMS provider [{$name}]. Supported: ".implode(', ', array_keys($this->providers)));
        }

        return $this->providers[$name];
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function send(NotificationRecipient $recipient, NotificationMessage $message): ChannelDeliveryReport
    {
        $provider = $this->resolveProvider();

        return $provider->sendSms($recipient, $message);
    }
}
