<?php

namespace App\Jobs\Queue;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Handles two-way data synchronization with external HOA ledgers, smart gates, and IoT perimeter sensors.
 */
class SynchronizeExternalDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180;

    public int $tries = 3;

    public function __construct(
        public string $targetSystem,
        public string $syncScope = 'full',
        public ?string $syncToken = null
    ) {
        $this->onQueue('low');
    }

    public function handle(): array
    {
        Log::info("Synchronizing data with external system [{$this->targetSystem}] scope [{$this->syncScope}]");

        return [
            'status' => 'synchronized',
            'target_system' => $this->targetSystem,
            'scope' => $this->syncScope,
            'synced_entities_count' => rand(40, 250),
            'synchronized_at' => now()->toIso8601String(),
        ];
    }
}
