<?php

namespace App\Services\Notifications\Universal\Channels;

use App\Services\Notifications\Universal\Contracts\WhatsAppProviderInterface;
use App\Services\Notifications\Universal\DTOs\ChannelDeliveryReport;
use App\Services\Notifications\Universal\DTOs\NotificationMessage;
use App\Services\Notifications\Universal\DTOs\NotificationRecipient;
use App\Services\Notifications\Universal\Providers\Messaging\TwilioWhatsAppProvider;
use InvalidArgumentException;

class WhatsAppNotificationChannel extends AbstractNotificationChannel
{
    /** @var array<string, WhatsAppProviderInterface> */
    protected array $providers = [];

    public function __construct(TwilioWhatsAppProvider $twilio)
    {
        $this->providers = [
            'twilio' => $twilio,
        ];
    }

    public function key(): string
    {
        return 'whatsapp';
    }

    public function label(): string
    {
        return 'WhatsApp Gateway';
    }

    public function resolveProvider(?string $providerName = null): WhatsAppProviderInterface
    {
        $name = strtolower($providerName ?? $this->activeProviderName());

        if (! isset($this->providers[$name])) {
            throw new InvalidArgumentException("Unknown WhatsApp provider [{$name}]. Supported: ".implode(', ', array_keys($this->providers)));
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

        return $provider->sendWhatsApp($recipient, $message);
    }
}
