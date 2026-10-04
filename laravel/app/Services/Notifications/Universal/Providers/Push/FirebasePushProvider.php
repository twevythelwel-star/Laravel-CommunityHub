<?php

namespace App\Services\Notifications\Universal\Providers\Push;

use App\Services\Notifications\Universal\Contracts\PushProviderInterface;
use App\Services\Notifications\Universal\DTOs\ChannelDeliveryReport;
use App\Services\Notifications\Universal\DTOs\NotificationMessage;
use App\Services\Notifications\Universal\DTOs\NotificationRecipient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class FirebasePushProvider implements PushProviderInterface
{
    protected string $serverKey;

    protected string $projectId;

    public function __construct()
    {
        $this->serverKey = (string) config('notifications.providers.firebase.server_key', '');
        $this->projectId = (string) config('notifications.providers.firebase.project_id', '');
    }

    public function providerKey(): string
    {
        return 'firebase';
    }

    public function isConfigured(): bool
    {
        return ! empty($this->serverKey) && ! str_starts_with($this->serverKey, 'mock');
    }

    public function sendPush(NotificationRecipient $recipient, NotificationMessage $message): ChannelDeliveryReport
    {
        if (empty($recipient->deviceTokens)) {
            return ChannelDeliveryReport::skipped('push', $this->providerKey(), 'Recipient has no registered device tokens.');
        }

        try {
            if ($this->isConfigured()) {
                $response = Http::withHeaders([
                    'Authorization' => "key={$this->serverKey}",
                    'Content-Type' => 'application/json',
                ])
                    ->timeout(8)
                    ->post('https://fcm.googleapis.com/fcm/send', [
                        'registration_ids' => $recipient->deviceTokens,
                        'notification' => [
                            'title' => $message->title,
                            'body' => $message->body,
                            'sound' => $message->sound ?? 'default',
                        ],
                        'data' => array_merge($message->data, [
                            'action_url' => $message->actionUrl,
                            'category' => $message->category,
                            'priority' => $message->priority,
                        ]),
                    ]);

                if (! $response->successful()) {
                    return ChannelDeliveryReport::failed(
                        'push',
                        $this->providerKey(),
                        "Firebase FCM HTTP {$response->status()}: {$response->body()}"
                    );
                }

                $messageId = 'fcm_'.Str::random(24);

                return ChannelDeliveryReport::delivered('push', $this->providerKey(), $messageId);
            }

            // Simulated environment for local/staging/CI
            $mockMessageId = 'fcm_sim_'.Str::random(24);
            Log::info('[UniversalNotifications:FCM] Simulated push to '.count($recipient->deviceTokens).' tokens', [
                'title' => $message->title,
                'message_id' => $mockMessageId,
            ]);

            return ChannelDeliveryReport::delivered('push', $this->providerKey(), $mockMessageId, [
                'token_count' => count($recipient->deviceTokens),
                'simulated' => true,
            ]);
        } catch (Throwable $e) {
            return ChannelDeliveryReport::failed('push', $this->providerKey(), $e->getMessage());
        }
    }
}
