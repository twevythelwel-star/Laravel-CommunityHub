<?php

namespace Tests\Feature;

use App\Enums\GateId;
use App\Enums\PassCategory;
use App\Enums\PassStatus;
use App\Enums\UserRole;
use App\Http\Middleware\EnsureUserIsActive;
use App\Livewire\CommunityOperationsHub;
use App\Livewire\GatePasses\PassManager;
use App\Livewire\Residents\ResidentDirectory;
use App\Livewire\Warnings\WarningDesk;
use App\Models\GatePass;
use App\Models\User;
use App\Models\Warning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Livewire\Mechanisms\PersistentMiddleware\PersistentMiddleware;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The operations and search pages list residents,
 * the ledger and the estate's passes, and can create, revoke and check
 * passes in and out. Each is limited to the roles that already see the same
 * records on the dashboard, both at the route and inside the component.
 */
class OperationsHubAccessTest extends TestCase
{
    use RefreshDatabase;

    private function as(UserRole $role): User
    {
        return User::factory()->role($role)->create();
    }

    private function pass(): GatePass
    {
        return GatePass::create([
            'pass_id' => 'VIS-'.strtoupper(fake()->unique()->bothify('????####')),
            'user_id' => $this->as(UserRole::Homeowner)->id,
            'category' => PassCategory::Visitor,
            'holder_name' => 'Ms. Test Visitor',
            'property' => 'Lot 7',
            'access_zone' => 'ZONE-HOST-RESIDENCE',
            'designated_gate' => GateId::Gate01,
            'valid_from' => now()->subHour(),
            'valid_until' => now()->addDay(),
            'single_entry' => false,
            'color_variant' => 'blue',
            'rotation_seq' => 1,
            'status' => PassStatus::Active,
        ]);
    }

    // ── Routes ──────────────────────────────────────────────────────

    /** @return array<string, array{string}> */
    public static function signedInPages(): array
    {
        return [
            'hub' => ['/operations'],
            'passes' => ['/operations/passes'],
            'directory' => ['/operations/directory'],
            'warnings' => ['/operations/warnings'],
            'ui kit' => ['/operations/ui-kit'],
            'search' => ['/search'],
            'search api' => ['/api/search?q=a'],
            'search driver' => ['/api/search/driver'],
        ];
    }

    #[DataProvider('signedInPages')]
    public function test_a_guest_is_sent_to_sign_in(string $uri): void
    {
        $this->get($uri)->assertRedirect(route('login'));
    }

    #[DataProvider('signedInPages')]
    public function test_a_deactivated_account_is_signed_out(string $uri): void
    {
        $user = User::factory()->role(UserRole::SystemAdmin)->create(['status' => 'Inactive', 'deactivated_at' => now()]);

        $this->actingAs($user)->get($uri)->assertRedirect(route('login'));
        $this->assertGuest();
    }

    /** @return array<string, array{string}> */
    public static function securityDeskPages(): array
    {
        return [
            'hub' => ['/operations'],
            'passes' => ['/operations/passes'],
        ];
    }

    #[DataProvider('securityDeskPages')]
    public function test_a_resident_cannot_open_the_security_desk(string $uri): void
    {
        $this->actingAs($this->as(UserRole::Homeowner))->get($uri)->assertForbidden();
    }

    #[DataProvider('securityDeskPages')]
    public function test_security_can_open_the_security_desk(string $uri): void
    {
        $this->actingAs($this->as(UserRole::Security))->get($uri)->assertOk();
    }

    public function test_only_administrators_can_open_the_resident_directory(): void
    {
        $this->actingAs($this->as(UserRole::Security))->get('/operations/directory')->assertForbidden();
        $this->actingAs($this->as(UserRole::Admin))->get('/operations/directory')->assertOk();
    }

    public function test_a_resident_can_open_the_warning_desk(): void
    {
        $this->actingAs($this->as(UserRole::Homeowner))->get('/operations/warnings')->assertOk();
    }

    public function test_the_public_layout_offers_the_hub_only_to_the_security_desk(): void
    {
        $this->get('/privacy')->assertOk()->assertDontSee('Livewire Hub');
        $this->actingAs($this->as(UserRole::Homeowner))->get('/privacy')->assertDontSee('Livewire Hub');
        $this->actingAs($this->as(UserRole::Security))->get('/privacy')->assertSee('Livewire Hub');
    }

    public function test_component_updates_also_check_the_account_is_active(): void
    {
        $this->assertContains(
            EnsureUserIsActive::class,
            app(PersistentMiddleware::class)->getPersistentMiddleware(),
        );
    }

    // ── Operations hub ──────────────────────────────────────────────

    public function test_security_does_not_get_the_directory_tab(): void
    {
        Livewire::actingAs($this->as(UserRole::Security))
            ->test(CommunityOperationsHub::class)
            ->assertDontSee('Resident Directory')
            ->call('setTab', 'directory')
            ->assertSet('activeTab', 'overview');
    }

    public function test_a_directory_link_opens_the_overview_for_security(): void
    {
        Livewire::actingAs($this->as(UserRole::Security))
            ->withQueryParams(['tab' => 'directory'])
            ->test(CommunityOperationsHub::class)
            ->assertSet('activeTab', 'overview');
    }

    public function test_an_administrator_gets_the_directory_tab(): void
    {
        Livewire::actingAs($this->as(UserRole::Admin))
            ->test(CommunityOperationsHub::class)
            ->assertSee('Resident Directory')
            ->call('setTab', 'directory')
            ->assertSet('activeTab', 'directory');
    }

    // ── Pass manager ────────────────────────────────────────────────

    public function test_a_resident_cannot_load_the_pass_manager(): void
    {
        Livewire::actingAs($this->as(UserRole::Homeowner))->test(PassManager::class)->assertForbidden();
    }

    public function test_a_pass_is_issued_by_the_guard_who_creates_it(): void
    {
        $guard = $this->as(UserRole::Security);
        $this->as(UserRole::SystemAdmin);

        Livewire::actingAs($guard)
            ->test(PassManager::class)
            ->set('holder_name', 'Courier Dave')
            ->call('createPass')
            ->assertHasNoErrors();

        $this->assertSame($guard->id, GatePass::where('holder_name', 'Courier Dave')->sole()->user_id);
    }

    public function test_sorting_ignores_columns_the_view_does_not_offer(): void
    {
        $component = Livewire::actingAs($this->as(UserRole::Security))
            ->test(PassManager::class)
            ->call('sortBy', 'password')
            ->assertSet('sortField', 'created_at');

        $this->expectException(\Exception::class);
        $component->set('sortField', 'user_id');
    }

    // ── Resident directory ──────────────────────────────────────────

    public function test_security_cannot_load_the_resident_directory(): void
    {
        Livewire::actingAs($this->as(UserRole::Security))->test(ResidentDirectory::class)->assertForbidden();
    }

    public function test_an_admin_cannot_create_a_system_admin(): void
    {
        Livewire::actingAs($this->as(UserRole::Admin))
            ->test(ResidentDirectory::class)
            ->set('name', 'Mallory Root')
            ->set('email', 'mallory@example.com')
            ->set('role', UserRole::SystemAdmin->value)
            ->set('lot', 'Lot 1')
            ->call('createResident')
            ->assertHasErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'mallory@example.com']);
    }

    public function test_an_unknown_role_is_rejected(): void
    {
        Livewire::actingAs($this->as(UserRole::Admin))
            ->test(ResidentDirectory::class)
            ->set('name', 'Odd Role')
            ->set('email', 'odd@example.com')
            ->set('role', 'Overlord')
            ->set('lot', 'Lot 1')
            ->call('createResident')
            ->assertHasErrors('role');
    }

    public function test_a_created_resident_can_sign_in(): void
    {
        Livewire::actingAs($this->as(UserRole::Admin))
            ->test(ResidentDirectory::class)
            ->set('name', 'New Neighbour')
            ->set('email', 'new@example.com')
            ->set('role', UserRole::Homeowner->value)
            ->set('lot', 'Lot 9')
            ->call('createResident')
            ->assertHasNoErrors();

        $this->assertTrue(User::where('email', 'new@example.com')->sole()->isActive());
    }

    public function test_deactivating_and_reactivating_a_resident(): void
    {
        $resident = $this->as(UserRole::Homeowner);
        $resident->createToken('Phone');
        $component = Livewire::actingAs($this->as(UserRole::Admin))->test(ResidentDirectory::class);

        $component->call('toggleUserStatus', $resident->id);
        $resident->refresh();
        $this->assertSame('Inactive', $resident->status);
        $this->assertNotNull($resident->deactivated_at);
        $this->assertFalse($resident->isActive());
        $this->assertCount(0, $resident->tokens);

        $component->call('toggleUserStatus', $resident->id);
        $this->assertTrue($resident->fresh()->isActive());
    }

    public function test_an_admin_cannot_deactivate_a_system_admin_or_themselves(): void
    {
        $admin = $this->as(UserRole::Admin);
        $root = $this->as(UserRole::SystemAdmin);

        Livewire::actingAs($admin)
            ->test(ResidentDirectory::class)
            ->call('toggleUserStatus', $root->id)
            ->call('toggleUserStatus', $admin->id);

        $this->assertTrue($root->fresh()->isActive());
        $this->assertTrue($admin->fresh()->isActive());
    }

    // ── Warning desk ────────────────────────────────────────────────

    public function test_a_resident_cannot_delete_an_alert(): void
    {
        $warning = Warning::create([
            'title' => 'Loose dog on Palm Blvd',
            'description' => 'Large brown dog seen near the park gate.',
            'author_id' => $this->as(UserRole::Homeowner)->id,
            'author_name' => 'Resident',
            'issued_at' => now(),
        ]);

        Livewire::actingAs($this->as(UserRole::Homeowner))
            ->test(WarningDesk::class)
            ->assertDontSeeHtml('wire:click="deleteWarning')
            ->call('deleteWarning', $warning->id)
            ->assertForbidden();

        $this->assertModelExists($warning);
    }

    public function test_security_can_delete_an_alert(): void
    {
        $warning = Warning::create([
            'title' => 'False alarm at gate 2',
            'description' => 'Reported intruder was a delivery driver.',
            'author_id' => $this->as(UserRole::Homeowner)->id,
            'author_name' => 'Resident',
            'issued_at' => now(),
        ]);

        Livewire::actingAs($this->as(UserRole::Security))
            ->test(WarningDesk::class)
            ->call('deleteWarning', $warning->id);

        $this->assertModelMissing($warning);
    }

    public function test_raising_alerts_is_rate_limited(): void
    {
        $component = Livewire::actingAs($this->as(UserRole::Homeowner))->test(WarningDesk::class);

        foreach (range(1, 4) as $n) {
            $component->set('title', "Alert number {$n}")
                ->set('description', 'Something worth telling the estate about.')
                ->call('createWarning');
        }

        $this->assertSame(3, Warning::count());
    }

    // ── Search and the query API ────────────────────────────────────

    public function test_a_resident_searching_finds_no_other_residents_or_passes(): void
    {
        User::factory()->role(UserRole::Homeowner)->create(['name' => 'Zelda Neighbour', 'email' => 'zelda@example.com']);
        $this->pass();

        $response = $this->actingAs($this->as(UserRole::Homeowner))
            ->getJson('/api/search?q=Zelda')
            ->assertOk();

        $this->assertSame([], $response->json('data.results.users'));
        $this->assertSame([], $response->json('data.results.gate_passes'));
        $this->assertSame([], $response->json('data.results.transactions'));
        $this->assertSame([], $response->json('data.results.visitors'));
    }

    public function test_an_administrator_can_search_residents(): void
    {
        User::factory()->role(UserRole::Homeowner)->create(['name' => 'Zelda Neighbour', 'email' => 'zelda@example.com']);

        $this->actingAs($this->as(UserRole::Admin))
            ->getJson('/api/search?q=Zelda&types[]=users')
            ->assertOk()
            ->assertJsonPath('data.results.users.0.title', 'Zelda Neighbour');
    }

    public function test_the_query_api_needs_a_token(): void
    {
        $this->getJson('/api/v1/users')->assertUnauthorized();
        $this->getJson('/api/v1/query-meta')->assertUnauthorized();
    }

    /** @return array<string, array{string}> */
    public static function restrictedEntities(): array
    {
        return [
            'users' => ['/api/v1/users'],
            'transactions' => ['/api/v1/transactions'],
            'gate passes' => ['/api/v1/gate-passes'],
            'visitors' => ['/api/v1/visitors'],
            'users by entity' => ['/api/v1/query/users'],
        ];
    }

    #[DataProvider('restrictedEntities')]
    public function test_a_residents_token_cannot_list_estate_records(string $uri): void
    {
        $resident = $this->as(UserRole::Homeowner);
        $token = $resident->createToken('Phone')->plainTextToken;

        $this->getJson($uri, ['Authorization' => "Bearer {$token}"])->assertForbidden();
    }

    public function test_a_residents_token_can_list_community_alerts(): void
    {
        $token = $this->as(UserRole::Homeowner)->createToken('Phone')->plainTextToken;

        $this->getJson('/api/v1/warnings', ['Authorization' => "Bearer {$token}"])->assertOk();
    }

    public function test_query_meta_lists_only_what_the_token_may_query(): void
    {
        $token = $this->as(UserRole::Homeowner)->createToken('Phone')->plainTextToken;

        $this->getJson('/api/v1/query-meta', ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonPath('supported_entities', ['warnings']);
    }
}
