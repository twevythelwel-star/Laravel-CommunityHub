<?php

namespace App\Services\Notifications\Universal\Contracts;

use App\Models\User;
use App\Services\Notifications\Universal\DTOs\NotificationDispatchSummary;
use App\Services\Notifications\Universal\DTOs\NotificationMessage;
use App\Services\Notifications\Universal\DTOs\NotificationRecipient;

interface NotificationServiceInterface
{
    /**
     * Dispatch a universal notification to a recipient across requested or default channels.
     *
     * @param  array<string>|null  $channels
     */
    public function dispatch(
        NotificationRecipient|User $recipient,
        NotificationMessage $message,
        ?array $channels = null
    ): NotificationDispatchSummary;

    /**
     * Retrieve a registered notification channel by key.
     */
    public function channel(string $key): NotificationChannelInterface;

    /**
     * Retrieve status, availability, and active provider info for all channels.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getChannelsCatalog(): array;

    /**
     * Register or override a channel at runtime.
     */
    public function registerChannel(NotificationChannelInterface $channel): self;

    /**
     * Dynamically swap or assign the provider for a specific channel.
     */
    public function setChannelProvider(string $channelKey, string $providerKey): self;
}
