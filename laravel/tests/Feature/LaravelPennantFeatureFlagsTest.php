<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Features\FeatureFlagHub;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Features\FeatureFlagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class LaravelPennantFeatureFlagsTest extends TestCase
{
    use RefreshDatabase;

    public function test_pennant_is_installed_and_configured_with_database_store(): void
    {
        $this->assertSame('database', config('pennant.default'));
        $this->assertTrue(Schema::hasTable('features'));
    }

    public function test_feature_catalog_contains_all_six_enterprise_flag_archetypes(): void
    {
        $service = app(FeatureFlagService::class);
        $catalog = $service->getCatalog();

        $this->assertGreaterThanOrEqual(10, count($catalog));

        // 1. feature.enabled
        $this->assertArrayHasKey('rfid_gate_scanner', $catalog);
        $this->assertSame('boolean_global', $catalog['rfid_gate_scanner']['type']);
        $this->assertSame('feature.enabled', $catalog['rfid_gate_scanner']['use_case']);

        // 2. beta.features
        $this->assertArrayHasKey('ai_visitor_analytics', $catalog);
        $this->assertSame('beta_opt_in', $catalog['ai_visitor_analytics']['type']);
        $this->assertSame('beta.features', $catalog['ai_visitor_analytics']['use_case']);

        // 3. A/B testing
        $this->assertArrayHasKey('checkout_flow_experiment', $catalog);
        $this->assertSame('ab_testing', $catalog['checkout_flow_experiment']['type']);
        $this->assertSame('A/B testing', $catalog['checkout_flow_experiment']['use_case']);
        $this->assertContains('classic', $catalog['checkout_flow_experiment']['variants']);

        // 4. gradual rollout
        $this->assertArrayHasKey('new_resident_portal', $catalog);
        $this->assertSame('gradual_rollout', $catalog['new_resident_portal']['type']);
        $this->assertSame('gradual rollout', $catalog['new_resident_portal']['use_case']);
        $this->assertSame(40, $catalog['new_resident_portal']['rollout_percentage']);

        // 5. tenant-specific features
        $this->assertArrayHasKey('automated_barrier_motor', $catalog);
        $this->assertSame('tenant_specific', $catalog['automated_barrier_motor']['type']);
        $this->assertSame('tenant-specific features', $catalog['automated_barrier_motor']['use_case']);

        // 6. role-specific features
        $this->assertArrayHasKey('advanced_audit_tools', $catalog);
        $this->assertSame('role_specific', $catalog['advanced_audit_tools']['type']);
        $this->assertSame('role-specific features', $catalog['advanced_audit_tools']['use_case']);
    }

    public function test_boolean_global_feature_toggles_and_activation(): void
    {
        $service = app(FeatureFlagService::class);

        // Initially active
        $this->assertTrue($service->active('rfid_gate_scanner'));

        // Deactivate globally
        $service->deactivate('rfid_gate_scanner');
        $this->assertFalse($service->active('rfid_gate_scanner'));

        // Reactivate globally
        $service->activate('rfid_gate_scanner');
        $this->assertTrue($service->active('rfid_gate_scanner'));
    }

    public function test_beta_feature_resolution_scoped_to_user_opt_in(): void
    {
        $service = app(FeatureFlagService::class);

        $regularUser = User::factory()->create([
            'email' => 'regular.homeowner@somemail.com',
            'ai_consent' => false,
        ]);

        $betaUser = User::factory()->create([
            'email' => 'beta.tester@somemail.com',
            'ai_consent' => true,
        ]);

        $this->assertFalse($service->active('ai_visitor_analytics', $regularUser));
        $this->assertTrue($service->active('ai_visitor_analytics', $betaUser));
    }

    public function test_ab_testing_multi_variant_experimentation_is_deterministic(): void
    {
        $service = app(FeatureFlagService::class);

        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $variantA1 = $service->value('checkout_flow_experiment', $userA);
        $variantA2 = $service->value('checkout_flow_experiment', $userA);

        $this->assertContains($variantA1, ['classic', 'streamlined', 'express_one_click']);
        // Deterministic persistence: consecutive calls for same user yield identical variant
        $this->assertSame($variantA1, $variantA2);

        $variantB = $service->value('checkout_flow_experiment', $userB);
        $this->assertContains($variantB, ['classic', 'streamlined', 'express_one_click']);
    }

    public function test_gradual_canary_rollout_is_percentage_based(): void
    {
        $service = app(FeatureFlagService::class);

        $includedUser = new User;
        $includedUser->id = 15; // 15 % 100 = 15 < 40

        $excludedUser = new User;
        $excludedUser->id = 75; // 75 % 100 = 75 >= 40

        $this->assertTrue($service->active('new_resident_portal', $includedUser));
        $this->assertFalse($service->active('new_resident_portal', $excludedUser));
    }

    public function test_tenant_specific_features_resolve_by_community_tenant_scope(): void
    {
        $service = app(FeatureFlagService::class);

        $premiumTenant = new Tenant(['id' => 'palm-grove']);
        $standardTenant = new Tenant(['id' => 'standard-grove']);

        $this->assertTrue($service->active('automated_barrier_motor', $premiumTenant));
        $this->assertFalse($service->active('automated_barrier_motor', $standardTenant));
    }

    public function test_role_specific_features_resolve_based_on_user_role(): void
    {
        $service = app(FeatureFlagService::class);

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $security = User::factory()->create(['role' => UserRole::Security]);
        $resident = User::factory()->create(['role' => UserRole::Homeowner]);

        // Admin has advanced audit and security dispatch
        $this->assertTrue($service->active('advanced_audit_tools', $admin));
        $this->assertTrue($service->active('security_dispatch_hub', $admin));

        // Security has security dispatch but not advanced audit
        $this->assertFalse($service->active('advanced_audit_tools', $security));
        $this->assertTrue($service->active('security_dispatch_hub', $security));

        // Resident has neither
        $this->assertFalse($service->active('advanced_audit_tools', $resident));
        $this->assertFalse($service->active('security_dispatch_hub', $resident));
    }

    public function test_pennant_feature_api_endpoints(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SystemAdmin]);

        // 1. Catalog endpoint
        $catalogRes = $this->actingAs($admin)->getJson('/api/v1/features/catalog');
        $catalogRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_features', 11);

        // 2. Index resolved features for current user
        $indexRes = $this->actingAs($admin)->getJson('/api/v1/features');
        $indexRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.features.rfid_gate_scanner.active', true);

        // 3. Check endpoint
        $checkRes = $this->actingAs($admin)->postJson('/api/v1/features/check', [
            'feature' => 'rfid_gate_scanner',
        ]);
        $checkRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.active', true);

        // 4. Activate endpoint
        $actRes = $this->actingAs($admin)->postJson('/api/v1/features/activate', [
            'feature' => 'automated_barrier_motor',
            'value' => true,
        ]);
        $actRes->assertStatus(200)
            ->assertJsonPath('success', true);

        // 5. Deactivate endpoint
        $deactRes = $this->actingAs($admin)->postJson('/api/v1/features/deactivate', [
            'feature' => 'automated_barrier_motor',
        ]);
        $deactRes->assertStatus(200)
            ->assertJsonPath('success', true);

        // 6. Purge endpoint
        $purgeRes = $this->actingAs($admin)->postJson('/api/v1/features/purge', [
            'feature' => 'automated_barrier_motor',
        ]);
        $purgeRes->assertStatus(200)
            ->assertJsonPath('success', true);

        // 7. Simulate endpoint
        $simRes = $this->actingAs($admin)->postJson('/api/v1/features/simulate', [
            'feature' => 'checkout_flow_experiment',
            'scope_type' => 'user',
            'scope_value' => '42',
        ]);
        $simRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.scope_type', 'user');
    }

    public function test_livewire_feature_flag_hub_renders_and_executes_actions(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::SystemAdmin,
            'name' => 'Release Manager',
        ]);

        Livewire::actingAs($admin)
            ->test(FeatureFlagHub::class)
            ->assertSee('Feature Flags & Experimentation Hub')
            ->assertSee('Feature Flags Directory')
            ->assertSee('RFID Gate Scanner Integration')
            ->call('toggleFeature', 'rfid_gate_scanner')
            ->assertSee('deactivated globally')
            ->call('selectTab', 'rollouts_ab')
            ->assertSee('A/B Testing & Progressive Canary Rollouts')
            ->assertSee('checkout_flow_experiment')
            ->call('selectTab', 'scopes_simulator')
            ->assertSee('Feature Flag Resolution Simulator')
            ->call('runSimulation')
            ->assertSee('Evaluated')
            ->call('selectTab', 'database_store')
            ->assertSee('Pennant Database Storage Engine')
            ->call('purgeStore')
            ->assertSee('Flushed all resolved feature values');
    }

    public function test_web_route_for_feature_flag_hub_is_accessible(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SystemAdmin]);

        $response = $this->actingAs($admin)->get('/operations/features');
        $response->assertStatus(200);
    }
}
