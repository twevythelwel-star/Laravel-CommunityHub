<?php

namespace App\Services\Notifications\Universal\DTOs;

use Illuminate\Support\Collection;

class NotificationDispatchSummary
{
    /**
     * @param  Collection<string, ChannelDeliveryReport>|array<string, ChannelDeliveryReport>  $reports
     */
    public function __construct(
        public string $trackingId,
        public NotificationRecipient $recipient,
        public NotificationMessage $message,
        public Collection|array $reports
    ) {
        if (is_array($this->reports)) {
            $this->reports = collect($this->reports);
        }
    }

    public function isDelivered(string $channel): bool
    {
        $report = $this->reports->get($channel);

        return $report !== null && $report->isSuccess();
    }

    public function successfulChannels(): array
    {
        return $this->reports
            ->filter(fn (ChannelDeliveryReport $r) => $r->isSuccess())
            ->keys()
            ->values()
            ->all();
    }

    public function failedChannels(): array
    {
        return $this->reports
            ->filter(fn (ChannelDeliveryReport $r) => ! $r->isSuccess())
            ->keys()
            ->values()
            ->all();
    }

    public function hasAnySuccess(): bool
    {
        return count($this->successfulChannels()) > 0;
    }

    public function toArray(): array
    {
        return [
            'tracking_id' => $this->trackingId,
            'recipient' => [
                'user_id' => $this->recipient->userId,
                'email' => $this->recipient->maskedEmail(),
                'phone' => $this->recipient->maskedPhone(),
            ],
            'message' => [
                'title' => $this->message->title,
                'category' => $this->message->category,
                'priority' => $this->message->priority,
            ],
            'successful_channels' => $this->successfulChannels(),
            'failed_channels' => $this->failedChannels(),
            'reports' => $this->reports->map(fn (ChannelDeliveryReport $r) => $r->toArray())->all(),
        ];
    }
}
