<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Household;
use App\Models\User;
use App\Services\DigitalAccessWalletService;
use App\Services\HouseholdManagementService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DigitalAccessWalletTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_digital_access_wallet_with_active_credential(): void
    {
        $homeowner = User::factory()->create([
            'role' => UserRole::Homeowner,
            'name' => 'John Smith',
            'lot' => '14',
        ]);

        $response = $this->actingAs($homeowner)->get('/dashboard/wallet');
        $response->assertOk();

        // Fresh JSON credentials endpoint
        $jsonResponse = $this->actingAs($homeowner)->getJson('/dashboard/wallet/credentials');
        $jsonResponse->assertOk()
            ->assertJsonStructure([
                'credentials',
                'refreshed_at',
            ]);
    }

    public function test_homeowner_sees_all_household_credentials_in_wallet_roster(): void
    {
        $homeowner = User::factory()->create([
            'role' => UserRole::Homeowner,
            'name' => 'John Smith',
            'lot' => '14',
        ]);

        // Seed the 5-person Smith household
        app(HouseholdManagementService::class)->seedExampleSmithHousehold($homeowner);

        $walletService = app(DigitalAccessWalletService::class);
        $credentials = $walletService->getWalletCredentials($homeowner);

        // Verify all 5 members are present with appropriate multi-modal credentials
        $this->assertGreaterThanOrEqual(5, $credentials->count());

        $names = $credentials->pluck('holder_name')->toArray();
        $this->assertContains('John Smith', $names);
        $this->assertContains('Mary Smith', $names);
        $this->assertContains('Alex Smith', $names);
        $this->assertContains('James Smith', $names);
        $this->assertContains('Maria Smith', $names);

        // Check Mary Smith has Spouse credential
        $mary = $credentials->firstWhere('holder_name', 'Mary Smith');
        $this->assertNotNull($mary);
        $this->assertNotNull($mary['qr']['token']);
        $this->assertNotNull($mary['nfc']['card_uid']);
        $this->assertStringStartsWith('04:', $mary['nfc']['card_uid']);
        $this->assertNotNull($mary['wallet_integrations']['apple_wallet']['download_url']);

        // Check Alex Smith has child curfew
        $alex = $credentials->firstWhere('holder_name', 'Alex Smith');
        $this->assertNotNull($alex);
        $this->assertTrue($alex['schedule']['curfew_enabled']);

        // Check Maria Smith has caregiver shift schedule
        $maria = $credentials->firstWhere('holder_name', 'Maria Smith');
        $this->assertNotNull($maria);
        $this->assertIsArray($maria['schedule']['days']);
        $this->assertContains('Monday', $maria['schedule']['days']);
    }

    // The signed passes themselves are covered in WalletPassesTest. These pin
    // that the head of household gets past authorization for a member's pass
    // (404 "not set up", not 403) while the estate has no wallet credentials.

    public function test_apple_wallet_pass_is_not_offered_until_configured(): void
    {
        $homeowner = User::factory()->create([
            'role' => UserRole::Homeowner,
            'name' => 'John Smith',
            'lot' => '14',
        ]);

        $household = app(HouseholdManagementService::class)->seedExampleSmithHousehold($homeowner);
        $maryPass = $household->members->firstWhere('name', 'Mary Smith')->gatePass;

        config(['wallet.apple.pass_type_identifier' => null]);

        $this->actingAs($homeowner)->getJson("/dashboard/wallet/apple-pass/{$maryPass->pass_id}")
            ->assertNotFound()
            ->assertJsonPath('message', 'Apple Wallet passes are not set up for this estate yet.');
    }

    public function test_google_wallet_pass_is_not_offered_until_configured(): void
    {
        $homeowner = User::factory()->create([
            'role' => UserRole::Homeowner,
            'name' => 'John Smith',
            'lot' => '14',
        ]);

        $household = app(HouseholdManagementService::class)->seedExampleSmithHousehold($homeowner);
        $pass = $household->members->firstWhere('name', 'James Smith')->gatePass;

        config(['wallet.google.issuer_id' => null]);

        $this->actingAs($homeowner)->getJson("/dashboard/wallet/google-pass/{$pass->pass_id}")
            ->assertNotFound()
            ->assertJsonPath('message', 'Google Wallet passes are not set up for this estate yet.');
    }

    public function test_samsung_wallet_pass_is_not_available(): void
    {
        $homeowner = User::factory()->create([
            'role' => UserRole::Homeowner,
            'name' => 'John Smith',
            'lot' => '14',
        ]);

        $household = app(HouseholdManagementService::class)->seedExampleSmithHousehold($homeowner);
        $pass = $household->members->firstWhere('name', 'John Smith')->gatePass;

        $this->actingAs($homeowner)->getJson("/dashboard/wallet/samsung-pass/{$pass->pass_id}")
            ->assertNotFound()
            ->assertJsonPath('message', 'Samsung Wallet is not available.');
    }

    public function test_simulated_nfc_tap_authenticates_gate_clearance(): void
    {
        $homeowner = User::factory()->create([
            'role' => UserRole::Homeowner,
            'name' => 'John Smith',
            'lot' => '14',
        ]);

        $household = app(HouseholdManagementService::class)->seedExampleSmithHousehold($homeowner);
        $pass = $household->members->firstWhere('name', 'John Smith')->gatePass;

        $walletService = app(DigitalAccessWalletService::class);
        $cred = $walletService->formatWalletPass($pass, 'Homeowner');

        $response = // The tap is a gate officer's: the simulator runs the real scanner (can:scanPasses).
        $this->actingAs(User::factory()->create(['role' => UserRole::Security]))->postJson('/dashboard/wallet/simulate-nfc-tap', [
            'payload' => $cred['nfc']['payload'],
            'gate' => 'GATE-01',
        ]);

        $response->assertOk()
            ->assertJsonPath('decision', 'CHECK_IN');

        // Verify access log records method as 'NFC Contactless Tap'
        $this->assertDatabaseHas('access_log_entries', [
            'pass_id' => $pass->pass_id,
            'method' => 'NFC Contactless Tap',
            'result' => 'ALLOW',
        ]);
    }

    public function test_curfew_enforcement_on_child_pass_at_night(): void
    {
        $homeowner = User::factory()->create([
            'role' => UserRole::Homeowner,
            'name' => 'John Smith',
            'lot' => '14',
        ]);

        $household = app(HouseholdManagementService::class)->seedExampleSmithHousehold($homeowner);
        $alexMember = $household->members->firstWhere('name', 'Alex Smith');
        $alexPass = $alexMember->gatePass;

        $walletService = app(DigitalAccessWalletService::class);
        $cred = $walletService->formatWalletPass($alexPass, 'Child', null, $alexMember);

        // Forward to 10:30 PM tomorrow, outside the 6:00 AM – 9:00 PM curfew. Not a
        // fixed past date: household passes are valid from when they are issued.
        Carbon::setTestNow(now()->addDay()->setTime(22, 30));

        $response = // The tap is a gate officer's: the simulator runs the real scanner (can:scanPasses).
        $this->actingAs(User::factory()->create(['role' => UserRole::Security]))->postJson('/dashboard/wallet/simulate-nfc-tap', [
            'payload' => $cred['nfc']['payload'],
            'gate' => 'GATE-01',
        ]);

        $response->assertOk()
            ->assertJsonPath('decision', 'REJECT')
            ->assertJsonPath('report.status', 'REJECTED');

        $this->assertStringContainsString('Curfew restriction', $response->json('report.primaryReason'));

        Carbon::setTestNow(); // reset
    }

    public function test_schedule_enforcement_on_caregiver_pass(): void
    {
        $homeowner = User::factory()->create([
            'role' => UserRole::Homeowner,
            'name' => 'John Smith',
            'lot' => '14',
        ]);

        $household = app(HouseholdManagementService::class)->seedExampleSmithHousehold($homeowner);
        $mariaMember = $household->members->firstWhere('name', 'Maria Smith');
        $mariaPass = $mariaMember->gatePass;

        $walletService = app(DigitalAccessWalletService::class);
        $cred = $walletService->formatWalletPass($mariaPass, 'Caregiver', null, $mariaMember);

        // Next Saturday, outside the Mon-Fri authorized days. Not a fixed date:
        // household passes are valid from when they are issued, so a date at
        // or before today is refused as not yet valid instead.
        Carbon::setTestNow(now()->next(Carbon::SATURDAY)->setTime(10, 0));

        $response = // The tap is a gate officer's: the simulator runs the real scanner (can:scanPasses).
        $this->actingAs(User::factory()->create(['role' => UserRole::Security]))->postJson('/dashboard/wallet/simulate-nfc-tap', [
            'payload' => $cred['nfc']['payload'],
            'gate' => 'GATE-01',
        ]);

        $response->assertOk()
            ->assertJsonPath('decision', 'REJECT')
            ->assertJsonPath('report.status', 'REJECTED');

        $this->assertStringContainsString('Access not permitted on Saturday', $response->json('report.primaryReason'));

        Carbon::setTestNow(); // reset
    }
}
