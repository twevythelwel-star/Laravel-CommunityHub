<?php

namespace App\Jobs\Queue;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Handles resource-intensive analytical and financial reports compilation in the background.
 */
class GenerateReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180;

    public int $tries = 2;

    public function __construct(
        public string $reportType,
        public array $parameters = [],
        public ?int $requestedByUserId = null
    ) {
        $this->onQueue('low');
    }

    public function handle(): array
    {
        Log::info("Generating report [{$this->reportType}] for user [{$this->requestedByUserId}]", [
            'params' => $this->parameters,
        ]);

        return [
            'status' => 'completed',
            'report_type' => $this->reportType,
            'rows_compiled' => rand(150, 1200),
            'generated_at' => now()->toIso8601String(),
        ];
    }
}
