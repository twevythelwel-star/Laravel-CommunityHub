<?php

declare(strict_types=1);

namespace App\Livewire\Observability;

use App\Services\Observability\ObservabilityService;
use Exception;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Throwable;

class ObservabilityHub extends Component
{
    public string $activeTab = 'overview'; // overview, health, performance, logs, audit, errors, queues

    public string $logFilterLevel = '';

    public string $logSearch = '';

    public string $testLogMessage = 'Operator verified observability stream integrity';

    public string $testLogLevel = 'info';

    public string $simulatedErrorMessage = 'Simulated gatehouse network timeout in telemetry stream';

    public string $simulatedErrorSeverity = 'error';

    public string $auditActionText = 'Security officer modified perimeter boundary parameters';

    public ?string $feedbackMessage = null;

    /**
     * System Admin only: the application and audit logs hold residents'
     * personal data, and this page writes audit entries. boot() runs on the
     * first load and on every action request.
     */
    public function boot(): void
    {
        $this->authorize('operatePlatform');
    }

    public function selectTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function runHealthProbes(ObservabilityService $service): void
    {
        $checks = $service->getHealthChecks();
        $this->feedbackMessage = 'Executed deep subsystem health probes across '.count($checks).' core subsystems.';
    }

    public function writeTestLog(ObservabilityService $service): void
    {
        // Browser-settable: Log::log() throws on a level it does not know.
        $this->validate([
            'testLogLevel' => ['required', 'in:debug,info,notice,warning,error,critical,alert,emergency'],
            'testLogMessage' => ['nullable', 'string', 'max:500'],
        ]);

        $message = trim($this->testLogMessage) ?: 'Manual diagnostic log entry';
        $level = $this->testLogLevel;

        $service->logApplicationEvent($level, $message, [
            'author' => auth()->user()->name,
            'tab' => 'ObservabilityHub',
        ]);

        $this->feedbackMessage = "Application log event recorded at level [{$level}].";
    }

    public function recordTestAudit(ObservabilityService $service): void
    {
        $action = trim($this->auditActionText) ?: 'Manual administrative audit action recorded';

        $service->recordAudit($action, auth()->user(), [
            'module' => 'ObservabilityHub',
            'action_type' => 'manual_test',
        ]);

        $this->feedbackMessage = 'Audit trail record successfully written.';
    }

    public function simulateTrackedError(ObservabilityService $service): void
    {
        $message = trim($this->simulatedErrorMessage) ?: 'Simulated telemetry anomaly';
        $severity = $this->simulatedErrorSeverity ?: 'error';

        try {
            throw new Exception($message);
        } catch (Throwable $e) {
            $tracked = $service->trackException($e, [
                'triggered_by' => auth()->user()->name,
                'context' => 'ObservabilityHub Simulation',
            ], $severity);

            $this->feedbackMessage = "Simulated exception recorded in ErrorTracker (Fingerprint: {$tracked->fingerprint}, Count: {$tracked->occurrences_count}).";
        }
    }

    public function resolveTrackedError(int $id, ObservabilityService $service): void
    {
        $resolved = $service->resolveError($id, auth()->user());
        if ($resolved) {
            $this->feedbackMessage = "Tracked error #{$id} marked as resolved.";
        }
    }

    public function render(ObservabilityService $service): View
    {
        $status = $service->getSystemStatus();
        $health = $service->getHealthChecks();
        $performance = $service->getPerformanceMetrics();
        $logs = $service->getApplicationLogs(
            $this->logFilterLevel ?: null,
            $this->logSearch ?: null,
            30
        );
        $audits = $service->getAuditLogs(null, null, 25);
        $errors = $service->getErrorTrackingMetrics(20);
        $queues = $service->getQueueMetrics();

        return view('livewire.observability.observability-hub', [
            'systemStatus' => $status,
            'healthChecks' => $health,
            'performanceMetrics' => $performance,
            'applicationLogs' => $logs,
            'auditLogs' => $audits,
            'errorMetrics' => $errors,
            'queueMetrics' => $queues,
        ])->layout('layouts.app', ['header' => 'Observability & Monitoring Hub']);
    }
}
