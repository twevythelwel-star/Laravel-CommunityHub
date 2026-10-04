<?php

namespace App\Services\Notifications;

use App\Models\User;
use App\Services\Notifications\Universal\Contracts\NotificationChannelInterface;
use App\Services\Notifications\Universal\Contracts\NotificationServiceInterface;
use App\Services\Notifications\Universal\DTOs\NotificationDispatchSummary;
use App\Services\Notifications\Universal\DTOs\NotificationMessage;
use App\Services\Notifications\Universal\DTOs\NotificationRecipient;
use App\Services\Notifications\Universal\UniversalNotificationService;

/**
 * Universal NotificationService Facade & Root Entrypoint.
 *
 * Implements clean channel abstraction:
 *
 * NotificationService
 *        ↓
 * NotificationChannel
 *        ├── Email (SendGrid, Mailgun, Postmark, SES, Laravel)
 *        ├── SMS (Twilio)
 *        ├── WhatsApp (Twilio)
 *        ├── Push (Firebase FCM, OneSignal)
 *        ├── Database (In-App notifications table)
 *        ├── In-App (Realtime Pusher/Ably broadcasting)
 *        ├── Slack (Incoming Webhooks)
 *        ├── Teams (Adaptive Cards)
 *        └── Webhook (HMAC signed outbound HTTP)
 */
class NotificationService implements NotificationServiceInterface
{
    public function __construct(
        protected UniversalNotificationService $engine
    ) {}

    public function dispatch(
        NotificationRecipient|User $recipient,
        NotificationMessage $message,
        ?array $channels = null
    ): NotificationDispatchSummary {
        return $this->engine->dispatch($recipient, $message, $channels);
    }

    public function channel(string $key): NotificationChannelInterface
    {
        return $this->engine->channel($key);
    }

    public function getChannelsCatalog(): array
    {
        return $this->engine->getChannelsCatalog();
    }

    public function registerChannel(NotificationChannelInterface $channel): self
    {
        $this->engine->registerChannel($channel);

        return $this;
    }

    public function setChannelProvider(string $channelKey, string $providerKey): self
    {
        $this->engine->setChannelProvider($channelKey, $providerKey);

        return $this;
    }

    /**
     * Fluent quick helper for dispatching a notification.
     */
    public function send(
        NotificationRecipient|User $recipient,
        string $title,
        string $body,
        ?array $channels = null,
        array $options = []
    ): NotificationDispatchSummary {
        $message = new NotificationMessage(
            title: $title,
            body: $body,
            actionUrl: $options['action_url'] ?? null,
            priority: $options['priority'] ?? 'normal',
            category: $options['category'] ?? 'general',
            data: $options['data'] ?? [],
        );

        return $this->dispatch($recipient, $message, $channels);
    }
}
