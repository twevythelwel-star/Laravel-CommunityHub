<?php

namespace Tests\Feature;

use App\Enums\TokenAbility;
use App\Enums\UserRole;
use App\Livewire\Observability\ObservabilityHub;
use App\Livewire\Performance\OctanePerformanceHub;
use App\Livewire\Queue\HorizonQueueHub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Queue, Octane and observability tooling runs the platform, not the estate:
 * it queues real email, SMS, export and webhook jobs, loads the server, and
 * reads and writes the application and audit logs. System Admin only.
 */
class PlatformOperationsAccessTest extends TestCase
{
    use RefreshDatabase;

    private function as(UserRole $role): User
    {
        return User::factory()->role($role)->create();
    }

    private function bearer(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('Device', TokenAbility::forUser($user))->plainTextToken];
    }

    // ── Pages ───────────────────────────────────────────────────────

    /** @return array<string, array{string}> */
    public static function pages(): array
    {
        return [
            'queues' => ['/operations/queues'],
            'octane' => ['/operations/octane'],
            'observability' => ['/operations/observability'],
        ];
    }

    #[DataProvider('pages')]
    public function test_a_guest_is_sent_to_sign_in(string $uri): void
    {
        $this->get($uri)->assertRedirect(route('login'));
    }

    #[DataProvider('pages')]
    public function test_only_a_system_admin_can_open_the_page(string $uri): void
    {
        foreach ([UserRole::Homeowner, UserRole::Security, UserRole::Admin] as $role) {
            $this->actingAs($this->as($role))->get($uri)->assertForbidden();
        }

        $this->actingAs($this->as(UserRole::SystemAdmin))->get($uri)->assertOk();
    }

    /** @return array<string, array{class-string}> */
    public static function components(): array
    {
        return [
            'queues' => [HorizonQueueHub::class],
            'octane' => [OctanePerformanceHub::class],
            'observability' => [ObservabilityHub::class],
        ];
    }

    #[DataProvider('components')]
    public function test_an_admin_cannot_load_the_component(string $component): void
    {
        Livewire::actingAs($this->as(UserRole::Admin))->test($component)->assertForbidden();
    }

    public function test_a_system_admin_can_queue_a_job_as_themselves(): void
    {
        Queue::fake();
        $root = $this->as(UserRole::SystemAdmin);

        Livewire::actingAs($root)
            ->test(HorizonQueueHub::class)
            ->set('selectedJobType', 'report')
            ->call('dispatchSelectedJob')
            ->assertSet('jobHistory.0.dispatched_by', $root->name);
    }

    public function test_an_unknown_log_level_is_rejected(): void
    {
        Livewire::actingAs($this->as(UserRole::SystemAdmin))
            ->test(ObservabilityHub::class)
            ->set('testLogLevel', 'catastrophic')
            ->call('writeTestLog')
            ->assertHasErrors('testLogLevel');
    }

    // ── API ─────────────────────────────────────────────────────────

    /** @return array<string, array{string, string}> */
    public static function apiRoutes(): array
    {
        return [
            'metrics' => ['GET', '/api/v1/metrics'],
            'queue dispatch' => ['POST', '/api/v1/queues/dispatch'],
            'queue stats' => ['GET', '/api/v1/queues/stats'],
            'queue jobs' => ['GET', '/api/v1/queues/jobs'],
            'horizon status' => ['GET', '/api/v1/queues/horizon-status'],
            'octane status' => ['GET', '/api/v1/octane/status'],
            'octane servers' => ['GET', '/api/v1/octane/servers'],
            'octane suitability' => ['GET', '/api/v1/octane/suitability'],
            'octane benchmark' => ['POST', '/api/v1/octane/benchmark-concurrency'],
            'observability status' => ['GET', '/api/v1/observability/status'],
            'observability health' => ['GET', '/api/v1/observability/health'],
            'observability performance' => ['GET', '/api/v1/observability/performance'],
            'application logs' => ['GET', '/api/v1/observability/logs'],
            'audit logs' => ['GET', '/api/v1/observability/audit-logs'],
            'tracked errors' => ['GET', '/api/v1/observability/errors'],
            'resolve error' => ['POST', '/api/v1/observability/errors/1/resolve'],
            'queue metrics' => ['GET', '/api/v1/observability/queue-metrics'],
            'simulate error' => ['POST', '/api/v1/observability/simulate-error'],
            'write audit' => ['POST', '/api/v1/observability/write-audit'],
        ];
    }

    #[DataProvider('apiRoutes')]
    public function test_the_api_needs_a_token(string $method, string $uri): void
    {
        $this->json($method, $uri)->assertUnauthorized();
    }

    #[DataProvider('apiRoutes')]
    public function test_an_admins_token_is_refused(string $method, string $uri): void
    {
        $this->json($method, $uri, [], $this->bearer($this->as(UserRole::Admin)))->assertForbidden();
    }

    public function test_a_system_admins_token_can_read_the_logs(): void
    {
        $this->getJson('/api/v1/observability/logs', $this->bearer($this->as(UserRole::SystemAdmin)))->assertOk();
    }

    public function test_the_liveness_probe_stays_public(): void
    {
        $this->getJson('/api/v1/health')->assertSuccessful();
    }
}
