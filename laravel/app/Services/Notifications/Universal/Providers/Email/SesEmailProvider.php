<?php

namespace App\Services\Notifications\Universal\Providers\Email;

use App\Services\Notifications\Universal\Contracts\EmailProviderInterface;
use App\Services\Notifications\Universal\DTOs\ChannelDeliveryReport;
use App\Services\Notifications\Universal\DTOs\NotificationMessage;
use App\Services\Notifications\Universal\DTOs\NotificationRecipient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class SesEmailProvider implements EmailProviderInterface
{
    protected string $key;

    protected string $secret;

    protected string $region;

    protected string $fromAddress;

    protected string $fromName;

    public function __construct()
    {
        $this->key = (string) config('notifications.providers.ses.key', '');
        $this->secret = (string) config('notifications.providers.ses.secret', '');
        $this->region = (string) config('notifications.providers.ses.region', 'us-east-1');
        $this->fromAddress = (string) config('notifications.providers.ses.from_address', 'ses@communityhub.io');
        $this->fromName = (string) config('notifications.providers.ses.from_name', 'Community Hub SES');
    }

    public function providerKey(): string
    {
        return 'ses';
    }

    public function isConfigured(): bool
    {
        return ! empty($this->key) && ! empty($this->secret) && ! str_starts_with($this->key, 'mock');
    }

    public function sendEmail(NotificationRecipient $recipient, NotificationMessage $message): ChannelDeliveryReport
    {
        if (empty($recipient->email)) {
            return ChannelDeliveryReport::skipped('email', $this->providerKey(), 'Recipient email is missing.');
        }

        try {
            // Simulated / Mocked SES delivery
            $messageId = 'ses_'.Str::random(24);
            Log::info("[UniversalNotifications:SES] Delivery to {$recipient->maskedEmail()}", [
                'region' => $this->region,
                'subject' => $message->title,
                'message_id' => $messageId,
            ]);

            return ChannelDeliveryReport::delivered('email', $this->providerKey(), $messageId, [
                'region' => $this->region,
                'configured' => $this->isConfigured(),
            ]);
        } catch (Throwable $e) {
            return ChannelDeliveryReport::failed('email', $this->providerKey(), $e->getMessage());
        }
    }
}
