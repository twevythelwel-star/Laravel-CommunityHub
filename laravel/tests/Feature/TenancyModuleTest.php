<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Tenant;
use App\Services\Tenancy\TenantProvisioningService;
use App\Traits\BelongsToCommunityTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TestTenantRecord extends Model
{
    use BelongsToCommunityTenant;

    protected $table = 'test_tenant_records';

    protected $guarded = [];
}

class TenancyModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (glob(database_path('tenant*')) ?: [] as $file) {
            @unlink($file);
        }

        Schema::create('test_tenant_records', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('tenant_id')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        Schema::dropIfExists('test_tenant_records');

        foreach (glob(database_path('tenant*')) ?: [] as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    public function test_tenant_can_be_provisioned_with_domain(): void
    {
        $service = app(TenantProvisioningService::class);

        $tenant = $service->provision([
            'id' => 'cypress-estate',
            'name' => 'Cypress Bay Estate',
            'domain' => 'cypress.communityhub.test',
            'estate_name' => 'Cypress Bay',
            'app_name' => 'Cypress Hub Pro',
        ]);

        $this->assertDatabaseHas('tenants', ['id' => 'cypress-estate']);
        $this->assertDatabaseHas('domains', [
            'tenant_id' => 'cypress-estate',
            'domain' => 'cypress.communityhub.test',
        ]);

        $this->assertEquals('Cypress Bay Estate', $tenant->name);
        $this->assertEquals('Cypress Bay', $tenant->estate_name);
    }

    public function test_tenant_domain_identification_resolves_tenant(): void
    {
        $tenant = Tenant::create([
            'id' => 'orchid-valley',
            'name' => 'Orchid Valley',
            'estate_name' => 'Orchid Valley Residence',
            'app_name' => 'Orchid Hub',
        ]);

        $tenant->domains()->create([
            'domain' => 'orchid.communityhub.test',
        ]);

        $response = $this->get('http://orchid.communityhub.test/api/tenant/info');

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'active',
            'tenant_id' => 'orchid-valley',
            'name' => 'Orchid Valley',
        ]);
    }

    public function test_tenant_config_overrides_application_settings(): void
    {
        $originalAppName = config('app.name');

        $tenant = Tenant::create([
            'id' => 'palms-estate',
            'name' => 'Royal Palms',
            'app_name' => 'Royal Palms Custom Hub',
            'estate_name' => 'Royal Palms Luxury Enclave',
        ]);

        tenancy()->initialize($tenant);

        // Stancl TenantConfig dynamically overrides configured keys
        $this->assertEquals('Royal Palms Custom Hub', config('app.name'));
        $this->assertEquals('Royal Palms Luxury Enclave', config('gatepass.estate_name'));

        // Revert to central context
        tenancy()->end();

        $this->assertEquals($originalAppName, config('app.name'));
    }

    public function test_tenant_filesystem_storage_is_scoped(): void
    {
        $tenant = Tenant::create(['id' => 'ocean-view']);

        tenancy()->initialize($tenant);

        $tenantStoragePath = storage_path();
        $this->assertStringContainsString('tenantocean-view', $tenantStoragePath);

        tenancy()->end();

        $centralStoragePath = storage_path();
        $this->assertStringNotContainsString('tenantocean-view', $centralStoragePath);
    }

    public function test_tenant_cache_is_scoped_and_isolated(): void
    {
        $tenantA = Tenant::create(['id' => 'tenant-alpha']);
        $tenantB = Tenant::create(['id' => 'tenant-beta']);

        tenancy()->initialize($tenantA);
        Cache::put('announcement', 'Welcome Alpha Residents', 60);
        $this->assertEquals('Welcome Alpha Residents', Cache::get('announcement'));
        tenancy()->end();

        // Central should not see tenant cached key
        $this->assertNull(Cache::get('announcement'));

        // Tenant B should not see Tenant A's cached announcement
        tenancy()->initialize($tenantB);
        $this->assertNull(Cache::get('announcement'));
        Cache::put('announcement', 'Welcome Beta Residents', 60);
        $this->assertEquals('Welcome Beta Residents', Cache::get('announcement'));
        tenancy()->end();
    }

    public function test_single_database_tenant_scoping_prevents_cross_tenant_leaks(): void
    {
        $tenant1 = Tenant::create(['id' => 'community-1']);
        $tenant2 = Tenant::create(['id' => 'community-2']);

        // Create records under Tenant 1
        tenancy()->initialize($tenant1);
        $record1 = TestTenantRecord::create(['name' => 'Gate Pass A1']);
        $this->assertEquals('community-1', $record1->tenant_id);
        $this->assertCount(1, TestTenantRecord::all());
        tenancy()->end();

        // Under Tenant 2, records from Tenant 1 are invisible
        tenancy()->initialize($tenant2);
        $this->assertCount(0, TestTenantRecord::all());
        $record2 = TestTenantRecord::create(['name' => 'Gate Pass B1']);
        $this->assertEquals('community-2', $record2->tenant_id);
        $this->assertCount(1, TestTenantRecord::all());
        $this->assertEquals('Gate Pass B1', TestTenantRecord::first()->name);
        tenancy()->end();

        // Superadmin bypass scope can see all records
        $this->assertCount(2, TestTenantRecord::withoutTenant()->get());
    }
}
