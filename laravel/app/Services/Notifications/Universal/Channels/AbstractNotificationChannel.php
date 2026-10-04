<?php

namespace App\Services\Notifications\Universal\Channels;

use App\Services\Notifications\Universal\Contracts\NotificationChannelInterface;

abstract class AbstractNotificationChannel implements NotificationChannelInterface
{
    protected ?string $activeProvider = null;

    public function activeProviderName(): string
    {
        return $this->activeProvider ?? (string) config("notifications.default_providers.{$this->key()}", $this->key());
    }

    public function setActiveProvider(string $provider): self
    {
        $this->activeProvider = $provider;

        return $this;
    }
}
