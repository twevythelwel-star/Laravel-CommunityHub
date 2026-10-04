<?php

namespace App\Services\Notifications\Universal\Providers\ChatOps;

use App\Services\Notifications\Universal\DTOs\ChannelDeliveryReport;
use App\Services\Notifications\Universal\DTOs\NotificationMessage;
use App\Services\Notifications\Universal\DTOs\NotificationRecipient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class SlackWebhookProvider
{
    protected string $defaultWebhookUrl;

    protected string $defaultChannel;

    public function __construct()
    {
        $this->defaultWebhookUrl = (string) config('notifications.providers.slack.webhook_url', '');
        $this->defaultChannel = (string) config('notifications.providers.slack.default_channel', '#community-alerts');
    }

    public function providerKey(): string
    {
        return 'slack';
    }

    public function isConfigured(): bool
    {
        return ! empty($this->defaultWebhookUrl) && ! str_contains($this->defaultWebhookUrl, 'mock');
    }

    public function sendSlack(NotificationRecipient $recipient, NotificationMessage $message): ChannelDeliveryReport
    {
        $webhookUrl = $recipient->slackWebhookUrl ?: $this->defaultWebhookUrl;

        if (empty($webhookUrl)) {
            return ChannelDeliveryReport::skipped('slack', $this->providerKey(), 'No Slack webhook URL configured.');
        }

        try {
            $payload = [
                'text' => "*{$message->title}*\n{$message->body}",
                'channel' => $this->defaultChannel,
                'blocks' => [
                    [
                        'type' => 'header',
                        'text' => [
                            'type' => 'plain_text',
                            'text' => $message->title,
                            'emoji' => true,
                        ],
                    ],
                    [
                        'type' => 'section',
                        'text' => [
                            'type' => 'mrkdwn',
                            'text' => $message->body,
                        ],
                    ],
                ],
            ];

            if ($message->actionUrl) {
                $payload['blocks'][] = [
                    'type' => 'actions',
                    'elements' => [
                        [
                            'type' => 'button',
                            'text' => ['type' => 'plain_text', 'text' => 'View Details'],
                            'url' => $message->actionUrl,
                            'style' => 'primary',
                        ],
                    ],
                ];
            }

            if ($this->isConfigured()) {
                $response = Http::timeout(6)->post($webhookUrl, $payload);
                if (! $response->successful()) {
                    return ChannelDeliveryReport::failed(
                        'slack',
                        $this->providerKey(),
                        "Slack HTTP {$response->status()}: {$response->body()}"
                    );
                }

                $msgId = 'slack_'.Str::random(24);

                return ChannelDeliveryReport::delivered('slack', $this->providerKey(), $msgId);
            }

            $simId = 'slack_sim_'.Str::random(24);
            Log::info('[UniversalNotifications:Slack] Simulated dispatch to Slack webhook', [
                'title' => $message->title,
                'message_id' => $simId,
            ]);

            return ChannelDeliveryReport::delivered('slack', $this->providerKey(), $simId, [
                'simulated' => true,
            ]);
        } catch (Throwable $e) {
            return ChannelDeliveryReport::failed('slack', $this->providerKey(), $e->getMessage());
        }
    }
}
