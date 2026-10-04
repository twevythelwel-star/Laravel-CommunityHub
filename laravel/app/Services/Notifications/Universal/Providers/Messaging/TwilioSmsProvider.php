<?php

namespace App\Services\Notifications\Universal\Providers\Messaging;

use App\Services\Notifications\Universal\Contracts\SmsProviderInterface;
use App\Services\Notifications\Universal\DTOs\ChannelDeliveryReport;
use App\Services\Notifications\Universal\DTOs\NotificationMessage;
use App\Services\Notifications\Universal\DTOs\NotificationRecipient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class TwilioSmsProvider implements SmsProviderInterface
{
    protected string $sid;

    protected string $token;

    protected string $from;

    public function __construct()
    {
        $this->sid = (string) config('notifications.providers.twilio.sid', '');
        $this->token = (string) config('notifications.providers.twilio.token', '');
        $this->from = (string) config('notifications.providers.twilio.from', '');
    }

    public function providerKey(): string
    {
        return 'twilio';
    }

    public function isConfigured(): bool
    {
        return ! empty($this->sid) && ! empty($this->token) && ! str_starts_with($this->sid, 'AC_mock');
    }

    public function sendSms(NotificationRecipient $recipient, NotificationMessage $message): ChannelDeliveryReport
    {
        if (empty($recipient->phone)) {
            return ChannelDeliveryReport::skipped('sms', $this->providerKey(), 'Recipient phone number is missing.');
        }

        try {
            if ($this->isConfigured() && ! app()->environment('testing')) {
                $response = Http::asForm()
                    ->withBasicAuth($this->sid, $this->token)
                    ->timeout(8)
                    ->post("https://api.twilio.com/2010-04-01/Accounts/{$this->sid}/Messages.json", [
                        'From' => $this->from,
                        'To' => $recipient->phone,
                        'Body' => "{$message->title}: {$message->body}",
                    ]);

                if (! $response->successful()) {
                    return ChannelDeliveryReport::failed(
                        'sms',
                        $this->providerKey(),
                        "Twilio SMS HTTP {$response->status()}: {$response->body()}"
                    );
                }

                $data = $response->json();
                $messageId = $data['sid'] ?? 'SM'.Str::random(32);

                return ChannelDeliveryReport::delivered('sms', $this->providerKey(), $messageId);
            }

            // Simulated environment for local/staging/CI
            $mockMessageId = 'SM_sim_'.Str::random(32);
            Log::info("[UniversalNotifications:TwilioSMS] Simulated SMS to {$recipient->maskedPhone()}", [
                'message_id' => $mockMessageId,
            ]);

            return ChannelDeliveryReport::delivered('sms', $this->providerKey(), $mockMessageId, [
                'simulated' => true,
            ]);
        } catch (Throwable $e) {
            return ChannelDeliveryReport::failed('sms', $this->providerKey(), $e->getMessage());
        }
    }
}
