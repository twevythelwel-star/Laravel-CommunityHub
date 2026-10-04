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

class MailgunEmailProvider implements EmailProviderInterface
{
    protected string $domain;

    protected string $secret;

    protected string $endpoint;

    protected string $fromAddress;

    protected string $fromName;

    public function __construct()
    {
        $this->domain = (string) config('notifications.providers.mailgun.domain', '');
        $this->secret = (string) config('notifications.providers.mailgun.secret', '');
        $this->endpoint = (string) config('notifications.providers.mailgun.endpoint', 'api.mailgun.net');
        $this->fromAddress = (string) config('notifications.providers.mailgun.from_address', 'notifications@communityhub.io');
        $this->fromName = (string) config('notifications.providers.mailgun.from_name', 'Community Hub Dispatcher');
    }

    public function providerKey(): string
    {
        return 'mailgun';
    }

    public function isConfigured(): bool
    {
        return ! empty($this->domain) && ! empty($this->secret) && ! str_starts_with($this->secret, 'key-mock');
    }

    public function sendEmail(NotificationRecipient $recipient, NotificationMessage $message): ChannelDeliveryReport
    {
        if (empty($recipient->email)) {
            return ChannelDeliveryReport::skipped('email', $this->providerKey(), 'Recipient email is missing.');
        }

        try {
            if ($this->isConfigured()) {
                $response = Http::asForm()
                    ->withBasicAuth('api', $this->secret)
                    ->timeout(8)
                    ->post("https://{$this->endpoint}/v3/{$this->domain}/messages", [
                        'from' => "{$this->fromName} <{$this->fromAddress}>",
                        'to' => $recipient->email,
                        'subject' => $message->title,
                        'text' => $message->body,
                    ]);

                if (! $response->successful()) {
                    return ChannelDeliveryReport::failed(
                        'email',
                        $this->providerKey(),
                        "Mailgun HTTP {$response->status()}: {$response->body()}"
                    );
                }

                $data = $response->json();
                $messageId = $data['id'] ?? 'mg_'.Str::random(24);

                return ChannelDeliveryReport::delivered('email', $this->providerKey(), $messageId);
            }

            // Simulated environment for local/staging/CI
            $mockMessageId = 'mg_sim_'.Str::random(24);
            Log::info("[UniversalNotifications:Mailgun] Simulated delivery to {$recipient->maskedEmail()}", [
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
