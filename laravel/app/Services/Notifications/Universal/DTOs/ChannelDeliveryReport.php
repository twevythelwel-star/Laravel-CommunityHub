<?php

namespace App\Services\Notifications\Universal\DTOs;

use Carbon\Carbon;

class ChannelDeliveryReport
{
    public function __construct(
        public string $channel,
        public string $provider,
        public string $status, // delivered, queued, skipped, failed
        public ?string $messageId = null,
        public ?string $error = null,
        public ?Carbon $sentAt = null,
        public array $metadata = []
    ) {
        $this->sentAt = $this->sentAt ?? now();
    }

    public static function delivered(
        string $channel,
        string $provider,
        ?string $messageId = null,
        array $metadata = []
    ): self {
        return new self(
            channel: $channel,
            provider: $provider,
            status: 'delivered',
            messageId: $messageId,
            metadata: $metadata
        );
    }

    public static function queued(
        string $channel,
        string $provider,
        ?string $messageId = null,
        array $metadata = []
    ): self {
        return new self(
            channel: $channel,
            provider: $provider,
            status: 'queued',
            messageId: $messageId,
            metadata: $metadata
        );
    }

    public static function skipped(
        string $channel,
        string $provider,
        string $reason,
        array $metadata = []
    ): self {
        return new self(
            channel: $channel,
            provider: $provider,
            status: 'skipped',
            error: $reason,
            metadata: $metadata
        );
    }

    public static function failed(
        string $channel,
        string $provider,
        string $error,
        array $metadata = []
    ): self {
        return new self(
            channel: $channel,
            provider: $provider,
            status: 'failed',
            error: $error,
            metadata: $metadata
        );
    }

    public function isSuccess(): bool
    {
        return in_array($this->status, ['delivered', 'queued'], true);
    }

    public function toArray(): array
    {
        return [
            'channel' => $this->channel,
            'provider' => $this->provider,
            'status' => $this->status,
            'message_id' => $this->messageId,
            'error' => $this->error,
            'sent_at' => $this->sentAt?->toISOString(),
            'metadata' => $this->metadata,
        ];
    }
}
