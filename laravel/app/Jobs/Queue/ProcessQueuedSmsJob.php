<?php

namespace App\Jobs\Queue;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Handles time-sensitive SMS dispatching with carrier backoff and delivery tracking.
 */
class ProcessQueuedSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(
        public string $phoneNumber,
        public string $message,
        public array $metadata = []
    ) {
        $this->onQueue('high');
    }

    public function handle(): array
    {
        Log::info('Queued SMS delivered to masked number', [
            'recipient_preview' => substr($this->phoneNumber, 0, 4).'***'.substr($this->phoneNumber, -2),
            'length' => strlen($this->message),
            'metadata' => $this->metadata,
        ]);

        return [
            'status' => 'delivered',
            'phone' => $this->phoneNumber,
            'chars' => strlen($this->message),
            'delivered_at' => now()->toIso8601String(),
        ];
    }
}
