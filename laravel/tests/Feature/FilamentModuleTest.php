<?php

namespace Tests\Feature;

use App\Enums\GateId;
use App\Enums\PassCategory;
use App\Enums\PassStatus;
use App\Enums\UserRole;
use App\Filament\Core\Forms\Form;
use App\Filament\Core\Infolists\Infolist;
use App\Filament\Core\Notifications\Notification;
use App\Filament\Core\PanelRegistry;
use App\Filament\Core\Tables\Table;
use App\Filament\Resources\GatePassResource;
use App\Filament\Resources\ResidentResource;
use App\Filament\Resources\WarningResource;
use App\Filament\Widgets\EstateStatsOverviewWidget;
use App\Livewire\Filament\FilamentHub;
use App\Models\GatePass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FilamentModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_filament_panels_are_registered_in_registry(): void
    {
        $adminPanel = PanelRegistry::get('admin');
        $this->assertNotNull($adminPanel);
        $this->assertEquals('/admin', '/'.$adminPanel->getPath());
        $this->assertContains(GatePassResource::class, $adminPanel->getResources());
        $this->assertContains(ResidentResource::class, $adminPanel->getResources());
        $this->assertContains(WarningResource::class, $adminPanel->getResources());

        $portalPanel = PanelRegistry::get('portal');
        $this->assertNotNull($portalPanel);
        $this->assertEquals('/portal', '/'.$portalPanel->getPath());
        $this->assertContains(GatePassResource::class, $portalPanel->getResources());
    }

    public function test_user_can_access_filament_panel_authorization(): void
    {
        $adminUser = User::factory()->create(['role' => UserRole::Admin, 'status' => 'active']);
        $securityUser = User::factory()->create(['role' => UserRole::Security, 'status' => 'active']);
        $residentUser = User::factory()->create(['role' => UserRole::Homeowner, 'status' => 'active']);
        $deactivatedAdmin = User::factory()->create(['role' => UserRole::Admin, 'status' => 'deactivated']);

        // Admin & Security can access admin panel
        $this->assertTrue($adminUser->canAccessFilamentPanel('admin'));
        $this->assertTrue($securityUser->canAccessFilamentPanel('admin'));

        // Resident cannot access admin panel, but can access portal
        $this->assertFalse($residentUser->canAccessFilamentPanel('admin'));
        $this->assertTrue($residentUser->canAccessFilamentPanel('portal'));

        // Deactivated user cannot access any panel
        $this->assertFalse($deactivatedAdmin->canAccessFilamentPanel('admin'));
        $this->assertFalse($deactivatedAdmin->canAccessFilamentPanel('portal'));
    }

    public function test_filament_routes_render_successfully(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $this->actingAs($admin);

        $responseAdmin = $this->get('/admin');
        $responseAdmin->assertStatus(200);
        $responseAdmin->assertSee('Filament Panel Management');
        $responseAdmin->assertSee('Admin Panel (/admin)');

        $responsePortal = $this->get('/portal');
        $responsePortal->assertStatus(200);
        $responsePortal->assertSee('Resident Portal (/portal)');
    }

    public function test_filament_resource_schemas_are_valid(): void
    {
        // GatePassResource form, table, infolist
        $form = GatePassResource::form(Form::make());
        $this->assertNotEmpty($form->getSchema());

        $table = GatePassResource::table(Table::make());
        $this->assertNotEmpty($table->getColumns());
        $this->assertNotEmpty($table->getFilters());
        $this->assertNotEmpty($table->getActions());
        $this->assertNotEmpty($table->getBulkActions());

        $infolist = GatePassResource::infolist(Infolist::make());
        $this->assertNotEmpty($infolist->getSchema());

        // ResidentResource
        $resForm = ResidentResource::form(Form::make());
        $this->assertNotEmpty($resForm->getSchema());
        $resTable = ResidentResource::table(Table::make());
        $this->assertNotEmpty($resTable->getColumns());

        // WarningResource
        $warnForm = WarningResource::form(Form::make());
        $this->assertNotEmpty($warnForm->getSchema());
        $warnTable = WarningResource::table(Table::make());
        $this->assertNotEmpty($warnTable->getColumns());
    }

    public function test_estate_stats_overview_widget_returns_stats(): void
    {
        $widget = new EstateStatsOverviewWidget;
        $stats = $widget->renderStats();

        $this->assertCount(4, $stats);
        $this->assertEquals('Active Passes', $stats[0]->getLabel());
        $this->assertEquals('Visitors On Site', $stats[1]->getLabel());
        $this->assertEquals('Security Alerts', $stats[2]->getLabel());
        $this->assertEquals('Residents & Staff', $stats[3]->getLabel());
    }

    public function test_filament_hub_component_actions_and_notifications(): void
    {
        $user = User::factory()->create();

        $pass = GatePass::factory()->create([
            'user_id' => $user->id,
            'holder_name' => 'Commander Taggart',
            'status' => PassStatus::Active,
        ]);

        Livewire::actingAs(User::factory()->role(UserRole::Security)->create())
            ->test(FilamentHub::class)
            ->assertSee('Filament Panel Management')
            ->call('switchPanel', 'portal')
            ->assertSet('currentPanel', 'portal')
            ->call('switchPanel', 'admin')
            ->assertSet('currentPanel', 'admin')
            ->call('setView', 'passes')
            ->assertSet('currentView', 'passes')
            ->assertSee('Commander Taggart')
            ->call('triggerAction', 'checkIn', $pass->id)
            ->assertDispatched('notify');

        $pass->refresh();
        $this->assertEquals(PassStatus::CheckedIn, $pass->status);

        // Test create pass form action
        Livewire::test(FilamentHub::class)
            ->set('formHolderName', 'Captain Kirk')
            ->set('formProperty', 'Lot 1701, Enterprise Way')
            ->set('formCategory', PassCategory::Visitor->value)
            ->set('formGate', GateId::Gate01->value)
            ->call('savePass')
            ->assertHasNoErrors()
            ->assertDispatched('notify');

        $this->assertDatabaseHas('gate_passes', [
            'holder_name' => 'Captain Kirk',
            'property' => 'Lot 1701, Enterprise Way',
        ]);

        // Test notification dispatch
        Livewire::test(FilamentHub::class)
            ->call('sendSampleNotification', 'success')
            ->assertDispatched('notify');

        // Test view infolist
        Livewire::test(FilamentHub::class)
            ->call('viewInfolist', $pass->id)
            ->assertSet('showInfolistModal', true)
            ->assertSee('Filament Infolist Schema')
            ->call('closeInfolist')
            ->assertSet('showInfolistModal', false);
    }
}
