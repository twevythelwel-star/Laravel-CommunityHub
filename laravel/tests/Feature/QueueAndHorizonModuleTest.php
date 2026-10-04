<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Jobs\Queue\DeliverWebhookJob;
use App\Jobs\Queue\DispatchQueuedNotificationJob;
use App\Jobs\Queue\ExportDataJob;
use App\Jobs\Queue\GeneratePdfDocumentJob;
use App\Jobs\Queue\GenerateReportJob;
use App\Jobs\Queue\ImportDataJob;
use App\Jobs\Queue\ProcessAiInferenceJob;
use App\Jobs\Queue\ProcessImageMediaJob;
use App\Jobs\Queue\ProcessQueuedEmailJob;
use App\Jobs\Queue\ProcessQueuedSmsJob;
use App\Jobs\Queue\SynchronizeExternalDataJob;
use App\Livewire\Queue\HorizonQueueHub;
use App\Models\User;
use App\Services\Queue\QueueMonitoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class QueueAndHorizonModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_queue_and_horizon_configurations_are_properly_structured(): void
    {
        $this->assertNotNull(config('queue.connections.redis'));
        $this->assertSame('redis', config('queue.connections.redis.driver'));

        $this->assertSame('Community Hub Horizon', config('horizon.name'));
        $this->assertArrayHasKey('defaults', config('horizon'));
        $this->assertArrayHasKey('supervisor-high', config('horizon.defaults'));
        $this->assertArrayHasKey('supervisor-default', config('horizon.defaults'));
        $this->assertArrayHasKey('supervisor-batch', config('horizon.defaults'));

        $this->assertSame(['high'], config('horizon.defaults.supervisor-high.queue'));
        $this->assertSame(['default'], config('horizon.defaults.supervisor-default.queue'));
        $this->assertSame(['low'], config('horizon.defaults.supervisor-batch.queue'));
    }

    public function test_queue_service_catalog_contains_all_eleven_workload_domains(): void
    {
        $service = app(QueueMonitoringService::class);
        $catalog = $service->getJobCatalog();

        $this->assertCount(11, $catalog);
        $expectedKeys = [
            'email', 'sms', 'report', 'export', 'import',
            'image', 'pdf', 'notification', 'ai', 'sync', 'webhook',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $catalog);
            $this->assertArrayHasKey('queue', $catalog[$key]);
            $this->assertArrayHasKey('priority', $catalog[$key]);
            $this->assertArrayHasKey('class', $catalog[$key]);
        }
    }

    public function test_all_eleven_jobs_dispatch_into_their_appropriate_priority_pools(): void
    {
        Queue::fake();

        $service = app(QueueMonitoringService::class);

        // 1. Email (default)
        $service->dispatchJob('email', ['recipient_email' => 'test@example.com']);
        Queue::assertPushedOn('default', ProcessQueuedEmailJob::class);

        // 2. SMS (high)
        $service->dispatchJob('sms', ['phone_number' => '+18765550100']);
        Queue::assertPushedOn('high', ProcessQueuedSmsJob::class);

        // 3. Report (low)
        $service->dispatchJob('report', ['report_type' => 'daily_audit']);
        Queue::assertPushedOn('low', GenerateReportJob::class);

        // 4. Export (low)
        $service->dispatchJob('export', ['dataset' => 'residents']);
        Queue::assertPushedOn('low', ExportDataJob::class);

        // 5. Import (low)
        $service->dispatchJob('import', ['import_type' => 'roster', 'records_count' => 50, 'file_path' => 'import.csv']);
        Queue::assertPushedOn('low', ImportDataJob::class);

        // 6. Image (default)
        $service->dispatchJob('image', ['image_path' => 'badge.png']);
        Queue::assertPushedOn('default', ProcessImageMediaJob::class);

        // 7. PDF (default)
        $service->dispatchJob('pdf', ['document_type' => 'pass', 'entity_id' => 99]);
        Queue::assertPushedOn('default', GeneratePdfDocumentJob::class);

        // 8. Notification (high)
        $service->dispatchJob('notification', ['user_id' => 1, 'title' => 'Alert', 'body' => 'Notice']);
        Queue::assertPushedOn('high', DispatchQueuedNotificationJob::class);

        // 9. AI (low)
        $service->dispatchJob('ai', ['task_type' => 'license_plate_ocr']);
        Queue::assertPushedOn('low', ProcessAiInferenceJob::class);

        // 10. Sync (low)
        $service->dispatchJob('sync', ['target_system' => 'gate_hardware']);
        Queue::assertPushedOn('low', SynchronizeExternalDataJob::class);

        // 11. Webhook (high)
        $service->dispatchJob('webhook', ['webhook_url' => 'https://example.com/wh', 'event' => 'ping']);
        Queue::assertPushedOn('high', DeliverWebhookJob::class);
    }

    public function test_queue_monitoring_service_returns_metrics_and_horizon_telemetry(): void
    {
        $service = app(QueueMonitoringService::class);

        $metrics = $service->getQueueMetrics();
        $this->assertArrayHasKey('queues', $metrics);
        $this->assertArrayHasKey('high', $metrics['queues']);
        $this->assertArrayHasKey('default', $metrics['queues']);
        $this->assertArrayHasKey('low', $metrics['queues']);
        $this->assertArrayHasKey('total_pending', $metrics);
        $this->assertArrayHasKey('failed_jobs', $metrics);

        $horizon = $service->getHorizonStatus();
        $this->assertTrue($horizon['installed']);
        $this->assertArrayHasKey('architecture', $horizon);
        $this->assertSame('Laravel Horizon (Master Process + Supervisor)', $horizon['architecture']['supervisor']);
    }

    public function test_queue_api_public_and_telemetry_endpoints(): void
    {
        $this->actingAs(User::factory()->role(UserRole::SystemAdmin)->create()); // these endpoints are operatePlatform-only
        // 1. Stats endpoint
        $statsRes = $this->getJson('/api/v1/queues/stats');
        $statsRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'operational');

        // 2. Jobs catalog endpoint
        $jobsRes = $this->getJson('/api/v1/queues/jobs');
        $jobsRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.email.queue', 'default')
            ->assertJsonPath('data.sms.queue', 'high');

        // 3. Horizon status endpoint
        $horizonRes = $this->getJson('/api/v1/queues/horizon-status');
        $horizonRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.installed', true);
    }

    public function test_queue_api_dispatch_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/queues/dispatch', [
            'type' => 'email',
            'payload' => ['recipient_email' => 'unauth@example.com'],
        ]);

        $response->assertStatus(401);
    }

    public function test_queue_api_dispatch_queues_job_for_authenticated_user(): void
    {
        Queue::fake();

        $admin = User::factory()->create([
            'role' => UserRole::SystemAdmin,
            'name' => 'Dispatch Officer',
        ]);

        $response = $this->actingAs($admin)->postJson('/api/v1/queues/dispatch', [
            'type' => 'webhook',
            'payload' => [
                'webhook_url' => 'https://external-api.test/webhook',
                'event' => 'gatepass.cleared',
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.job_type', 'webhook')
            ->assertJsonPath('data.target_queue', 'high')
            ->assertJsonPath('data.priority', 'critical');

        Queue::assertPushedOn('high', DeliverWebhookJob::class);
    }

    public function test_horizon_authorization_gate(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SystemAdmin]);
        $resident = User::factory()->create(['role' => UserRole::Homeowner]);

        $this->assertTrue(Gate::forUser($admin)->allows('viewHorizon'));
        $this->assertFalse(Gate::forUser($resident)->allows('viewHorizon'));
    }

    public function test_livewire_horizon_queue_hub_renders_and_dispatches_jobs(): void
    {
        Queue::fake();

        $admin = User::factory()->create([
            'role' => UserRole::SystemAdmin,
            'name' => 'Commander Queue',
        ]);

        Livewire::actingAs($admin)
            ->test(HorizonQueueHub::class)
            ->assertSee('Background Processing')
            ->assertSee('Laravel Queues + Horizon Active')
            ->assertSee('High Priority Pool')
            ->assertSee('Default Pool')
            ->call('selectTab', 'dispatcher')
            ->assertSee('Dispatch Background Workload')
            ->set('selectedJobType', 'sms')
            ->call('dispatchSelectedJob')
            ->assertSee('Priority SMS Dispatch')
            ->call('selectTab', 'catalog')
            ->assertSee('Workload Catalog')
            ->call('selectTab', 'architecture')
            ->assertSee('Enterprise Queue Architecture Pipeline');

        Queue::assertPushedOn('high', ProcessQueuedSmsJob::class);
    }

    public function test_web_route_for_horizon_queue_hub_is_accessible(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::SystemAdmin,
        ]);

        $response = $this->actingAs($admin)->get('/operations/queues');
        $response->assertStatus(200);
    }
}
