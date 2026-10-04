<?php

namespace App\Services\Notifications\Universal;

use App\Models\NotificationDelivery;
use App\Models\User;
use App\Services\Notifications\Universal\Channels\AbstractNotificationChannel;
use App\Services\Notifications\Universal\Channels\DatabaseNotificationChannel;
use App\Services\Notifications\Universal\Channels\EmailNotificationChannel;
use App\Services\Notifications\Universal\Channels\InAppNotificationChannel;
use App\Services\Notifications\Universal\Channels\PushNotificationChannel;
use App\Services\Notifications\Universal\Channels\SlackNotificationChannel;
use App\Services\Notifications\Universal\Channels\SmsNotificationChannel;
use App\Services\Notifications\Universal\Channels\TeamsNotificationChannel;
use App\Services\Notifications\Universal\Channels\WebhookNotificationChannel;
use App\Services\Notifications\Universal\Channels\WhatsAppNotificationChannel;
use App\Services\Notifications\Universal\Contracts\NotificationChannelInterface;
use App\Services\Notifications\Universal\Contracts\NotificationServiceInterface;
use App\Services\Notifications\Universal\DTOs\ChannelDeliveryReport;
use App\Services\Notifications\Universal\DTOs\NotificationDispatchSummary;
use App\Services\Notifications\Universal\DTOs\NotificationMessage;
use App\Services\Notifications\Universal\DTOs\NotificationRecipient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class UniversalNotificationService implements NotificationServiceInterface
{
    /** @var array<string, NotificationChannelInterface> */
    protected array $channels = [];

    public function __construct(
        EmailNotificationChannel $email,
        SmsNotificationChannel $sms,
        WhatsAppNotificationChannel $whatsApp,
        PushNotificationChannel $push,
        SlackNotificationChannel $slack,
        TeamsNotificationChannel $teams,
        WebhookNotificationChannel $webhook,
        DatabaseNotificationChannel $database,
        InAppNotificationChannel $inApp,
    ) {
        $this->channels = [
            'email' => $email,
            'sms' => $sms,
            'whatsapp' => $whatsApp,
            'push' => $push,
            'slack' => $slack,
            'teams' => $teams,
            'webhook' => $webhook,
            'database' => $database,
            'in_app' => $inApp,
        ];
    }

    public function channel(string $key): NotificationChannelInterface
    {
        $normalized = strtolower(trim($key));

        if (! isset($this->channels[$normalized])) {
            throw new InvalidArgumentException("Notification channel [{$key}] is not registered.");
        }

        return $this->channels[$normalized];
    }

    public function registerChannel(NotificationChannelInterface $channel): self
    {
        $this->channels[strtolower($channel->key())] = $channel;

        return $this;
    }

    public function setChannelProvider(string $channelKey, string $providerKey): self
    {
        $channel = $this->channel($channelKey);

        if ($channel instanceof AbstractNotificationChannel) {
            $channel->setActiveProvider($providerKey);
        }

        return $this;
    }

    public function getChannelsCatalog(): array
    {
        $catalog = [];

        foreach ($this->channels as $key => $channel) {
            $isAvailable = $channel->isAvailable();

            $catalog[$key] = [
                'key' => $key,
                'label' => $channel->label(),
                'active_provider' => $channel->activeProviderName(),
                'available' => $isAvailable,
                'status' => $isAvailable ? 'operational' : 'degraded',
            ];
        }

        return $catalog;
    }

    public function dispatch(
        NotificationRecipient|User $recipient,
        NotificationMessage $message,
        ?array $channels = null
    ): NotificationDispatchSummary {
        $recipientDto = $recipient instanceof User
            ? NotificationRecipient::fromUser($recipient)
            : $recipient;

        $targetChannels = $channels ?? (array) config('notifications.default_channels', ['in_app', 'email']);
        $trackingId = 'notif_trk_'.Str::random(24);
        $reports = [];

        foreach ($targetChannels as $channelKey) {
            $channelKey = strtolower(trim($channelKey));

            if (! isset($this->channels[$channelKey])) {
                $reports[$channelKey] = ChannelDeliveryReport::failed(
                    $channelKey,
                    'unknown',
                    "Channel [{$channelKey}] is not registered."
                );

                continue;
            }

            $channel = $this->channels[$channelKey];

            try {
                $report = $channel->send($recipientDto, $message);

                // Handle automated fallback if delivery failed or was skipped
                if (! $report->isSuccess() && $fallbackChannel = config("notifications.fallbacks.{$channelKey}")) {
                    Log::info("[UniversalNotifications] Channel {$channelKey} did not succeed; triggering fallback to {$fallbackChannel}");
                    if (isset($this->channels[$fallbackChannel]) && ! in_array($fallbackChannel, $targetChannels, true)) {
                        $fallbackReport = $this->channels[$fallbackChannel]->send($recipientDto, $message);
                        $reports[$fallbackChannel] = $fallbackReport;
                        $this->recordDeliveryAudit($recipientDto, $fallbackChannel, $fallbackReport);
                    }
                }
            } catch (Throwable $e) {
                $report = ChannelDeliveryReport::failed(
                    $channelKey,
                    $channel->activeProviderName(),
                    $e->getMessage()
                );
            }

            $reports[$channelKey] = $report;
            $this->recordDeliveryAudit($recipientDto, $channelKey, $report);
        }

        return new NotificationDispatchSummary(
            trackingId: $trackingId,
            recipient: $recipientDto,
            message: $message,
            reports: $reports
        );
    }

    protected function recordDeliveryAudit(
        NotificationRecipient $recipient,
        string $channel,
        ChannelDeliveryReport $report
    ): void {
        if (! config('notifications.logging.record_deliveries', true)) {
            return;
        }

        try {
            if (Schema::hasTable('notification_deliveries')) {
                $recipientContact = match ($channel) {
                    'email' => $recipient->email,
                    'sms', 'whatsapp' => $recipient->phone,
                    'webhook' => $recipient->webhookUrl,
                    'slack' => $recipient->slackWebhookUrl ?? 'default_slack_webhook',
                    'teams' => $recipient->teamsWebhookUrl ?? 'default_teams_webhook',
                    'push' => ! empty($recipient->deviceTokens) ? implode(',', $recipient->deviceTokens) : (string) $recipient->userId,
                    default => (string) ($recipient->userId ?? $recipient->name ?? 'system'),
                };

                NotificationDelivery::create([
                    'transaction_id' => null,
                    'user_id' => $recipient->userId,
                    'channel' => substr($channel, 0, 16),
                    'recipient' => substr((string) $recipientContact, 0, 128),
                    'provider' => substr($report->provider, 0, 32),
                    'provider_message_id' => substr((string) ($report->messageId ?? 'none'), 0, 128),
                    'status' => $report->status,
                    'error_code' => $report->isSuccess() ? null : 'ERR_DELIVERY',
                    'error_message' => $report->error,
                    'sent_at' => $report->sentAt,
                    'delivered_at' => $report->status === 'delivered' ? $report->sentAt : null,
                ]);
            }
        } catch (Throwable $e) {
            // Failsafe logging: audit failure should never block notification dispatch
            Log::warning("[UniversalNotifications] Failed recording audit delivery: {$e->getMessage()}");
        }
    }
}
