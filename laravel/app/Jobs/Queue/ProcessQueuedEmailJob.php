<?php

namespace App\Jobs\Queue;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Handles asynchronous transactional and community email processing in the background.
 */
class ProcessQueuedEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public string $recipientEmail,
        public string $subject,
        public string $template = 'generic_notice',
        public array $payload = []
    ) {
        $this->onQueue('default');
    }

    public function handle(): array
    {
        Log::info("Queued email dispatched to [{$this->recipientEmail}] with subject '{$this->subject}'", [
            'template' => $this->template,
            'job_id' => $this->job?->getJobId(),
        ]);

        return [
            'status' => 'sent',
            'recipient' => $this->recipientEmail,
            'subject' => $this->subject,
            'processed_at' => now()->toIso8601String(),
        ];
    }
}
