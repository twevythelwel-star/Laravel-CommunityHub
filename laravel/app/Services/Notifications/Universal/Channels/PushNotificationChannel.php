<?php

namespace App\Services\Notifications\Universal\Channels;

use App\Services\Notifications\Universal\Contracts\PushProviderInterface;
use App\Services\Notifications\Universal\DTOs\ChannelDeliveryReport;
use App\Services\Notifications\Universal\DTOs\NotificationMessage;
use App\Services\Notifications\Universal\DTOs\NotificationRecipient;
use App\Services\Notifications\Universal\Providers\Push\FirebasePushProvider;
use App\Services\Notifications\Universal\Providers\Push\OneSignalPushProvider;
use InvalidArgumentException;

class PushNotificationChannel extends AbstractNotificationChannel
{
    /** @var array<string, PushProviderInterface> */
    protected array $providers = [];

    public function __construct(
        FirebasePushProvider $firebase,
        OneSignalPushProvider $oneSignal
    ) {
        $this->providers = [
            'firebase' => $firebase,
            'onesignal' => $oneSignal,
        ];
    }

    public function key(): string
    {
        return 'push';
    }

    public function label(): string
    {
        return 'Mobile & Web Push';
    }

    public function resolveProvider(?string $providerName = null): PushProviderInterface
    {
        $name = strtolower($providerName ?? $this->activeProviderName());

        if (! isset($this->providers[$name])) {
            throw new InvalidArgumentException("Unknown Push provider [{$name}]. Supported: ".implode(', ', array_keys($this->providers)));
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

        return $provider->sendPush($recipient, $message);
    }
}
