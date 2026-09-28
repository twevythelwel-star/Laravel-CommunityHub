<?php

namespace Tests\Feature;

use App\Enums\GateId;
use App\Enums\PassCategory;
use App\Enums\UserRole;
use App\Enums\VisitorStatus;
use App\Models\AccessLogEntry;
use App\Models\GatePass;
use App\Models\User;
use App\Models\Visitor;
use App\Services\GatePassEngine;
use App\Services\GateScanner;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GateScannerKioskTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_guard_can_access_gate_scanner_kiosk(): void
    {
        $guard = User::factory()->create(['role' => UserRole::Security]);

        $response = $this->actingAs($guard)->get('/dashboard/gate-scanner');
        $response->assertOk();
    }

    public function test_homeowner_cannot_access_gate_scanner_kiosk(): void
    {
        $homeowner = User::factory()->create(['role' => UserRole::Homeowner]);

        $response = $this->actingAs($homeowner)->get('/dashboard/gate-scanner');
        $response->assertForbidden();
    }

    public function test_guard_can_scan_and_confirm_visitor_pass(): void
    {
        $guard = User::factory()->create(['role' => UserRole::Security]);
        $host = User::factory()->create(['role' => UserRole::Homeowner]);
        $visitor = Visitor::create([
            'homeowner_id' => $host->id,
            'name' => 'Toby Flenderson',
            'type' => 'Visitor',
            'contact' => '+18765550205',
            'expected_at' => now()->addHour(),
            'status' => VisitorStatus::Expected,
        ]);

        $gatePass = app(GatePassEngine::class)->issueGuestPass($visitor, $host, PassCategory::Visitor);
        $tokenData = app(GatePassEngine::class)->issueToken($gatePass, GateId::Gate01);

        $scanResponse = $this->actingAs($guard)->postJson('/dashboard/gate-pass/scan', [
            'token' => $tokenData['token'],
            'gate' => 'GATE-01',
        ]);

        $scanResponse->assertOk();
        $scanResponse->assertJsonPath('decision', 'CHECK_IN');
        $scanId = $scanResponse->json('scanId');
        $this->assertNotNull($scanId);

        $confirmResponse = $this->actingAs($guard)->postJson("/dashboard/gate-pass/scans/{$scanId}/confirm", [
            'accept' => true,
        ]);
        $confirmResponse->assertOk();

        $visitor->refresh();
        $this->assertEquals(VisitorStatus::CheckedIn, $visitor->status);
    }

    // ── One row per crossing ──

    /** @return list<array<string, mixed>> */
    private function kioskRows(User $guard): array
    {
        $rows = [];
        $this->actingAs($guard)->get('/dashboard/gate-scanner')
            ->assertInertia(function (Assert $page) use (&$rows) {
                $rows = $page->toArray()['props']['recentScans'];
            });

        return $rows;
    }

    /** @return array{0: User, 1: string} a guard and the scan ID of a resident's pass */
    private function scanResident(string $name = 'Olivia Davis'): array
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-07 10:00:00'));
        $guard = User::factory()->role(UserRole::Security)->create();
        $engine = app(GatePassEngine::class);
        $pass = $engine->issuePassFor(User::factory()->role(UserRole::Homeowner)->create(['display_name' => $name]));
        $scan = app(GateScanner::class)->scan($engine->issueToken($pass)['token'], GateId::Gate02, $guard);

        return [$guard, $scan['scanId']];
    }

    public function test_a_scan_and_its_confirmation_are_one_row_on_the_kiosk(): void
    {
        [$guard, $scanId] = $this->scanResident();

        $pending = $this->kioskRows($guard);
        $this->assertSame(['ALLOW'], array_column($pending, 'result'));

        app(GateScanner::class)->confirm($scanId, $guard);

        $rows = $this->kioskRows($guard);
        $this->assertSame(['CHECK_IN'], array_column($rows, 'result'));
        $this->assertSame('Olivia Davis', $rows[0]['userName']);

        // The access log keeps both: what the pass showed, and what the guard did.
        $this->assertSame(2, AccessLogEntry::count());
        $decision = AccessLogEntry::where('result', 'CHECK_IN')->sole();
        $this->assertSame('ALLOW', $decision->confirms->result);
    }

    public function test_an_officer_refusal_replaces_its_scan_with_the_reason(): void
    {
        [$guard, $scanId] = $this->scanResident();

        app(GateScanner::class)->confirm($scanId, $guard, accept: false, refusalReason: 'ID did not match');

        $rows = $this->kioskRows($guard);
        $this->assertSame(['DENY'], array_column($rows, 'result'));
        $this->assertStringContainsString('ID did not match', $rows[0]['denyReason']);
    }

    public function test_separate_crossings_of_one_pass_stay_separate_rows(): void
    {
        [$guard, $scanId] = $this->scanResident();
        app(GateScanner::class)->confirm($scanId, $guard);

        // Leaving later: a new scan, not yet confirmed.
        $this->travel(5)->minutes();
        $pass = GatePass::sole();
        app(GateScanner::class)->scan(app(GatePassEngine::class)->issueToken($pass->fresh())['token'], GateId::Gate02, $guard);

        $this->assertSame(['ALLOW', 'CHECK_IN'], array_column($this->kioskRows($guard), 'result'));
    }

    public function test_a_rejected_scan_has_nothing_to_confirm_and_stays_one_row(): void
    {
        $guard = User::factory()->role(UserRole::Security)->create();
        app(GateScanner::class)->scan('not-a-real-token', GateId::Gate01, $guard);

        $this->assertSame(['DENY'], array_column($this->kioskRows($guard), 'result'));
    }
}
