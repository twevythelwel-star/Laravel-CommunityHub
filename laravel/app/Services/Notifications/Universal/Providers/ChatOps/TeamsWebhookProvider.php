<?php

namespace App\Services\Notifications\Universal\Providers\ChatOps;

use App\Services\Notifications\Universal\DTOs\ChannelDeliveryReport;
use App\Services\Notifications\Universal\DTOs\NotificationMessage;
use App\Services\Notifications\Universal\DTOs\NotificationRecipient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class TeamsWebhookProvider
{
    protected string $defaultWebhookUrl;

    public function __construct()
    {
        $this->defaultWebhookUrl = (string) config('notifications.providers.teams.webhook_url', '');
    }

    public function providerKey(): string
    {
        return 'teams';
    }

    public function isConfigured(): bool
    {
        return ! empty($this->defaultWebhookUrl) && ! str_contains($this->defaultWebhookUrl, 'mock');
    }

    public function sendTeams(NotificationRecipient $recipient, NotificationMessage $message): ChannelDeliveryReport
    {
        $webhookUrl = $recipient->teamsWebhookUrl ?: $this->defaultWebhookUrl;

        if (empty($webhookUrl)) {
            return ChannelDeliveryReport::skipped('teams', $this->providerKey(), 'No Teams webhook URL configured.');
        }

        try {
            $card = [
                '@type' => 'MessageCard',
                '@context' => 'http://schema.org/extensions',
                'themeColor' => match ($message->priority) {
                    'urgent', 'high' => 'D83B01',
                    'low' => '107C41',
                    default => '0078D7',
                },
                'summary' => $message->title,
                'sections' => [
                    [
                        'activityTitle' => $message->title,
                        'activitySubtitle' => 'Category: '.ucfirst($message->category),
                        'text' => $message->body,
                    ],
                ],
            ];

            if ($message->actionUrl) {
                $card['potentialAction'] = [
                    [
                        '@type' => 'OpenUri',
                        'name' => 'View Action',
                        'targets' => [
                            ['os' => 'default', 'uri' => $message->actionUrl],
                        ],
                    ],
                ];
            }

            if ($this->isConfigured()) {
                $response = Http::timeout(6)->post($webhookUrl, $card);
                if (! $response->successful()) {
                    return ChannelDeliveryReport::failed(
                        'teams',
                        $this->providerKey(),
                        "Teams HTTP {$response->status()}: {$response->body()}"
                    );
                }

                $msgId = 'teams_'.Str::random(24);

                return ChannelDeliveryReport::delivered('teams', $this->providerKey(), $msgId);
            }

            $simId = 'teams_sim_'.Str::random(24);
            Log::info('[UniversalNotifications:Teams] Simulated dispatch to Teams webhook', [
                'title' => $message->title,
                'message_id' => $simId,
            ]);

            return ChannelDeliveryReport::delivered('teams', $this->providerKey(), $simId, [
                'simulated' => true,
            ]);
        } catch (Throwable $e) {
            return ChannelDeliveryReport::failed('teams', $this->providerKey(), $e->getMessage());
        }
    }
}
