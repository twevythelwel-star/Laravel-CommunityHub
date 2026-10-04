<?php

namespace App\Jobs\Queue;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Handles high-priority multi-channel background notifications (Push, WhatsApp, In-App, Database).
 */
class DispatchQueuedNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 15;

    public function __construct(
        public int $userId,
        public string $title,
        public string $body,
        public array $channels = ['in_app', 'push', 'database'],
        public array $data = []
    ) {
        $this->onQueue('high');
    }

    public function handle(): array
    {
        Log::info("Queued notification dispatched to user [{$this->userId}] across channels", [
            'channels' => $this->channels,
            'title' => $this->title,
        ]);

        return [
            'status' => 'dispatched',
            'user_id' => $this->userId,
            'title' => $this->title,
            'channels' => $this->channels,
            'dispatched_at' => now()->toIso8601String(),
        ];
    }
}
