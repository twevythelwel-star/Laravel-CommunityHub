<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\GateTailgatingDetectionService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GateTailgatingDetectionTest extends TestCase
{
    use RefreshDatabase;

    private GateTailgatingDetectionService $service;

    private User $homeowner;

    private User $guard;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-07 10:00:00'));

        $this->service = app(GateTailgatingDetectionService::class);
        $this->homeowner = User::factory()->role(UserRole::Homeowner)->create([
            'name' => 'John Smith',
            'email' => 'john.smith@community.test',
        ]);
        $this->guard = User::factory()->role(UserRole::Security)->create([
            'name' => 'Officer Rodriguez',
        ]);
    }

    public function test_normal_transit_does_not_flag_tailgating(): void
    {
        $result = $this->service->processGateTransitSequence([
            'gate' => 'GATE-01',
            'direction' => 'in',
            'license_plate' => 'XXX-1234',
            'authorized_occupants' => 1,
            'detected_occupants' => 1,
            'sensor_type' => 'break_beam_matrix',
        ], $this->guard);

        $this->assertTrue($result['success']);
        $this->assertFalse($result['is_tailgating']);
        $this->assertCount(4, $result['sequence']);
        $this->assertEquals('CLEAR', $result['event']->resolution_status);
        $this->assertFalse($result['event']->is_tailgating);
    }

    public function test_flags_tailgating_when_detected_exceeds_authorized(): void
    {
        // 1 person authorized by QR, but camera detects 3 people
        $result = $this->service->processGateTransitSequence([
            'gate' => 'GATE-01',
            'direction' => 'in',
            'pass_id' => 'GP-TEST-001',
            'license_plate' => 'XXX-1234',
            'authorized_occupants' => 1,
            'detected_occupants' => 3,
            'sensor_type' => 'break_beam_matrix',
        ], $this->guard);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['is_tailgating']);
        $this->assertEquals('CRITICAL', $result['severity']);
        $this->assertStringContainsStringIgnoringCase('Possible tailgating event', $result['alert_title']);
        $this->assertStringContainsString('authorized 1 person', $result['alert_message']);
        $this->assertStringContainsString('detected 3 people', $result['alert_message']);

        // Check database
        $this->assertDatabaseHas('gate_sensor_events', [
            'gate' => 'GATE-01',
            'authorized_occupants' => 1,
            'detected_occupants' => 3,
            'is_tailgating' => true,
            'resolution_status' => 'UNRESOLVED',
        ]);

        // Check in-app notification dispatched to security
        $this->assertDatabaseHas('in_app_notifications', [
            'user_id' => $this->guard->id,
            'category' => 'security',
        ]);
    }

    public function test_can_resolve_sensor_alert(): void
    {
        $res = $this->service->processGateTransitSequence([
            'gate' => 'GATE-01',
            'authorized_occupants' => 1,
            'detected_occupants' => 2,
        ], $this->guard);

        $eventId = $res['event']->id;

        $resolved = $this->service->resolveEvent(
            $eventId,
            $this->guard,
            'CONFIRMED_VIOLATION',
            'Dispatched golf cart patrol; tailgating driver escorted to security station.'
        );

        $this->assertEquals('CONFIRMED_VIOLATION', $resolved->resolution_status);
        $this->assertEquals($this->guard->id, $resolved->resolved_by_user_id);
        $this->assertStringContainsString('golf cart patrol', $resolved->resolution_notes);
    }

    public function test_gate_sensor_api_endpoints(): void
    {
        // 1. Post sequence API
        $seqRes = $this->actingAs($this->guard)->postJson('/dashboard/gate-sensor/sequence', [
            'gate' => 'GATE-MAIN',
            'direction' => 'in',
            'license_plate' => 'JAM-123',
            'authorized_occupants' => 1,
            'detected_occupants' => 3,
        ]);
        $seqRes->assertOk();
        $seqRes->assertJsonFragment(['is_tailgating' => true]);

        $eventId = $seqRes->json('event.id');

        // 2. Resolve API
        $resolveRes = $this->actingAs($this->guard)->postJson('/dashboard/gate-sensor/resolve', [
            'event_id' => $eventId,
            'status' => 'DISMISSED',
            'notes' => 'False positive due to optical glare on trailer hitch.',
        ]);
        $resolveRes->assertOk();
        $resolveRes->assertJsonFragment(['success' => true]);

        // 3. Recent feed API
        $feedRes = $this->actingAs($this->guard)->getJson('/dashboard/gate-sensor/recent');
        $feedRes->assertOk();
        $feedRes->assertJsonStructure(['events']);
    }
}
