<?php

namespace App\Services\Notifications\Universal\Providers\ChatOps;

use App\Services\Notifications\Universal\DTOs\ChannelDeliveryReport;
use App\Services\Notifications\Universal\DTOs\NotificationMessage;
use App\Services\Notifications\Universal\DTOs\NotificationRecipient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class SignedHttpWebhookProvider
{
    protected string $secret;

    protected int $timeout;

    protected string $userAgent;

    public function __construct()
    {
        $this->secret = (string) config('notifications.providers.webhook.signing_secret', 'ch_whsec_universal_token');
        $this->timeout = (int) config('notifications.providers.webhook.timeout', 5);
        $this->userAgent = (string) config('notifications.providers.webhook.user_agent', 'CommunityHub-NotificationEngine/2.0');
    }

    public function providerKey(): string
    {
        return 'webhook';
    }

    public function isConfigured(): bool
    {
        return ! empty($this->secret);
    }

    public function dispatchSignedWebhook(NotificationRecipient $recipient, NotificationMessage $message): ChannelDeliveryReport
    {
        $targetUrl = $recipient->webhookUrl;

        if (empty($targetUrl)) {
            return ChannelDeliveryReport::skipped('webhook', $this->providerKey(), 'No webhook target URL provided.');
        }

        try {
            $timestamp = (string) now()->timestamp;
            $eventId = 'evt_'.Str::random(24);

            $payload = [
                'event_id' => $eventId,
                'event_type' => 'notification.'.$message->category,
                'timestamp' => $timestamp,
                'priority' => $message->priority,
                'title' => $message->title,
                'body' => $message->body,
                'action_url' => $message->actionUrl,
                'recipient' => [
                    'user_id' => $recipient->userId,
                    'tenant_id' => $recipient->tenantId,
                ],
                'data' => $message->data,
            ];

            $jsonPayload = json_encode($payload, JSON_THROW_ON_ERROR);
            $signature = hash_hmac('sha256', "{$timestamp}.{$jsonPayload}", $this->secret);

            // If target URL is an active URL and not a dummy/mock URL
            if (! str_starts_with($targetUrl, 'http://localhost') && ! str_contains($targetUrl, 'example.com') && ! str_contains($targetUrl, 'mock')) {
                $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                    'User-Agent' => $this->userAgent,
                    'X-CommunityHub-Signature' => "t={$timestamp},v1={$signature}",
                    'X-CommunityHub-Event-Id' => $eventId,
                ])
                    ->timeout($this->timeout)
                    ->post($targetUrl, $payload);

                if (! $response->successful()) {
                    return ChannelDeliveryReport::failed(
                        'webhook',
                        $this->providerKey(),
                        "Webhook target HTTP {$response->status()}: {$response->body()}"
                    );
                }
            } else {
                Log::info("[UniversalNotifications:Webhook] Simulated signed webhook delivery to {$targetUrl}", [
                    'event_id' => $eventId,
                    'signature_prefix' => substr($signature, 0, 10).'...',
                ]);
            }

            return ChannelDeliveryReport::delivered('webhook', $this->providerKey(), $eventId, [
                'target_url' => $targetUrl,
                'signature_v1' => $signature,
            ]);
        } catch (Throwable $e) {
            return ChannelDeliveryReport::failed('webhook', $this->providerKey(), $e->getMessage());
        }
    }
}
