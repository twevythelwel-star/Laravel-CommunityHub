<?php

namespace App\Services\Notifications\Universal\Channels;

use App\Models\InAppNotification;
use App\Services\Notifications\Universal\DTOs\ChannelDeliveryReport;
use App\Services\Notifications\Universal\DTOs\NotificationMessage;
use App\Services\Notifications\Universal\DTOs\NotificationRecipient;
use Throwable;

class DatabaseNotificationChannel extends AbstractNotificationChannel
{
    public function key(): string
    {
        return 'database';
    }

    public function label(): string
    {
        return 'Database Storage';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function send(NotificationRecipient $recipient, NotificationMessage $message): ChannelDeliveryReport
    {
        try {
            $notification = InAppNotification::create([
                'user_id' => $recipient->userId,
                'tenant_id' => $recipient->tenantId,
                'category' => $message->category,
                'title' => $message->title,
                'body' => $message->body,
                'action_url' => $message->actionUrl,
                'priority' => $message->priority,
                'data' => $message->data,
                'read_at' => null,
            ]);

            return ChannelDeliveryReport::delivered(
                $this->key(),
                $this->activeProviderName(),
                (string) $notification->id,
                ['notification_id' => $notification->id]
            );
        } catch (Throwable $e) {
            return ChannelDeliveryReport::failed($this->key(), $this->activeProviderName(), $e->getMessage());
        }
    }
}
