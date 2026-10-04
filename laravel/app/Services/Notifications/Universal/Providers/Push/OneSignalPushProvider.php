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

class OneSignalPushProvider implements PushProviderInterface
{
    protected string $appId;

    protected string $restApiKey;

    public function __construct()
    {
        $this->appId = (string) config('notifications.providers.onesignal.app_id', '');
        $this->restApiKey = (string) config('notifications.providers.onesignal.rest_api_key', '');
    }

    public function providerKey(): string
    {
        return 'onesignal';
    }

    public function isConfigured(): bool
    {
        return ! empty($this->appId) && ! empty($this->restApiKey) && ! str_starts_with($this->appId, 'mock');
    }

    public function sendPush(NotificationRecipient $recipient, NotificationMessage $message): ChannelDeliveryReport
    {
        if (empty($recipient->deviceTokens) && empty($recipient->userId)) {
            return ChannelDeliveryReport::skipped('push', $this->providerKey(), 'Recipient has neither device tokens nor user identifier.');
        }

        try {
            if ($this->isConfigured()) {
                $payload = [
                    'app_id' => $this->appId,
                    'headings' => ['en' => $message->title],
                    'contents' => ['en' => $message->body],
                    'data' => $message->data,
                ];

                if (! empty($recipient->deviceTokens)) {
                    $payload['include_player_ids'] = $recipient->deviceTokens;
                } elseif ($recipient->userId) {
                    $payload['include_external_user_ids'] = [(string) $recipient->userId];
                }

                $response = Http::withHeaders([
                    'Authorization' => "Basic {$this->restApiKey}",
                    'Content-Type' => 'application/json',
                ])
                    ->timeout(8)
                    ->post('https://onesignal.com/api/v1/notifications', $payload);

                if (! $response->successful()) {
                    return ChannelDeliveryReport::failed(
                        'push',
                        $this->providerKey(),
                        "OneSignal HTTP {$response->status()}: {$response->body()}"
                    );
                }

                $data = $response->json();
                $messageId = $data['id'] ?? 'os_'.Str::random(24);

                return ChannelDeliveryReport::delivered('push', $this->providerKey(), $messageId);
            }

            // Simulated environment for local/staging/CI
            $mockMessageId = 'os_sim_'.Str::random(24);
            Log::info('[UniversalNotifications:OneSignal] Simulated push', [
                'title' => $message->title,
                'message_id' => $mockMessageId,
            ]);

            return ChannelDeliveryReport::delivered('push', $this->providerKey(), $mockMessageId, [
                'simulated' => true,
            ]);
        } catch (Throwable $e) {
            return ChannelDeliveryReport::failed('push', $this->providerKey(), $e->getMessage());
        }
    }
}
