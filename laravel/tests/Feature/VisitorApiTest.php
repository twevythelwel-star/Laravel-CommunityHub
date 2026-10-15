<?php

namespace Tests\Feature;

use App\Enums\TokenAbility;
use App\Enums\UserRole;
use App\Models\AccessLogEntry;
use App\Models\BlocklistEntry;
use App\Models\Renter;
use App\Models\User;
use App\Models\Visitor;
use App\Services\GatePassEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The visitor API must hold the same rules as the dashboard: the app and the
 * gate tablets reach the same data through it.
 */
class VisitorApiTest extends TestCase
{
    use RefreshDatabase;

    private function guard(): User
    {
        $guard = User::factory()->role(UserRole::Security)->create();
        Sanctum::actingAs($guard, [TokenAbility::SecurityManage->value]);

        return $guard;
    }

    private function visitor(array $attributes = []): Visitor
    {
        $resident = User::factory()->role(UserRole::Homeowner)->create();

        return Visitor::create([
            'name' => 'Pam Beesly', 'type' => 'One-time', 'status' => 'Expected',
            'expected_at' => now()->addHour(), 'homeowner_id' => $resident->id,
            'homeowner_name' => $resident->display_name,
            ...$attributes,
        ]);
    }

    public function test_a_resident_registers_a_visitor_and_gets_a_pass(): void
    {
        $resident = User::factory()->role(UserRole::Homeowner)->create();
        Sanctum::actingAs($resident, [TokenAbility::VisitorsManage->value]);

        $this->postJson('/api/visitors', ['name' => 'Jim Halpert', 'type' => 'One-time', 'expected_at' => now()->addDay()->toIso8601String()])
            ->assertCreated()
            ->assertJsonStructure(['visitor' => ['id'], 'pass' => ['passId', 'status']]);

        $this->assertSame($resident->id, Visitor::sole()->homeowner_id);
    }

    public function test_a_blocklisted_name_cannot_be_registered(): void
    {
        BlocklistEntry::create(['name' => 'Roy Anderson', 'reason' => 'Altercation', 'severity' => 'Critical', 'date_added' => now()->toDateString(), 'added_by' => 'Security']);
        Sanctum::actingAs(User::factory()->role(UserRole::Homeowner)->create(), [TokenAbility::VisitorsManage->value]);

        $this->postJson('/api/visitors', ['name' => 'Roy Anderson', 'type' => 'One-time', 'expected_at' => now()->addDay()->toIso8601String()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->assertSame(0, Visitor::count());
    }

    public function test_a_temporary_homeowner_cannot_register_outside_their_stay(): void
    {
        $homeowner = User::factory()->role(UserRole::Homeowner)->create();
        $tenant = User::factory()->role(UserRole::TemporaryHomeowner)->create();
        Renter::create([
            'homeowner_id' => $homeowner->id, 'user_id' => $tenant->id, 'name' => $tenant->display_name,
            'stay_type' => 'Short-term (Airbnb)', 'status' => 'Active',
            'lease_start' => now()->toDateString(), 'lease_end' => now()->addDays(3)->toDateString(),
        ]);
        Sanctum::actingAs($tenant, [TokenAbility::VisitorsManage->value]);

        $this->postJson('/api/visitors', ['name' => 'Late Guest', 'type' => 'One-time', 'expected_at' => now()->addDays(10)->toIso8601String()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('expected_at');

        $this->assertSame(0, Visitor::count());
    }

    public function test_check_in_lets_a_cleared_visitor_through(): void
    {
        $visitor = $this->visitor();
        $this->guard();

        $this->postJson("/api/visitors/{$visitor->id}/check-in", ['gate' => 'GATE-01'])
            ->assertOk()
            ->assertJson(['allowed' => true, 'status' => 'Checked In']);
    }

    public function test_check_in_refuses_an_expired_clearance(): void
    {
        $visitor = $this->visitor(['expected_at' => now()->subHours(20), 'expired_at' => now()]);
        $this->guard();

        $this->postJson("/api/visitors/{$visitor->id}/check-in")
            ->assertUnprocessable()
            ->assertJson(['allowed' => false]);

        $this->assertNull($visitor->fresh()->checked_in_at);
    }

    public function test_check_in_refuses_a_blocklisted_visitor_who_holds_a_pass_and_logs_it(): void
    {
        $visitor = $this->visitor(['name' => 'Roy Anderson']);
        app(GatePassEngine::class)->issueGuestPass($visitor, $visitor->homeowner);
        BlocklistEntry::create(['name' => 'Roy Anderson', 'reason' => 'Altercation', 'severity' => 'Critical', 'date_added' => now()->toDateString(), 'added_by' => 'Security']);
        $this->guard();

        $this->postJson("/api/visitors/{$visitor->id}/check-in")
            ->assertUnprocessable()
            ->assertJson(['allowed' => false, 'reason' => 'Denied — this visitor is on the blocklist.']);

        $this->assertNull($visitor->fresh()->checked_in_at);
        $this->assertTrue(AccessLogEntry::where('user_name', 'Roy Anderson')->where('result', 'DENY')->exists());
    }
}
