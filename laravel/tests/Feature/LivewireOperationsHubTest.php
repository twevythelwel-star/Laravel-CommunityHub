<?php

namespace Tests\Feature;

use App\Enums\GateId;
use App\Enums\PassCategory;
use App\Enums\PassStatus;
use App\Enums\UserRole;
use App\Livewire\CommunityOperationsHub;
use App\Livewire\Components\UiShowcase;
use App\Livewire\GatePasses\PassManager;
use App\Livewire\Residents\ResidentDirectory;
use App\Livewire\Warnings\WarningDesk;
use App\Models\GatePass;
use App\Models\User;
use App\Models\Warning;
use App\Models\WarningResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LivewireOperationsHubTest extends TestCase
{
    use RefreshDatabase;

    /**
     * These pages are for the security desk and administrators, so each test
     * runs as an Admin unless it signs in as somebody else. Access by role is
     * covered by OperationsHubAccessTest.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->role(UserRole::Admin)->create());
    }

    public function test_operations_hub_route_and_component_render_successfully(): void
    {
        $response = $this->get('/operations');
        $response->assertStatus(200);
        $response->assertSee('Community Operations Hub');

        Livewire::test(CommunityOperationsHub::class)
            ->assertSee('Active Gate Passes')
            ->assertSee('Visitors On Site')
            ->assertSee('Active Alerts')
            ->assertSee('Resident Directory')
            ->assertSet('activeTab', 'overview');
    }

    public function test_operations_hub_switches_tabs(): void
    {
        Livewire::test(CommunityOperationsHub::class)
            ->call('setTab', 'passes')
            ->assertSet('activeTab', 'passes')
            ->call('setTab', 'directory')
            ->assertSet('activeTab', 'directory')
            ->call('setTab', 'warnings')
            ->assertSet('activeTab', 'warnings')
            ->call('setTab', 'ui-kit')
            ->assertSet('activeTab', 'ui-kit');
    }

    public function test_pass_manager_renders_and_validates_form(): void
    {
        $user = User::factory()->create();

        Livewire::test(PassManager::class)
            ->set('holder_name', '')
            ->call('createPass')
            ->assertHasErrors(['holder_name'])
            ->set('holder_name', 'Marcus Sterling')
            ->set('category', PassCategory::Visitor->value)
            ->set('designated_gate', GateId::Gate01->value)
            ->set('property', 'Lot 88, Sunrise Bay')
            ->set('access_zone', 'ZONE-HOST-RESIDENCE')
            ->set('valid_from', now()->format('Y-m-d\TH:i'))
            ->set('valid_until', now()->addDay()->format('Y-m-d\TH:i'))
            ->call('createPass')
            ->assertHasNoErrors()
            ->assertDispatched('notify');

        $this->assertDatabaseHas('gate_passes', [
            'holder_name' => 'Marcus Sterling',
            'property' => 'Lot 88, Sunrise Bay',
            'status' => PassStatus::Active->value,
        ]);
    }

    public function test_pass_manager_can_search_filter_and_transition_status(): void
    {
        $user = User::factory()->create();

        $pass = GatePass::factory()->create([
            'user_id' => $user->id,
            'holder_name' => 'Dr. Alexander Vance',
            'status' => PassStatus::Active,
        ]);

        Livewire::test(PassManager::class)
            ->set('search', 'Alexander')
            ->assertSee('Dr. Alexander Vance')
            ->call('checkIn', $pass->id)
            ->assertDispatched('notify');

        $pass->refresh();
        $this->assertEquals(PassStatus::CheckedIn, $pass->status);
        $this->assertNotNull($pass->checked_in_at);

        Livewire::test(PassManager::class)
            ->call('checkOut', $pass->id)
            ->assertDispatched('notify');

        $pass->refresh();
        $this->assertEquals(PassStatus::CheckedOut, $pass->status);
        $this->assertNotNull($pass->checked_out_at);
    }

    public function test_pass_manager_can_revoke_pass(): void
    {
        $user = User::factory()->create();

        $pass = GatePass::factory()->create([
            'user_id' => $user->id,
            'holder_name' => 'Temporary Contractor',
            'status' => PassStatus::Active,
        ]);

        Livewire::test(PassManager::class)
            ->call('confirmRevoke', $pass->id)
            ->assertSet('showRevokeModal', true)
            ->set('revocationReason', 'Contract terminated early')
            ->call('revokePass')
            ->assertSet('showRevokeModal', false)
            ->assertDispatched('notify');

        $pass->refresh();
        $this->assertEquals(PassStatus::Revoked, $pass->status);
        $this->assertEquals('Contract terminated early', $pass->revocation_reason);
    }

    public function test_resident_directory_searches_registers_and_toggles(): void
    {
        $resident = User::factory()->create([
            'name' => 'Helena Harper',
            'email' => 'helena@cypressbay.local',
            'lot' => 'Lot 50',
            'role' => UserRole::Homeowner,
            'status' => 'Active',
        ]);

        Livewire::test(ResidentDirectory::class)
            ->set('search', 'Helena')
            ->assertSee('Helena Harper')
            ->assertSee('Lot 50')
            ->set('name', 'Benjamin Sisko')
            ->set('email', 'benjamin@cypressbay.local')
            ->set('phone', '555-0199')
            ->set('role', UserRole::Homeowner->value)
            ->set('lot', 'Lot 99')
            ->set('street', 'Deep Space Way')
            ->call('createResident')
            ->assertHasNoErrors()
            ->assertDispatched('notify');

        $this->assertDatabaseHas('users', [
            'name' => 'Benjamin Sisko',
            'email' => 'benjamin@cypressbay.local',
            'lot' => 'Lot 99',
        ]);

        Livewire::test(ResidentDirectory::class)
            ->call('toggleUserStatus', $resident->id)
            ->assertDispatched('notify');

        $resident->refresh();
        $this->assertEquals('Inactive', $resident->status);
        $this->assertFalse($resident->isActive());
    }

    public function test_warning_desk_broadcasts_alert_and_records_votes(): void
    {
        $user = User::factory()->create();
        $voter = User::factory()->create();

        $this->actingAs($user);

        Livewire::test(WarningDesk::class)
            ->set('title', 'Water Main Inspection')
            ->set('description', 'Scheduled maintenance on north lane pumps between 10am-2pm.')
            ->call('createWarning')
            ->assertHasNoErrors()
            ->assertDispatched('notify');

        $this->assertDatabaseHas('warnings', [
            'title' => 'Water Main Inspection',
            'author_id' => $user->id,
        ]);

        $warning = Warning::where('title', 'Water Main Inspection')->first();

        // Author cannot vote on their own alert
        Livewire::test(WarningDesk::class)
            ->call('vote', $warning->id, 'confirmed')
            ->assertDispatched('notify');

        $this->assertEquals(0, WarningResponse::count());

        // Second resident votes
        $this->actingAs($voter);
        Livewire::test(WarningDesk::class)
            ->call('vote', $warning->id, 'confirmed')
            ->assertDispatched('notify');

        $this->assertDatabaseHas('warning_responses', [
            'warning_id' => $warning->id,
            'user_id' => $voter->id,
            'response' => 'confirmed',
        ]);
        $this->assertEquals(1, $warning->confirmsCount());
    }

    public function test_ui_showcase_components_and_bulk_actions(): void
    {
        Livewire::test(UiShowcase::class)
            ->assertSee('1. Reactive Forms')
            ->assertSee('2. Tables, Search, Filtering')
            ->assertSee('3. Alpine.js Lightweight Browser Micro-Interactions')
            ->set('formName', 'Test User')
            ->set('formEmail', 'test@example.com')
            ->set('formAccepted', true)
            ->call('submitDemoForm')
            ->assertHasNoErrors()
            ->assertSet('isFormSubmitted', true)
            ->call('incrementCounter')
            ->assertSet('liveCounter', 43)
            ->call('decrementCounter')
            ->assertSet('liveCounter', 42)
            ->set('selectedRows', [101, 102])
            ->call('performBulkAction', 'Test Action')
            ->assertDispatched('notify');
    }
}
