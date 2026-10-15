<?php

namespace Tests\Feature;

use App\Enums\TokenAbility;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Platform metrics describe the server, not the estate. System Admin only;
 * the liveness probe stays public for load balancers.
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

    public function test_metrics_need_a_token(): void
    {
        $this->getJson('/api/v1/metrics')->assertUnauthorized();
    }

    public function test_an_admins_token_cannot_read_metrics(): void
    {
        $this->getJson('/api/v1/metrics', $this->bearer($this->as(UserRole::Admin)))->assertForbidden();
    }

    public function test_a_system_admins_token_can_read_metrics(): void
    {
        $this->getJson('/api/v1/metrics', $this->bearer($this->as(UserRole::SystemAdmin)))
            ->assertOk()
            ->assertJsonPath('data.period', '24_hours')
            ->assertJsonPath('data.webhooks.success_rate_percent', 100)
            ->assertJsonStructure(['data' => [
                'api' => ['total_requests', 'average_latency_ms', 'status_breakdown' => ['2xx_success', '4xx_client_error', '5xx_server_error']],
                'tokens' => ['active_count'],
                'webhooks' => ['total_deliveries', 'successful_deliveries'],
                'generated_at',
            ]]);
    }

    public function test_the_liveness_probe_stays_public(): void
    {
        $this->getJson('/api/v1/health')->assertSuccessful();
    }
}
