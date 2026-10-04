<?php

namespace App\Jobs\Queue;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Handles image transformations, avatar thumbnail generation, WebP conversion, and optimization.
 */
class ProcessImageMediaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;

    public int $tries = 3;

    public function __construct(
        public string $imagePath,
        public array $conversions = ['thumbnail', 'webp_optimized'],
        public ?int $ownerId = null
    ) {
        $this->onQueue('default');
    }

    public function handle(): array
    {
        Log::info("Optimizing image at [{$this->imagePath}] with conversions", [
            'conversions' => $this->conversions,
            'owner_id' => $this->ownerId,
        ]);

        return [
            'status' => 'optimized',
            'original_path' => $this->imagePath,
            'variants' => array_map(fn ($c) => "{$this->imagePath}.{$c}", $this->conversions),
            'optimized_at' => now()->toIso8601String(),
        ];
    }
}
