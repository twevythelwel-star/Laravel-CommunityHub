<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CommunityIncident;
use App\Models\CommunityIncidentUpdate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CommunityIncidentTest extends TestCase
{
    use RefreshDatabase;

    private function securityOfficer(): User
    {
        return User::factory()->role(UserRole::Security)->create([
            'name' => 'Officer Miller',
            'email' => 'officer.miller@test.local',
        ]);
    }

    private function estateAdmin(): User
    {
        return User::factory()->role(UserRole::Admin)->create([
            'name' => 'Estate Manager',
            'email' => 'admin@test.local',
        ]);
    }

    private function resident(): User
    {
        return User::factory()->role(UserRole::Homeowner)->create([
            'name' => 'John Resident',
            'email' => 'john.resident@test.local',
        ]);
    }

    public function test_security_officer_can_report_an_incident_with_sensitive_details(): void
    {
        $officer = $this->securityOfficer();

        $response = $this->actingAs($officer)->post('/dashboard/incidents', [
            'title' => 'Water Main Rupture on Hibiscus Way',
            'category' => 'utility_outage',
            'severity' => 'high',
            'location' => 'Sector 3 - Hibiscus Way',
            'summary' => 'Water pressure drop reported. National Water Commission maintenance team dispatched.',
            'sensitive_details' => 'Caller identity: Unit 44 (Mrs. Davis). Isolation valve #3 stuck open. Plumber contact: 876-555-0199.',
            'broadcast_urgent_notice' => true,
        ]);

        $response->assertRedirect('/dashboard/incidents');
        $this->assertDatabaseHas('community_incidents', [
            'title' => 'Water Main Rupture on Hibiscus Way',
            'category' => 'utility_outage',
            'severity' => 'high',
            'location' => 'Sector 3 - Hibiscus Way',
            'broadcast_sent' => true,
            'reported_by' => $officer->id,
        ]);

        $incident = CommunityIncident::where('title', 'Water Main Rupture on Hibiscus Way')->first();
        $this->assertNotNull($incident);
        $this->assertStringStartsWith('INC-', $incident->incident_number);
        $this->assertDatabaseHas('community_incident_updates', [
            'incident_id' => $incident->id,
            'user_id' => $officer->id,
        ]);
    }

    public function test_resident_cannot_report_an_incident(): void
    {
        $resident = $this->resident();

        $response = $this->actingAs($resident)->post('/dashboard/incidents', [
            'title' => 'Unauthorized Citizen Incident',
            'category' => 'security',
            'severity' => 'medium',
            'location' => 'Main Gate',
            'summary' => 'I saw something strange.',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('community_incidents', 0);
    }

    public function test_sensitive_details_and_internal_notes_are_strictly_shielded_from_residents(): void
    {
        $officer = $this->securityOfficer();
        $resident = $this->resident();

        $incident = CommunityIncident::create([
            'incident_number' => 'INC-2026-0001',
            'title' => 'Suspicious Vehicle Loitering at North Perimeter',
            'category' => 'security',
            'severity' => 'critical',
            'status' => 'investigating',
            'location' => 'North Perimeter Gate',
            'summary' => 'Security personnel are inspecting the perimeter boundary.',
            'sensitive_details' => 'RESTRICTED: License plate 4829-GA, white Toyota Fielder. Suspect observed photographing camera post 4. Police dispatch ID: POL-8812.',
            'reported_by' => $officer->id,
        ]);

        // Add a public update
        CommunityIncidentUpdate::create([
            'incident_id' => $incident->id,
            'user_id' => $officer->id,
            'message' => 'Patrol vehicle deployed to sector.',
            'status_after' => 'investigating',
            'is_internal' => false,
        ]);

        // Add an internal confidential note
        CommunityIncidentUpdate::create([
            'incident_id' => $incident->id,
            'user_id' => $officer->id,
            'message' => 'Officer Davis reviewing CCTV camera 7 feed with local police patrol.',
            'status_after' => 'investigating',
            'is_internal' => true,
        ]);

        // Resident views the incident feed
        $this->actingAs($resident)
            ->get('/dashboard/incidents')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Incidents')
                ->has('incidents', 1)
                ->where('incidents.0.sensitiveDetails', null)
                ->where('incidents.0.title', 'Suspicious Vehicle Loitering at North Perimeter')
                ->where('incidents.0.updates', function ($updates) {
                    // Only the 1 public update should be visible to resident; internal note MUST be hidden!
                    return count($updates) === 1 && $updates[0]['isInternal'] === false;
                })
                ->where('can.viewSensitiveDetails', false)
                ->where('can.manage', false)
            );

        // Security officer views the incident feed
        $this->actingAs($officer)
            ->get('/dashboard/incidents')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Incidents')
                ->has('incidents', 1)
                ->where('incidents.0.sensitiveDetails', 'RESTRICTED: License plate 4829-GA, white Toyota Fielder. Suspect observed photographing camera post 4. Police dispatch ID: POL-8812.')
                ->where('incidents.0.updates', function ($updates) {
                    // Both the public update and the internal confidential note must be visible to security
                    return count($updates) === 2;
                })
                ->where('can.viewSensitiveDetails', true)
                ->where('can.manage', true)
            );
    }

    public function test_authorized_user_can_add_timeline_updates(): void
    {
        $officer = $this->securityOfficer();

        $incident = CommunityIncident::create([
            'incident_number' => 'INC-2026-0002',
            'title' => 'Brush Fire Near East Perimeter',
            'category' => 'fire',
            'severity' => 'critical',
            'status' => 'reported',
            'location' => 'East Perimeter Berm',
            'summary' => 'Smoke observed near boundary.',
            'reported_by' => $officer->id,
        ]);

        $response = $this->actingAs($officer)->post("/dashboard/incidents/{$incident->id}/updates", [
            'message' => 'Fire brigade on scene. Water line connected. Controlled containment underway.',
            'status' => 'contained',
            'is_internal' => false,
            'broadcast_update' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('community_incident_updates', [
            'incident_id' => $incident->id,
            'message' => 'Fire brigade on scene. Water line connected. Controlled containment underway.',
            'status_after' => 'contained',
            'is_internal' => false,
            'broadcast_sent' => true,
        ]);

        $this->assertEquals('contained', $incident->fresh()->status);
    }

    public function test_authorized_user_can_issue_an_all_clear_message(): void
    {
        $admin = $this->estateAdmin();

        $incident = CommunityIncident::create([
            'incident_number' => 'INC-2026-0003',
            'title' => 'Power Grid Surge & Transformer Fault',
            'category' => 'utility_outage',
            'severity' => 'high',
            'status' => 'active',
            'location' => 'Substation B',
            'summary' => 'Power outage affecting Blocks 3 and 4.',
            'reported_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->post("/dashboard/incidents/{$incident->id}/all-clear", [
            'message' => 'Transformer replacement completed by JPS teams. Power safely restored across all blocks. All clear.',
            'broadcast_all_clear' => true,
        ]);

        $response->assertRedirect();
        $incident->refresh();

        $this->assertEquals(CommunityIncident::STATUS_ALL_CLEAR, $incident->status);
        $this->assertNotNull($incident->all_clear_at);
        $this->assertEquals($admin->id, $incident->all_clear_by);
        $this->assertEquals('Transformer replacement completed by JPS teams. Power safely restored across all blocks. All clear.', $incident->all_clear_message);

        // Timeline should have an ALL-CLEAR entry
        $this->assertDatabaseHas('community_incident_updates', [
            'incident_id' => $incident->id,
            'status_after' => CommunityIncident::STATUS_ALL_CLEAR,
            'is_internal' => false,
        ]);
    }

    public function test_resident_cannot_issue_an_all_clear(): void
    {
        $officer = $this->securityOfficer();
        $resident = $this->resident();

        $incident = CommunityIncident::create([
            'incident_number' => 'INC-2026-0004',
            'title' => 'Gate 1 Access Motor Jam',
            'category' => 'infrastructure',
            'severity' => 'medium',
            'status' => 'active',
            'location' => 'Gate 1',
            'summary' => 'Gate motor jammed.',
            'reported_by' => $officer->id,
        ]);

        $response = $this->actingAs($resident)->post("/dashboard/incidents/{$incident->id}/all-clear", [
            'message' => 'I think it is fine now.',
        ]);

        $response->assertForbidden();
        $this->assertEquals('active', $incident->fresh()->status);
    }

    private function loggedIncident(User $officer): CommunityIncident
    {
        $this->actingAs($officer)->post('/dashboard/incidents', [
            'title' => 'Power outage, Block C', 'category' => 'utility_outage', 'severity' => 'medium',
            'location' => 'Block C', 'summary' => 'Transformer tripped.', 'sensitive_details' => 'Suspected tampering by a named contractor.',
        ])->assertRedirect();

        return CommunityIncident::sole();
    }

    public function test_closing_is_for_security_and_leaves_a_timeline_entry(): void
    {
        $officer = $this->securityOfficer();
        $incident = $this->loggedIncident($officer);

        $this->actingAs($this->resident())->post("/dashboard/incidents/{$incident->id}/close")->assertForbidden();
        $this->assertSame(CommunityIncident::STATUS_REPORTED, $incident->fresh()->status);

        $this->actingAs($officer)->post("/dashboard/incidents/{$incident->id}/close")->assertRedirect();

        $this->assertSame(CommunityIncident::STATUS_CLOSED, $incident->fresh()->status);
        $this->assertDatabaseHas('community_incident_updates', [
            'incident_id' => $incident->id, 'user_id' => $officer->id, 'status_after' => CommunityIncident::STATUS_CLOSED,
        ]);
    }

    public function test_one_incident_is_json_with_details_shielded_and_the_browser_goes_to_the_feed(): void
    {
        $officer = $this->securityOfficer();
        $incident = $this->loggedIncident($officer);
        $resident = $this->resident();

        $this->actingAs($resident)->getJson("/dashboard/incidents/{$incident->id}")
            ->assertOk()
            ->assertJsonPath('title', 'Power outage, Block C')
            ->assertJsonPath('sensitiveDetails', null);

        $this->actingAs($officer)->getJson("/dashboard/incidents/{$incident->id}")
            ->assertJsonPath('sensitiveDetails', 'Suspected tampering by a named contractor.');

        $this->actingAs($resident)->get("/dashboard/incidents/{$incident->id}")->assertRedirect(route('dashboard.incidents'));
    }
}
