<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Performance\OctanePerformanceHub;
use App\Models\User;
use App\Services\Performance\OctanePerformanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LaravelOctanePerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_octane_configuration_is_structured_with_supported_application_servers(): void
    {
        $server = config('octane.server');
        $this->assertContains($server, ['frankenphp', 'swoole', 'roadrunner']);

        $tables = config('octane.tables');
        $this->assertIsArray($tables);
        $this->assertArrayHasKey('gate_passes:5000', $tables);
        $this->assertArrayHasKey('rfid_tags:5000', $tables);

        $listeners = config('octane.listeners');
        $this->assertIsArray($listeners);
        $this->assertNotEmpty($listeners);
    }

    public function test_octane_service_provides_detailed_server_specifications(): void
    {
        $service = app(OctanePerformanceService::class);
        $servers = $service->getApplicationServers();

        $this->assertCount(3, $servers);
        $this->assertArrayHasKey('frankenphp', $servers);
        $this->assertArrayHasKey('swoole', $servers);
        $this->assertArrayHasKey('roadrunner', $servers);

        $this->assertSame('FrankenPHP', $servers['frankenphp']['name']);
        $this->assertStringContainsString('Caddy', $servers['frankenphp']['language']);
        $this->assertNotEmpty($servers['frankenphp']['key_features']);

        $this->assertSame('Swoole / OpenSwoole', $servers['swoole']['name']);
        $this->assertNotEmpty($servers['swoole']['key_features']);

        $this->assertSame('RoadRunner', $servers['roadrunner']['name']);
        $this->assertNotEmpty($servers['roadrunner']['key_features']);
    }

    public function test_octane_service_returns_runtime_metrics_and_memory_telemetry(): void
    {
        $service = app(OctanePerformanceService::class);
        $metrics = $service->getRuntimeMetrics();

        $this->assertTrue($metrics['octane_installed']);
        $this->assertArrayHasKey('memory_used_mb', $metrics);
        $this->assertArrayHasKey('memory_peak_mb', $metrics);
        $this->assertArrayHasKey('garbage_threshold_mb', $metrics);
        $this->assertArrayHasKey('tables_configured', $metrics);
        $this->assertGreaterThanOrEqual(1, count($metrics['tables_configured']));
    }

    public function test_octane_concurrency_execution_evaluates_tasks(): void
    {
        $service = app(OctanePerformanceService::class);

        $tasks = [
            'math' => fn () => 40 + 2,
            'string' => fn () => strtoupper('octane'),
            'hash' => fn () => md5('secret'),
        ];

        $response = $service->runConcurrently($tasks);

        $this->assertCount(3, $response['results']);
        $this->assertSame(42, $response['results']['math']);
        $this->assertSame('OCTANE', $response['results']['string']);
        $this->assertSame(md5('secret'), $response['results']['hash']);
        $this->assertArrayHasKey('elapsed_ms', $response);
        $this->assertArrayHasKey('execution_mode', $response);
    }

    public function test_octane_workload_suitability_analysis(): void
    {
        $service = app(OctanePerformanceService::class);
        $suitability = $service->getWorkloadSuitabilityAnalysis();

        $this->assertArrayHasKey('benefits_long_lived_workers', $suitability);
        $this->assertArrayHasKey('requires_standard_lifecycle', $suitability);

        $benefits = $suitability['benefits_long_lived_workers'];
        $this->assertNotEmpty($benefits);
        $this->assertStringContainsString('Scanner', $benefits[0]['workload']);
    }

    public function test_octane_api_endpoints(): void
    {
        $this->actingAs(User::factory()->role(UserRole::SystemAdmin)->create()); // these endpoints are operatePlatform-only
        // 1. Status endpoint
        $statusRes = $this->getJson('/api/v1/octane/status');
        $statusRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.octane_installed', true);

        // 2. Servers endpoint
        $serversRes = $this->getJson('/api/v1/octane/servers');
        $serversRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.frankenphp.name', 'FrankenPHP')
            ->assertJsonPath('data.swoole.name', 'Swoole / OpenSwoole');

        // 3. Suitability endpoint
        $suitabilityRes = $this->getJson('/api/v1/octane/suitability');
        $suitabilityRes->assertStatus(200)
            ->assertJsonPath('success', true);

        // 4. Concurrency benchmark endpoint
        $benchRes = $this->postJson('/api/v1/octane/benchmark-concurrency', ['tasks' => 3]);
        $benchRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.tasks_count', 3);
    }

    public function test_livewire_octane_performance_hub_renders_and_executes_actions(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::SystemAdmin,
            'name' => 'Speed Officer',
        ]);

        Livewire::actingAs($admin)
            ->test(OctanePerformanceHub::class)
            ->assertSee('High-Performance Runtime Hub')
            ->assertSee('Laravel Octane Accelerated')
            ->assertSee('FrankenPHP')
            ->call('selectServer', 'swoole')
            ->assertSee('Active Octane application server runtime switched to [swoole]')
            ->call('selectTab', 'concurrency')
            ->assertSee('Parallel Concurrency Engine')
            ->call('runConcurrencyBenchmark')
            ->assertSee('Executed')
            ->call('selectTab', 'telemetry')
            ->assertSee('Memory and State Sanitation')
            ->assertSee('Configured In-Memory Cache Tables')
            ->call('selectTab', 'suitability')
            ->assertSee('When Long-Lived Workers Excel');
    }

    public function test_web_route_for_octane_performance_hub_is_accessible(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::SystemAdmin,
        ]);

        $response = $this->actingAs($admin)->get('/operations/octane');
        $response->assertStatus(200);
    }
}
