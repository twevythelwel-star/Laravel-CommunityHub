<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Renter;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeownerAndTemporaryHomeownerFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_homeowner_login_and_dashboard_access(): void
    {
        $homeowner = User::factory()->create([
            'role' => UserRole::Homeowner,
            'lot' => 'Lot 14',
            'street' => 'Hibiscus Way',
            'status' => 'Active',
            'password' => bcrypt('password'),
        ]);

        $response = $this->post('/login', [
            'email' => $homeowner->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard.index'));
        $this->assertAuthenticatedAs($homeowner);
    }

    public function test_homeowner_can_view_calendar_with_entry_slots(): void
    {
        $homeowner = User::factory()->create([
            'role' => UserRole::Homeowner,
            'status' => 'Active',
        ]);

        $response = $this->actingAs($homeowner)->get('/dashboard/calendar');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard/Calendar')
            ->has('entrySlots')
            ->where('canRegisterVisitors', true)
        );
    }

    public function test_homeowner_can_register_manage_and_remove_temporary_homeowner(): void
    {
        $homeowner = User::factory()->create([
            'role' => UserRole::Homeowner,
            'lot' => 'Lot 14',
            'street' => 'Hibiscus Way',
            'status' => 'Active',
        ]);

        // 1. Homeowner views renters page
        $response = $this->actingAs($homeowner)->get('/dashboard/renters');
        $response->assertOk();

        // 2. Homeowner registers a long-term renter
        $registerLongTerm = $this->actingAs($homeowner)->post('/dashboard/renters', [
            'name' => 'Sophia Taylor',
            'stay_type' => 'Long-term (Renter)',
            'contact' => 'sophia@example.com',
            'notes' => 'Lease contract signed for 1 year.',
            'lease_start' => now()->toDateString(),
            'lease_end' => now()->addYear()->toDateString(),
        ]);
        $registerLongTerm->assertSessionHasNoErrors();
        $registerLongTerm->assertRedirect();

        $this->assertDatabaseHas('renters', [
            'homeowner_id' => $homeowner->id,
            'name' => 'Sophia Taylor',
            'stay_type' => 'Long-term (Renter)',
            'status' => 'Active',
        ]);

        // 3. Homeowner registers a short-term Airbnb guest
        $registerShortTerm = $this->actingAs($homeowner)->post('/dashboard/renters', [
            'name' => 'Elena Rostova',
            'stay_type' => 'Short-term (Airbnb)',
            'contact' => '+1 (555) 392-1084',
            'notes' => 'Airbnb reservation #AB-9921',
            'lease_start' => now()->toDateString(),
            'lease_end' => now()->addDays(5)->toDateString(),
        ]);
        $registerShortTerm->assertSessionHasNoErrors();

        $this->assertDatabaseHas('renters', [
            'homeowner_id' => $homeowner->id,
            'name' => 'Elena Rostova',
            'stay_type' => 'Short-term (Airbnb)',
        ]);

        $renter = Renter::where('name', 'Elena Rostova')->first();

        // 4. Homeowner updates temporary homeowner stay timeframe
        $updateResponse = $this->actingAs($homeowner)->put("/dashboard/renters/{$renter->id}", [
            'name' => 'Elena Rostova',
            'stay_type' => 'Short-term (Airbnb)',
            'lease_start' => now()->toDateString(),
            'lease_end' => now()->addDays(7)->toDateString(),
            'notes' => 'Extended stay by 2 days.',
        ]);
        $updateResponse->assertSessionHasNoErrors();
        $this->assertEquals(now()->addDays(7)->toDateString(), $renter->fresh()->lease_end->toDateString());

        // 5. Homeowner removes temporary homeowner
        $deleteResponse = $this->actingAs($homeowner)->delete("/dashboard/renters/{$renter->id}");
        $deleteResponse->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('renters', ['id' => $renter->id]);
    }

    public function test_homeowner_can_register_edit_and_delete_visitors(): void
    {
        $homeowner = User::factory()->create([
            'role' => UserRole::Homeowner,
            'status' => 'Active',
        ]);

        // Register one-time visitor
        $storeResponse = $this->actingAs($homeowner)->post('/dashboard/visitors', [
            'name' => 'Michael Scott',
            'type' => 'One-time',
            'expected_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'contact' => 'michael@dunder.com',
            'vehicle' => 'Silver Sebring',
        ]);
        $storeResponse->assertSessionHasNoErrors();

        $visitor = Visitor::where('name', 'Michael Scott')->first();
        $this->assertNotNull($visitor);
        $this->assertEquals($homeowner->id, $visitor->homeowner_id);

        // Edit visitor information
        $updateResponse = $this->actingAs($homeowner)->put("/dashboard/visitors/{$visitor->id}", [
            'name' => 'Michael G. Scott',
            'type' => 'Recurring',
            'expected_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'date_range' => now()->addDays(2)->toDateString().' to '.now()->addDays(5)->toDateString(),
        ]);
        $updateResponse->assertSessionHasNoErrors();
        $this->assertEquals('Michael G. Scott', $visitor->fresh()->name);
        $this->assertEquals('Recurring', $visitor->fresh()->type);

        // Delete visitor
        $deleteResponse = $this->actingAs($homeowner)->delete("/dashboard/visitors/{$visitor->id}");
        $deleteResponse->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('visitors', ['id' => $visitor->id]);
    }

    public function test_temporary_homeowner_cannot_access_renters_management(): void
    {
        $tempHomeowner = User::factory()->create([
            'role' => UserRole::TemporaryHomeowner,
            'status' => 'Active',
        ]);

        // Temporary Homeowners cannot view or register new temporary homeowners
        $this->actingAs($tempHomeowner)->get('/dashboard/renters')->assertForbidden();
        $this->actingAs($tempHomeowner)->post('/dashboard/renters', [
            'name' => 'Unauthorized Guest',
            'stay_type' => 'Short-term (Airbnb)',
            'lease_start' => now()->toDateString(),
            'lease_end' => now()->addDays(3)->toDateString(),
        ])->assertForbidden();
    }

    public function test_temporary_homeowner_visitor_management_within_allowed_timeframe(): void
    {
        $homeowner = User::factory()->create([
            'role' => UserRole::Homeowner,
            'status' => 'Active',
        ]);

        $tempUser = User::factory()->create([
            'role' => UserRole::TemporaryHomeowner,
            'status' => 'Active',
        ]);

        // Lease valid from today until 10 days from now
        $stay = Renter::create([
            'homeowner_id' => $homeowner->id,
            'user_id' => $tempUser->id,
            'name' => $tempUser->display_name,
            'stay_type' => 'Short-term (Airbnb)',
            'lease_start' => now()->toDateString(),
            'lease_end' => now()->addDays(10)->toDateString(),
            'status' => 'Active',
        ]);

        // 1. Register visitor within timeframe: SUCCESS
        $validArrival = now()->addDays(3)->format('Y-m-d H:i:s');
        $validResponse = $this->actingAs($tempUser)->post('/dashboard/visitors', [
            'name' => 'David Wallace',
            'type' => 'One-time',
            'expected_at' => $validArrival,
        ]);
        $validResponse->assertSessionHasNoErrors();
        $this->assertDatabaseHas('visitors', [
            'homeowner_id' => $tempUser->id,
            'name' => 'David Wallace',
        ]);

        $visitor = Visitor::where('name', 'David Wallace')->first();

        // 2. Register visitor outside timeframe (e.g., 15 days later): REJECTED
        $invalidArrival = now()->addDays(15)->format('Y-m-d H:i:s');
        $invalidResponse = $this->actingAs($tempUser)->post('/dashboard/visitors', [
            'name' => 'Jan Levinson',
            'type' => 'One-time',
            'expected_at' => $invalidArrival,
        ]);
        $invalidResponse->assertSessionHasErrors('expected_at');
        $this->assertDatabaseMissing('visitors', ['name' => 'Jan Levinson']);

        // 3. Edit visitor to valid date within timeframe: SUCCESS
        $editValid = $this->actingAs($tempUser)->put("/dashboard/visitors/{$visitor->id}", [
            'name' => 'David Wallace Jr.',
            'expected_at' => now()->addDays(5)->format('Y-m-d H:i:s'),
        ]);
        $editValid->assertSessionHasNoErrors();
        $this->assertEquals('David Wallace Jr.', $visitor->fresh()->name);

        // 4. Edit visitor to date outside timeframe: REJECTED
        $editInvalid = $this->actingAs($tempUser)->put("/dashboard/visitors/{$visitor->id}", [
            'expected_at' => now()->addDays(20)->format('Y-m-d H:i:s'),
        ]);
        $editInvalid->assertSessionHasErrors('expected_at');
    }

    public function test_temporary_homeowner_access_automatically_expires_when_end_date_passes(): void
    {
        $homeowner = User::factory()->create(['role' => UserRole::Homeowner]);

        $tempUser = User::factory()->create([
            'role' => UserRole::TemporaryHomeowner,
            'status' => 'Active',
            'password' => bcrypt('password'),
        ]);

        // Stay ended yesterday
        $stay = Renter::create([
            'homeowner_id' => $homeowner->id,
            'user_id' => $tempUser->id,
            'name' => $tempUser->display_name,
            'stay_type' => 'Short-term (Airbnb)',
            'lease_start' => now()->subDays(7)->toDateString(),
            'lease_end' => now()->subDay()->toDateString(),
            'status' => 'Active',
        ]);

        // Login should be rejected due to expiration
        $loginResponse = $this->post('/login', [
            'email' => $tempUser->email,
            'password' => 'password',
        ]);
        $loginResponse->assertSessionHasErrors('email');
        $this->assertGuest();

        // If an active session tries to navigate, middleware logs them out
        $sessionResponse = $this->actingAs($tempUser)->get('/dashboard');
        $sessionResponse->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_logout_works_for_both_homeowner_and_temporary_homeowner(): void
    {
        $homeowner = User::factory()->create(['role' => UserRole::Homeowner]);
        $response = $this->actingAs($homeowner)->post('/logout');
        $response->assertRedirect(route('landing'));
        $this->assertGuest();

        $tempUser = User::factory()->create(['role' => UserRole::TemporaryHomeowner]);
        $response2 = $this->actingAs($tempUser)->post('/logout');
        $response2->assertRedirect(route('landing'));
        $this->assertGuest();
    }
}
