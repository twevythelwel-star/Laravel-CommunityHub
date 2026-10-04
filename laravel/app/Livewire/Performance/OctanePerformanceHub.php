<?php

namespace App\Livewire\Performance;

use App\Services\Performance\OctanePerformanceService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class OctanePerformanceHub extends Component
{
    /**
     * System Admin only: the benchmark loads the server on demand. boot()
     * runs on the first load and on every action request.
     */
    public function boot(): void
    {
        $this->authorize('operatePlatform');
    }

    public string $activeTab = 'servers'; // servers, concurrency, telemetry, suitability

    // Concurrency benchmark state
    public int $concurrencyTaskCount = 4;

    public ?array $benchmarkResult = null;

    public bool $isBenchmarking = false;

    // Server selection simulator
    public string $selectedServer = 'frankenphp';

    public ?string $feedbackMessage = null;

    public function mount(OctanePerformanceService $service): void
    {
        $this->selectedServer = config('octane.server', 'frankenphp');
    }

    public function selectTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function selectServer(string $server): void
    {
        $this->selectedServer = $server;
        config(['octane.server' => $server]);
        $this->feedbackMessage = "Active Octane application server runtime switched to [{$server}]!";
    }

    public function runConcurrencyBenchmark(OctanePerformanceService $service): void
    {
        $this->isBenchmarking = true;

        $tasks = [];
        for ($i = 1; $i <= $this->concurrencyTaskCount; $i++) {
            $taskName = "Worker Thread #{$i}";
            $tasks["task_{$i}"] = function () use ($taskName, $i) {
                // Simulate compute or async I/O
                usleep(12000 + ($i * 1000));

                return [
                    'thread' => $taskName,
                    'status' => 'success',
                    'timestamp' => microtime(true),
                    'digest' => hash('crc32b', $taskName.microtime(true)),
                ];
            };
        }

        $this->benchmarkResult = $service->runConcurrently($tasks);
        $this->isBenchmarking = false;
        $this->feedbackMessage = "Executed {$this->concurrencyTaskCount} concurrent tasks in {$this->benchmarkResult['elapsed_ms']}ms via [{$this->benchmarkResult['execution_mode']}].";
    }

    public function render(OctanePerformanceService $service): View
    {
        $servers = $service->getApplicationServers();
        $metrics = $service->getRuntimeMetrics();
        $suitability = $service->getWorkloadSuitabilityAnalysis();

        return view('livewire.performance.octane-performance-hub', [
            'servers' => $servers,
            'metrics' => $metrics,
            'suitability' => $suitability,
        ])->layout('layouts.app');
    }
}
