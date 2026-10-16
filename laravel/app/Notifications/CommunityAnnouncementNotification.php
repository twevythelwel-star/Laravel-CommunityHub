<?php

namespace App\Notifications;

use App\Models\InAppNotification;
use App\Models\Notification as AnnouncementModel;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Services\NotificationEngine\NotificationEngine;
use App\Services\NotificationEngine\NotificationPayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

class CommunityAnnouncementNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int> */
    public array $backoff = [15, 60, 300];

    public function __construct(
        public AnnouncementModel $announcement,
        public array $requestedChannels = ['in_app']
    ) {}

    /**
     * Determine which channels the notification should be sent through.
     * In-app is always the baseline.
     */
    public function via(mixed $notifiable): array
    {
        $channels = [];

        if (in_array('email', $this->requestedChannels, true) && ! empty($notifiable->email)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $communityName = $this->announcement->community?->name ?? config('app.name');
        $recipientName = $notifiable->display_name ?? $notifiable->name ?? 'Resident';

        $mail = (new MailMessage)
            ->subject("[{$communityName}] {$this->announcement->title}")
            ->greeting("Hello {$recipientName},")
            ->line($this->announcement->content)
            ->action('View in Community Hub', url('/dashboard/notifications'))
            ->salutation("Sincerely,\n{$communityName} Management");

        return $mail;
    }

    /**
     * Dispatch multi-channel notification for a specific recipient, recording outcomes.
     */
    public function deliver(User $recipient, NotificationEngine $engine): array
    {
        $outcomes = [];
        $title = $this->announcement->title;
        $content = $this->announcement->content;
        $actionUrl = '/dashboard/notifications';

        // 1. In-App Notification (Universal Baseline)
        if (in_array('in_app', $this->requestedChannels, true)) {
            try {
                $inApp = InAppNotification::create([
                    'user_id' => $recipient->id,
                    'tenant_id' => $this->announcement->community?->code,
                    'category' => 'announcement',
                    'title' => $title,
                    'body' => $content,
                    'action_url' => $actionUrl,
                    'priority' => 'normal',
                    'data' => [
                        'announcement_id' => $this->announcement->id,
                        'author' => $this->announcement->author_name,
                    ],
                ]);

                NotificationDelivery::create([
                    'notification_id' => $this->announcement->id,
                    'user_id' => $recipient->id,
                    'channel' => 'in_app',
                    'recipient' => (string) $recipient->id,
                    'provider' => 'internal',
                    'provider_message_id' => (string) $inApp->id,
                    'status' => 'delivered',
                    'sent_at' => now(),
                    'delivered_at' => now(),
                ]);

                $outcomes['in_app'] = 'delivered';
            } catch (Throwable $e) {
                Log::error("[CommunityAnnouncementNotification] In-app failed for user #{$recipient->id}: {$e->getMessage()}");
                $outcomes['in_app'] = 'failed';
            }
        }

        // 2. Email Delivery
        if (in_array('email', $this->requestedChannels, true)) {
            if ($engine->isChannelConfigured('email') && ! empty($recipient->email)) {
                $payload = new NotificationPayload(
                    title: $title,
                    body: $content,
                    recipient: $recipient->email,
                    user: $recipient,
                    actionUrl: $actionUrl
                );

                $result = $engine->channel('email')->send($payload);
                $status = $result->isSent() || $result->isDelivered() ? 'sent' : ($result->isUnavailable() ? 'unavailable' : 'failed');

                NotificationDelivery::create([
                    'notification_id' => $this->announcement->id,
                    'user_id' => $recipient->id,
                    'channel' => 'email',
                    'recipient' => $recipient->email,
                    'provider' => 'smtp',
                    'provider_message_id' => $result->reference,
                    'status' => $status,
                    'error_message' => $result->reason,
                    'sent_at' => now(),
                    'delivered_at' => $status === 'delivered' ? now() : null,
                ]);

                $outcomes['email'] = $status;
            } else {
                $outcomes['email'] = 'skipped_or_unconfigured';
            }
        }

        // 3. SMS Delivery
        if (in_array('sms', $this->requestedChannels, true)) {
            if ($engine->isChannelConfigured('sms') && ! empty($recipient->phone)) {
                $payload = new NotificationPayload(
                    title: $title,
                    body: $content,
                    recipient: $recipient->phone,
                    user: $recipient,
                    actionUrl: $actionUrl
                );

                $result = $engine->channel('sms')->send($payload);
                $status = $result->isSent() || $result->isDelivered() ? 'sent' : ($result->isUnavailable() ? 'unavailable' : 'failed');

                NotificationDelivery::create([
                    'notification_id' => $this->announcement->id,
                    'user_id' => $recipient->id,
                    'channel' => 'sms',
                    'recipient' => $recipient->phone,
                    'provider' => 'twilio',
                    'provider_message_id' => $result->reference,
                    'status' => $status,
                    'error_message' => $result->reason,
                    'sent_at' => now(),
                    'delivered_at' => $status === 'delivered' ? now() : null,
                ]);

                $outcomes['sms'] = $status;
            } else {
                $outcomes['sms'] = 'skipped_or_unconfigured';
            }
        }

        // 4. WhatsApp Delivery
        if (in_array('whatsapp', $this->requestedChannels, true)) {
            if ($engine->isChannelConfigured('whatsapp') && ! empty($recipient->phone)) {
                $payload = new NotificationPayload(
                    title: $title,
                    body: $content,
                    recipient: $recipient->phone,
                    user: $recipient,
                    actionUrl: $actionUrl
                );

                $result = $engine->channel('whatsapp')->send($payload);
                $status = $result->isSent() || $result->isDelivered() ? 'sent' : ($result->isUnavailable() ? 'unavailable' : 'failed');

                NotificationDelivery::create([
                    'notification_id' => $this->announcement->id,
                    'user_id' => $recipient->id,
                    'channel' => 'whatsapp',
                    'recipient' => $recipient->phone,
                    'provider' => 'twilio',
                    'provider_message_id' => $result->reference,
                    'status' => $status,
                    'error_message' => $result->reason,
                    'sent_at' => now(),
                    'delivered_at' => $status === 'delivered' ? now() : null,
                ]);

                $outcomes['whatsapp'] = $status;
            } else {
                $outcomes['whatsapp'] = 'skipped_or_unconfigured';
            }
        }

        return $outcomes;
    }
}
