<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Observability\ObservabilityHub;
use App\Models\User;
use App\Services\Observability\ObservabilityService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ObservabilityModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_observability_service_provides_system_status_and_health_checks(): void
    {
        $service = app(ObservabilityService::class);

        $status = $service->getSystemStatus();
        $this->assertArrayHasKey('status', $status);
        $this->assertArrayHasKey('is_healthy', $status);
        $this->assertArrayHasKey('environment', $status);
        $this->assertArrayHasKey('subsystem_counts', $status);
        $this->assertGreaterThanOrEqual(1, $status['subsystem_counts']['total']);

        $health = $service->getHealthChecks();
        $this->assertArrayHasKey('database', $health);
        $this->assertArrayHasKey('cache', $health);
        $this->assertArrayHasKey('storage', $health);
        $this->assertArrayHasKey('queue', $health);
        $this->assertArrayHasKey('reverb', $health);
        $this->assertArrayHasKey('octane', $health);

        $this->assertSame('healthy', $health['database']['status']);
        $this->assertSame('healthy', $health['storage']['status']);
        $this->assertArrayHasKey('latency_ms', $health['database']);
    }

    public function test_observability_service_aggregates_performance_and_queue_metrics(): void
    {
        $service = app(ObservabilityService::class);

        $performance = $service->getPerformanceMetrics();
        $this->assertArrayHasKey('latency', $performance);
        $this->assertArrayHasKey('average_ms', $performance['latency']);
        $this->assertArrayHasKey('p95_ms', $performance['latency']);
        $this->assertArrayHasKey('memory', $performance);
        $this->assertArrayHasKey('current_mb', $performance['memory']);
        $this->assertArrayHasKey('requests_24h', $performance);
        $this->assertArrayHasKey('cache', $performance);

        $queues = $service->getQueueMetrics();
        $this->assertArrayHasKey('queue_summary', $queues);
        $this->assertArrayHasKey('horizon', $queues);
        $this->assertArrayHasKey('total_pending', $queues['queue_summary']);
    }

    public function test_observability_application_logs_retrieval_and_writing(): void
    {
        $service = app(ObservabilityService::class);

        $service->logApplicationEvent('warning', 'Observability probe detected simulated edge load.', [
            'probe_id' => 'probe_991',
        ]);

        $logs = $service->getApplicationLogs();
        $this->assertIsArray($logs);
        $this->assertNotEmpty($logs);
        $this->assertArrayHasKey('message', $logs[0]);
        $this->assertArrayHasKey('level', $logs[0]);
        $this->assertArrayHasKey('timestamp', $logs[0]);
    }

    public function test_observability_audit_logs_recording_and_retrieval(): void
    {
        $service = app(ObservabilityService::class);
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'name' => 'Auditor Chief',
        ]);

        $entry = $service->recordAudit('Officer updated boundary surveillance', $admin, [
            'zone' => 'North Gate',
            'ip' => '10.0.0.15',
        ]);

        $auditLogs = $service->getAuditLogs('surveillance');
        $this->assertIsArray($auditLogs);
        $this->assertNotEmpty($auditLogs);
        $this->assertStringContainsString('surveillance', $auditLogs[0]['action']);
    }

    public function test_observability_error_tracker_lifecycle_fingerprinting_and_resolution(): void
    {
        $service = app(ObservabilityService::class);
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'name' => 'Site Reliability Engineer',
        ]);

        $exception = new Exception('Simulated test database timeout error', 504);

        // 1. First occurrence
        $tracked = $service->trackException($exception, ['route' => '/api/v1/gate-passes'], 'critical');

        $this->assertNotNull($tracked->id);
        $this->assertSame(1, $tracked->occurrences_count);
        $this->assertSame('critical', $tracked->severity);
        $this->assertSame('unresolved', $tracked->status);
        $this->assertNotEmpty($tracked->fingerprint);

        // 2. Second occurrence of same error -> increment occurrences count
        $trackedAgain = $service->trackException($exception, ['route' => '/api/v1/gate-passes'], 'critical');
        $this->assertSame($tracked->id, $trackedAgain->id);
        $this->assertSame(2, $trackedAgain->occurrences_count);

        // 3. Check error metrics
        $metrics = $service->getErrorTrackingMetrics();
        $this->assertGreaterThanOrEqual(1, $metrics['total_tracked']);
        $this->assertGreaterThanOrEqual(1, $metrics['critical_count']);

        // 4. Resolve the error
        $resolved = $service->resolveError($tracked->id, $admin);
        $this->assertSame('resolved', $resolved->status);
        $this->assertSame($admin->id, $resolved->resolved_by);
        $this->assertNotNull($resolved->resolved_at);
    }

    public function test_observability_api_endpoints_return_successful_responses(): void
    {
        $this->actingAs(User::factory()->role(UserRole::SystemAdmin)->create()); // these endpoints are operatePlatform-only
        // 1. Status overview
        $statusRes = $this->getJson('/api/v1/observability/status');
        $statusRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.system_status.is_healthy', true);

        // 2. Subsystems health
        $healthRes = $this->getJson('/api/v1/observability/health');
        $healthRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.subsystems.database.status', 'healthy');

        // 3. Performance metrics
        $perfRes = $this->getJson('/api/v1/observability/performance');
        $perfRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.cache.status', 'operational');

        // 4. Logs endpoint
        $logsRes = $this->getJson('/api/v1/observability/logs');
        $logsRes->assertStatus(200)
            ->assertJsonPath('success', true);

        // 5. Audit logs endpoint
        $auditRes = $this->getJson('/api/v1/observability/audit-logs');
        $auditRes->assertStatus(200)
            ->assertJsonPath('success', true);

        // 6. Errors endpoint
        $errorsRes = $this->getJson('/api/v1/observability/errors');
        $errorsRes->assertStatus(200)
            ->assertJsonPath('success', true);

        // 7. Queue metrics endpoint
        $queueRes = $this->getJson('/api/v1/observability/queue-metrics');
        $queueRes->assertStatus(200)
            ->assertJsonPath('success', true);

        // 8. Simulate error endpoint
        $simRes = $this->postJson('/api/v1/observability/simulate-error', [
            'message' => 'API simulated exception for telemetry verification',
            'severity' => 'warning',
        ]);
        $simRes->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.severity', 'warning')
            ->assertJsonPath('data.status', 'unresolved');

        // 9. Write audit endpoint
        $writeAuditRes = $this->postJson('/api/v1/observability/write-audit', [
            'action' => 'Operator authenticated token via API',
        ]);
        $writeAuditRes->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_livewire_observability_hub_renders_and_executes_actions(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::SystemAdmin,
            'name' => 'Monitoring Operator',
        ]);

        Livewire::actingAs($admin)
            ->test(ObservabilityHub::class)
            ->assertSee('Enterprise Observability Hub')
            ->assertSee('System Overview')
            ->call('selectTab', 'health')
            ->assertSee('Subsystem Health Diagnostics')
            ->call('runHealthProbes')
            ->assertSee('Executed deep subsystem health probes')
            ->call('selectTab', 'performance')
            ->assertSee('Performance Telemetry')
            ->call('selectTab', 'logs')
            ->assertSee('Structured Application Logs')
            ->call('writeTestLog')
            ->assertSee('Application log event recorded')
            ->call('selectTab', 'audit')
            ->assertSee('Enterprise Audit Trail')
            ->call('recordTestAudit')
            ->assertSee('Audit trail record successfully written')
            ->call('selectTab', 'errors')
            ->assertSee('Proactive Error Tracking')
            ->call('simulateTrackedError')
            ->assertSee('Simulated exception recorded in ErrorTracker')
            ->call('selectTab', 'queues')
            ->assertSee('Queue & Horizon Infrastructure Telemetry');
    }

    public function test_web_route_for_observability_hub_is_accessible(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::SystemAdmin,
        ]);

        $response = $this->actingAs($admin)->get('/operations/observability');
        $response->assertStatus(200);
    }
}
