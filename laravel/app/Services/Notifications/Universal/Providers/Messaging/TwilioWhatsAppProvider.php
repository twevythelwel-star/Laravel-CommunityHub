<?php

namespace App\Services\Notifications\Universal\Providers\Messaging;

use App\Services\Notifications\Universal\Contracts\WhatsAppProviderInterface;
use App\Services\Notifications\Universal\DTOs\ChannelDeliveryReport;
use App\Services\Notifications\Universal\DTOs\NotificationMessage;
use App\Services\Notifications\Universal\DTOs\NotificationRecipient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class TwilioWhatsAppProvider implements WhatsAppProviderInterface
{
    protected string $sid;

    protected string $token;

    protected string $whatsappFrom;

    public function __construct()
    {
        $this->sid = (string) config('notifications.providers.twilio.sid', '');
        $this->token = (string) config('notifications.providers.twilio.token', '');
        $this->whatsappFrom = (string) config('notifications.providers.twilio.whatsapp_from', 'whatsapp:+14155238886');
    }

    public function providerKey(): string
    {
        return 'twilio';
    }

    public function isConfigured(): bool
    {
        return ! empty($this->sid) && ! empty($this->token) && ! str_starts_with($this->sid, 'AC_mock');
    }

    public function sendWhatsApp(NotificationRecipient $recipient, NotificationMessage $message): ChannelDeliveryReport
    {
        if (empty($recipient->phone)) {
            return ChannelDeliveryReport::skipped('whatsapp', $this->providerKey(), 'Recipient phone number is missing.');
        }

        $formattedTo = str_starts_with($recipient->phone, 'whatsapp:')
            ? $recipient->phone
            : 'whatsapp:'.$recipient->phone;

        try {
            if ($this->isConfigured() && ! app()->environment('testing')) {
                $response = Http::asForm()
                    ->withBasicAuth($this->sid, $this->token)
                    ->timeout(8)
                    ->post("https://api.twilio.com/2010-04-01/Accounts/{$this->sid}/Messages.json", [
                        'From' => $this->whatsappFrom,
                        'To' => $formattedTo,
                        'Body' => "*{$message->title}*\n\n{$message->body}",
                    ]);

                if (! $response->successful()) {
                    return ChannelDeliveryReport::failed(
                        'whatsapp',
                        $this->providerKey(),
                        "Twilio WhatsApp HTTP {$response->status()}: {$response->body()}"
                    );
                }

                $data = $response->json();
                $messageId = $data['sid'] ?? 'WA'.Str::random(32);

                return ChannelDeliveryReport::delivered('whatsapp', $this->providerKey(), $messageId);
            }

            // Simulated environment for local/staging/CI
            $mockMessageId = 'WA_sim_'.Str::random(32);
            Log::info("[UniversalNotifications:TwilioWhatsApp] Simulated WhatsApp to {$recipient->maskedPhone()}", [
                'message_id' => $mockMessageId,
            ]);

            return ChannelDeliveryReport::delivered('whatsapp', $this->providerKey(), $mockMessageId, [
                'simulated' => true,
            ]);
        } catch (Throwable $e) {
            return ChannelDeliveryReport::failed('whatsapp', $this->providerKey(), $e->getMessage());
        }
    }
}
