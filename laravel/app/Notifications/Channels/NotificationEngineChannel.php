<?php

namespace App\Notifications\Channels;

use App\Models\NotificationDelivery;
use App\Models\User;
use App\Notifications\EngineNotification;
use App\Services\NotificationEngine\NotificationEngine;
use RuntimeException;

/**
 * Laravel notification channel that sends through the community NotificationEngine
 * (in-app, email, SMS, WhatsApp, push) and records each attempt in notification_deliveries.
 */
class NotificationEngineChannel
{
    public function __construct(private readonly NotificationEngine $engine) {}

    public function send(User $notifiable, EngineNotification $notification): void
    {
        $payload = $notification->toEngine($notifiable);
        $results = $this->engine->send($notification->engineChannels($notifiable), $payload);
        $subject = $notification->subject();

        foreach ($results as $channel => $result) {
            NotificationDelivery::create([
                'user_id' => $notifiable->id,
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
                'channel' => $channel,
                'recipient' => $channel === 'in_app' ? (string) $notifiable->id : (string) ($payload->recipient ?? ''),
                'provider' => $channel === 'in_app' ? 'internal' : $channel,
                'provider_message_id' => $result->reference,
                'status' => $result->status,
                'error_message' => $result->reason,
                'sent_at' => now(),
                'delivered_at' => $result->isDelivered() ? now() : null,
            ]);
        }

        // Nothing got through: fail the job so the queue retries it.
        if ($results !== [] && collect($results)->every->isFailed()) {
            throw new RuntimeException('Every channel failed for '.class_basename($notification)." to user #{$notifiable->id}.");
        }
    }
}
