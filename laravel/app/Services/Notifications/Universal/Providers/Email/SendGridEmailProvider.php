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

class SendGridEmailProvider implements EmailProviderInterface
{
    protected string $apiKey;

    protected string $fromAddress;

    protected string $fromName;

    public function __construct()
    {
        $this->apiKey = (string) config('notifications.providers.sendgrid.api_key', '');
        $this->fromAddress = (string) config('notifications.providers.sendgrid.from_address', 'alerts@communityhub.io');
        $this->fromName = (string) config('notifications.providers.sendgrid.from_name', 'Community Hub');
    }

    public function providerKey(): string
    {
        return 'sendgrid';
    }

    public function isConfigured(): bool
    {
        return ! empty($this->apiKey) && ! str_starts_with($this->apiKey, 'mock_');
    }

    public function sendEmail(NotificationRecipient $recipient, NotificationMessage $message): ChannelDeliveryReport
    {
        if (empty($recipient->email)) {
            return ChannelDeliveryReport::skipped('email', $this->providerKey(), 'Recipient email is missing.');
        }

        try {
            if ($this->isConfigured()) {
                $response = Http::withToken($this->apiKey)
                    ->timeout(8)
                    ->post('https://api.sendgrid.com/v3/mail/send', [
                        'personalizations' => [
                            [
                                'to' => [['email' => $recipient->email, 'name' => $recipient->name]],
                                'subject' => $message->title,
                            ],
                        ],
                        'from' => ['email' => $this->fromAddress, 'name' => $this->fromName],
                        'content' => [
                            [
                                'type' => 'text/plain',
                                'value' => $message->body,
                            ],
                        ],
                    ]);

                if (! $response->successful()) {
                    return ChannelDeliveryReport::failed(
                        'email',
                        $this->providerKey(),
                        "SendGrid HTTP {$response->status()}: {$response->body()}"
                    );
                }

                $messageId = $response->header('X-Message-Id') ?: 'sg_'.Str::random(24);

                return ChannelDeliveryReport::delivered('email', $this->providerKey(), $messageId);
            }

            // Simulated environment for local/staging/CI
            $mockMessageId = 'sg_sim_'.Str::random(24);
            Log::info("[UniversalNotifications:SendGrid] Simulated delivery to {$recipient->maskedEmail()}", [
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
