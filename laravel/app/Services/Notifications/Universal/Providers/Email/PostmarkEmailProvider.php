<?php

namespace App\Services\Notifications\Universal\Providers\Email;

use App\Services\Notifications\Universal\Contracts\EmailProviderInterface;
use App\Services\Notifications\Universal\DTOs\ChannelDeliveryReport;
use App\Services\Notifications\Universal\DTOs\NotificationMessage;
use App\Services\Notifications\Universal\DTOs\NotificationRecipient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class PostmarkEmailProvider implements EmailProviderInterface
{
    protected string $token;

    protected string $fromAddress;

    protected string $fromName;

    public function __construct()
    {
        $this->token = (string) config('notifications.providers.postmark.token', '');
        $this->fromAddress = (string) config('notifications.providers.postmark.from_address', 'system@communityhub.io');
        $this->fromName = (string) config('notifications.providers.postmark.from_name', 'Community Hub Postmark');
    }

    public function providerKey(): string
    {
        return 'postmark';
    }

    public function isConfigured(): bool
    {
        return ! empty($this->token) && ! str_starts_with($this->token, 'mock');
    }

    public function sendEmail(NotificationRecipient $recipient, NotificationMessage $message): ChannelDeliveryReport
    {
        if (empty($recipient->email)) {
            return ChannelDeliveryReport::skipped('email', $this->providerKey(), 'Recipient email is missing.');
        }

        try {
            if ($this->isConfigured()) {
                $response = Http::withHeaders([
                    'X-Postmark-Server-Token' => $this->token,
                    'Accept' => 'application/json',
                ])
                    ->timeout(8)
                    ->post('https://api.postmarkapp.com/email', [
                        'From' => "{$this->fromName} <{$this->fromAddress}>",
                        'To' => $recipient->email,
                        'Subject' => $message->title,
                        'TextBody' => $message->body,
                    ]);

                if (! $response->successful()) {
                    return ChannelDeliveryReport::failed(
                        'email',
                        $this->providerKey(),
                        "Postmark HTTP {$response->status()}: {$response->body()}"
                    );
                }

                $data = $response->json();
                $messageId = $data['MessageID'] ?? 'pm_'.Str::random(24);

                return ChannelDeliveryReport::delivered('email', $this->providerKey(), $messageId);
            }

            // Simulated environment for local/staging/CI
            $mockMessageId = 'pm_sim_'.Str::random(24);
            Log::info("[UniversalNotifications:Postmark] Simulated delivery to {$recipient->maskedEmail()}", [
                'subject' => $message->title,
                'message_id' => $mockMessageId,
            ]);

            return ChannelDeliveryReport::delivered('email', $this->providerKey(), $mockMessageId, [
                'simulated' => true,
            ]);
        } catch (Throwable $e) {
            return ChannelDeliveryReport::failed('email', $this->providerKey(), $e->getMessage());
        }
    }
}
