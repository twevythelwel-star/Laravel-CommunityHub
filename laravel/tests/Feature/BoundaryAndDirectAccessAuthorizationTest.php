<?php

namespace Tests\Feature;

use App\Enums\PassCategory;
use App\Enums\PassStatus;
use App\Enums\UserRole;
use App\Models\BoundaryConfig;
use App\Models\Community;
use App\Models\DelegatedAccess;
use App\Models\GatePass;
use App\Models\Invoice;
use App\Models\Property;
use App\Models\Renter;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\VehicleManagementService;
use Database\Seeders\CompanyPropertySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoundaryAndDirectAccessAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function vehicleFor(User $owner, array $attributes = []): Vehicle
    {
        return app(VehicleManagementService::class)->registerVehicle($owner, $attributes + [
            'license_plate' => 'AB '.fake()->unique()->numerify('####'),
            'make' => 'Toyota',
            'model' => 'Corolla',
            'color' => 'Silver',
        ]);
    }

    // ──────────────────────────────────────────────────────────────────
    // 1. PROPERTY BOUNDARIES: Direct Requests & Cross-Property Isolation
    // ──────────────────────────────────────────────────────────────────

    public function test_direct_request_for_unauthorized_unit_occupancy_is_forbidden(): void
    {
        $resident = User::factory()->role(UserRole::Homeowner)->create(['lot' => 'Unit 10']);

        $response = $this->actingAs($resident)->getJson('/dashboard/occupancy/unit/Unit 22');

        $response->assertForbidden();
    }

    public function test_direct_request_to_company_dashboard_for_unauthorized_property_is_forbidden(): void
    {
        $this->seed(CompanyPropertySeeder::class);

        // Mark Vance is authorized only for Unit 14 and Unit 18, NOT Unit 22
        $manager = User::where('email', 'mark.vance@abcholdings.com')->firstOrFail();
        $unit22 = Property::where('lot_number', 'Unit 22')->firstOrFail();

        $response = $this->actingAs($manager)->get('/dashboard/company?property_id='.$unit22->id);

        $response->assertForbidden();
    }

    public function test_direct_request_to_issue_parking_pass_for_another_users_vehicle_is_forbidden(): void
    {
        $userA = User::factory()->role(UserRole::Homeowner)->create();
        $userB = User::factory()->role(UserRole::Homeowner)->create();

        $vehicleB = $this->vehicleFor($userB);

        $response = $this->actingAs($userA)->post('/dashboard/parking', [
            'category' => 'resident',
            'holder_name' => $userA->name,
            'license_plate' => $vehicleB->license_plate,
            'vehicle_id' => $vehicleB->id,
            'duration_hours' => 24,
        ]);

        $response->assertForbidden();
    }

    public function test_direct_request_to_issue_loading_zone_or_accessible_pass_by_resident_is_forbidden(): void
    {
        $resident = User::factory()->role(UserRole::Homeowner)->create();
        $vehicle = $this->vehicleFor($resident);

        $response = $this->actingAs($resident)->post('/dashboard/parking', [
            'category' => 'loading_zone',
            'holder_name' => $resident->name,
            'license_plate' => $vehicle->license_plate,
            'vehicle_id' => $vehicle->id,
            'duration_hours' => 1,
        ]);

        $response->assertForbidden();
    }

    public function test_direct_request_to_download_another_users_gate_pass_pdf_is_forbidden(): void
    {
        $userA = User::factory()->role(UserRole::Homeowner)->create();
        $userB = User::factory()->role(UserRole::Homeowner)->create();

        $passB = GatePass::create([
            'pass_id' => 'GP-TEST-001',
            'user_id' => $userB->id,
            'holder_name' => $userB->name,
            'category' => PassCategory::Homeowner,
            'status' => PassStatus::Active,
            'secret_hash' => hash('sha256', 'secret-key'),
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addDays(7),
        ]);

        $response = $this->actingAs($userA)->get("/dashboard/gate-pass/pdf/{$passB->id}");

        $response->assertForbidden();
    }

    public function test_direct_request_to_checkout_another_users_invoice_is_forbidden(): void
    {
        $userA = User::factory()->role(UserRole::Homeowner)->create();
        $userB = User::factory()->role(UserRole::Homeowner)->create();

        $invoiceB = Invoice::create([
            'reference' => 'INV-TEST-999',
            'user_id' => $userB->id,
            'amount_minor' => 15000,
            'status' => 'issued',
            'currency' => 'USD',
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'due_on' => now()->addMonth(),
        ]);

        $response = $this->actingAs($userA)->post("/dashboard/billing/invoices/{$invoiceB->id}/stripe-checkout");

        $response->assertForbidden();
    }

    public function test_direct_request_to_save_continuity_plan_by_renter_is_forbidden(): void
    {
        $renter = User::factory()->role(UserRole::TemporaryHomeowner)->create();
        Renter::create([
            'name' => $renter->name,
            'user_id' => $renter->id,
            'stay_type' => 'long_term',
            'lease_start' => now()->subMonth(),
            'lease_end' => now()->addMonths(11),
        ]);

        $response = $this->actingAs($renter)->post('/dashboard/delegation/continuity-plan', [
            'activation_conditions' => ['medical_emergency'],
            'required_verification' => 'admin_verification',
            'authorized_actions' => ['gate_access'],
            'max_duration_days' => 30,
            'requires_admin_approval' => true,
            'notify_homeowner_on_trigger' => true,
            'notify_community_security' => true,
        ]);

        $response->assertForbidden();
    }

    public function test_direct_request_by_renter_to_create_homeowner_credential_or_short_term_rental_is_forbidden(): void
    {
        $renter = User::factory()->role(UserRole::TemporaryHomeowner)->create();
        Renter::create([
            'name' => $renter->name,
            'user_id' => $renter->id,
            'stay_type' => 'long_term',
            'lease_start' => now()->subMonth(),
            'lease_end' => now()->addMonths(11),
        ]);

        $property = Property::create([
            'property_code' => 'PROP-RENTER',
            'lot_number' => 'Lot R',
            'owner_user_id' => $renter->id,
        ]);

        $response = $this->actingAs($renter)->post('/dashboard/delegation', [
            'property_id' => $property->id,
            'name' => 'Unauthorized Guest',
            'email' => 'guest@example.com',
            'phone' => '876-555-0199',
            'relationship' => 'Homeowner',
            'access_level' => 'Homeowner',
            'duration_type' => 'date_range',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
        ]);

        $response->assertForbidden();
    }

    public function test_direct_request_to_create_delegation_for_unauthorized_property_is_forbidden(): void
    {
        $ownerA = User::factory()->role(UserRole::Homeowner)->create();
        $ownerB = User::factory()->role(UserRole::Homeowner)->create();
        $propertyB = Property::create([
            'property_code' => 'PROP-B',
            'lot_number' => 'Lot B',
            'owner_user_id' => $ownerB->id,
        ]);

        $response = $this->actingAs($ownerA)->post('/dashboard/delegation', [
            'property_id' => $propertyB->id,
            'name' => 'Intruder Access',
            'email' => 'intruder@example.com',
            'phone' => '876-555-0101',
            'relationship' => 'Guest',
            'access_level' => 'Visitor',
            'duration_type' => 'date_range',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
        ]);

        $response->assertForbidden();
    }

    public function test_direct_request_by_short_term_guest_to_update_host_delegation_rules_is_forbidden(): void
    {
        $host = User::factory()->role(UserRole::Homeowner)->create();
        $guest = User::factory()->role(UserRole::TemporaryHomeowner)->create();
        Renter::create([
            'name' => $guest->name,
            'user_id' => $guest->id,
            'stay_type' => 'short_term',
            'notes' => 'airbnb guest',
            'lease_start' => now()->subDay(),
            'lease_end' => now()->addDays(4),
        ]);

        $hostDelegation = DelegatedAccess::create([
            'grantor_user_id' => $host->id,
            'name' => 'Host Property Pass',
            'email' => 'service@example.com',
            'relationship' => 'Contractor',
            'access_level' => 'Maintenance',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'invite_token' => 'token-12345678901234567890123456789012',
            'status' => 'active',
        ]);

        $response = $this->actingAs($guest)->patch("/dashboard/delegation/{$hostDelegation->id}/rules", [
            'access_rules' => ['full_property_access'],
        ]);

        $response->assertForbidden();
    }

    public function test_direct_request_to_assign_directory_property_by_renter_is_forbidden(): void
    {
        $renter = User::factory()->role(UserRole::TemporaryHomeowner)->create();
        $targetUser = User::factory()->role(UserRole::TemporaryHomeowner)->create();
        $property = Property::create([
            'property_code' => 'PROP-DIR',
            'lot_number' => 'Lot D',
        ]);

        $response = $this->actingAs($renter)->post("/dashboard/directory/users/{$targetUser->id}/properties", [
            'property_id' => $property->id,
        ]);

        $response->assertForbidden();
    }

    // ──────────────────────────────────────────────────────────────────
    // 2. COMMUNITY BOUNDARIES: Boundaries, Geofences & Cross-Community
    // ──────────────────────────────────────────────────────────────────

    public function test_direct_request_to_replace_or_destroy_community_boundary_by_non_admin_is_forbidden(): void
    {
        $community = Community::default();
        $resident = User::factory()->role(UserRole::Homeowner)->create();

        $boundaryConfig = $community->boundaryConfigs()->create([
            'version' => 1,
            'status' => 'SUPERSEDED',
            'published_coordinates' => [[18.4742, -77.9284], [18.4748, -77.9241], [18.4771, -77.9232]],
            'last_published_at' => now(),
            'last_published_by' => 'Admin',
        ]);

        $replaceResponse = $this->actingAs($resident)->post("/dashboard/map/boundary/replace/{$boundaryConfig->id}");
        $replaceResponse->assertForbidden();

        $deleteResponse = $this->actingAs($resident)->delete("/dashboard/map/boundary/{$boundaryConfig->id}");
        $deleteResponse->assertForbidden();
    }

    public function test_direct_request_to_export_community_boundary_without_permission_is_forbidden(): void
    {
        Community::default();
        $resident = User::factory()->role(UserRole::Homeowner)->create();

        $response = $this->actingAs($resident)->get('/dashboard/map/boundary/export');

        $response->assertForbidden();
    }

    public function test_direct_request_to_replace_boundary_belonging_to_another_community_is_denied(): void
    {
        $defaultCommunity = Community::default();
        $otherCommunity = Community::create([
            'name' => 'Other Estate',
            'code' => 'other-estate',
        ]);

        $admin = User::factory()->role(UserRole::SystemAdmin)->create();

        $foreignBoundary = $otherCommunity->boundaryConfigs()->create([
            'version' => 1,
            'status' => 'SUPERSEDED',
            'published_coordinates' => [[18.4742, -77.9284], [18.4748, -77.9241], [18.4771, -77.9232]],
            'last_published_at' => now(),
            'last_published_by' => 'Foreign Admin',
        ]);

        $response = $this->actingAs($admin)->post("/dashboard/map/boundary/replace/{$foreignBoundary->id}");

        $this->assertTrue(in_array($response->status(), [403, 404], true));
    }

    public function test_direct_query_api_request_for_unauthorized_entity_is_forbidden(): void
    {
        $resident = User::factory()->role(UserRole::Homeowner)->create();

        $response = $this->actingAs($resident)->getJson('/api/v1/query/users');

        $response->assertForbidden();
    }
}
