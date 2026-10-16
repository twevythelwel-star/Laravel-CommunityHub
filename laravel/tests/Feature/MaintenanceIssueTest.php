<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\InAppNotification;
use App\Models\MaintenanceIssue;
use App\Models\MaintenanceIssueUpdate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MaintenanceIssueTest extends TestCase
{
    use RefreshDatabase;

    public function test_residents_can_view_own_maintenance_issues(): void
    {
        $resident = User::factory()->role(UserRole::Homeowner)->create();
        $otherResident = User::factory()->role(UserRole::Homeowner)->create();

        MaintenanceIssue::create([
            'reference_number' => 'MNT-2026-0001',
            'reporter_id' => $resident->id,
            'category' => 'potholes',
            'title' => 'Deep pothole on Palm Grove Way',
            'description' => 'Needs asphalt patching near driveway entrance.',
            'location' => 'Palm Grove Way Block A',
            'priority' => 'high',
            'status' => 'in_progress',
        ]);

        MaintenanceIssue::create([
            'reference_number' => 'MNT-2026-0002',
            'reporter_id' => $otherResident->id,
            'category' => 'streetlights',
            'title' => 'Dark pole',
            'description' => 'Light bulb burned out.',
            'location' => 'North Gate Pole 3',
            'priority' => 'low',
            'status' => 'reported',
        ]);

        $this->actingAs($resident)->get('/dashboard/maintenance')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard/Maintenance')
                ->has('issues.data', 1)
                ->where('issues.data.0.referenceNumber', 'MNT-2026-0001')
                ->where('issues.data.0.title', 'Deep pothole on Palm Grove Way')
                ->where('stats.total', 1)
                ->where('canTriage', false)
            );
    }

    public function test_residents_can_report_maintenance_issue_with_photo(): void
    {
        Storage::fake('public');

        $resident = User::factory()->role(UserRole::Homeowner)->create();

        $photo = UploadedFile::fake()->image('pothole.jpg', 800, 600);

        $response = $this->actingAs($resident)->post('/dashboard/maintenance', [
            'category' => 'potholes',
            'title' => 'Curbing broken by delivery truck',
            'description' => 'Concrete curb shattered and debris in roadway.',
            'location' => 'Main Boulevard near Lot 12',
            'priority' => 'medium',
            'photos' => [$photo],
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('maintenance_issues', [
            'reporter_id' => $resident->id,
            'category' => 'potholes',
            'title' => 'Curbing broken by delivery truck',
            'location' => 'Main Boulevard near Lot 12',
            'status' => 'reported',
        ]);

        $issue = MaintenanceIssue::first();
        $this->assertNotNull($issue);
        $this->assertCount(1, $issue->photos);
        $this->assertDatabaseHas('maintenance_issue_updates', [
            'maintenance_issue_id' => $issue->id,
            'to_status' => 'reported',
        ]);
    }

    public function test_reporting_emergency_hazard_notifies_staff(): void
    {
        $resident = User::factory()->role(UserRole::Homeowner)->create();
        $staff = User::factory()->role(UserRole::Staff)->create();
        $admin = User::factory()->role(UserRole::Admin)->create();

        $this->actingAs($resident)->post('/dashboard/maintenance', [
            'category' => 'water_leaks',
            'title' => 'Major water main rupture flooding road',
            'description' => 'High pressure water shooting 10ft into air.',
            'location' => 'Corner of West Gate & Palm Ave',
            'priority' => 'emergency',
        ]);

        $this->assertDatabaseHas('in_app_notifications', [
            'user_id' => $staff->id,
            'category' => 'maintenance',
            'priority' => 'urgent',
        ]);

        $this->assertDatabaseHas('in_app_notifications', [
            'user_id' => $admin->id,
            'category' => 'maintenance',
            'priority' => 'urgent',
        ]);
    }

    public function test_staff_and_admin_can_view_all_issues_and_triage(): void
    {
        $resident1 = User::factory()->role(UserRole::Homeowner)->create();
        $resident2 = User::factory()->role(UserRole::Homeowner)->create();
        $staff = User::factory()->role(UserRole::Staff)->create();

        MaintenanceIssue::create([
            'reference_number' => 'MNT-2026-0001',
            'reporter_id' => $resident1->id,
            'category' => 'drainage',
            'title' => 'Storm drain clogged',
            'description' => 'Leaves and mud obstructing rainwater flow.',
            'location' => 'Lot 44 front culvert',
            'priority' => 'medium',
            'status' => 'reported',
        ]);

        MaintenanceIssue::create([
            'reference_number' => 'MNT-2026-0002',
            'reporter_id' => $resident2->id,
            'category' => 'waste_collection',
            'title' => 'Missed dumpster collection',
            'description' => 'Bins overflowing behind recreation center.',
            'location' => 'Recreation Center bin bay',
            'priority' => 'low',
            'status' => 'reported',
        ]);

        $this->actingAs($staff)->get('/dashboard/maintenance')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard/Maintenance')
                ->has('issues.data', 2)
                ->where('canTriage', true)
                ->has('staffRoster')
            );
    }

    public function test_staff_can_assign_work_order_and_update_status(): void
    {
        $resident = User::factory()->role(UserRole::Homeowner)->create();
        $staff = User::factory()->role(UserRole::Staff)->create();

        $issue = MaintenanceIssue::create([
            'reference_number' => 'MNT-2026-0001',
            'reporter_id' => $resident->id,
            'category' => 'streetlights',
            'title' => 'Dark pole 12',
            'description' => 'Light fixture blown after lightning storm.',
            'location' => 'Pole #12 Main Boulevard',
            'priority' => 'high',
            'status' => 'reported',
        ]);

        $this->actingAs($staff)->patch("/dashboard/maintenance/{$issue->id}", [
            'status' => 'in_progress',
            'assigned_to_user_id' => $staff->id,
            'note' => 'Parts ordered from electrical supply. Replacement scheduled for 2 PM.',
        ])->assertRedirect();

        $issue->refresh();
        $this->assertEquals('in_progress', $issue->status);
        $this->assertEquals($staff->id, $issue->assigned_to_user_id);
        $this->assertNotNull($issue->assigned_at);

        $this->assertDatabaseHas('maintenance_issue_updates', [
            'maintenance_issue_id' => $issue->id,
            'user_id' => $staff->id,
            'to_status' => 'in_progress',
            'note' => 'Parts ordered from electrical supply. Replacement scheduled for 2 PM.',
        ]);
    }

    public function test_resolving_issue_sends_closure_notification_to_resident(): void
    {
        $resident = User::factory()->role(UserRole::Homeowner)->create();
        $staff = User::factory()->role(UserRole::Staff)->create();

        $issue = MaintenanceIssue::create([
            'reference_number' => 'MNT-2026-0001',
            'reporter_id' => $resident->id,
            'category' => 'potholes',
            'title' => 'Pothole on North Gate curve',
            'description' => 'Rim damage hazard.',
            'location' => 'North Gate Curve',
            'priority' => 'high',
            'status' => 'in_progress',
        ]);

        $this->actingAs($staff)->patch("/dashboard/maintenance/{$issue->id}", [
            'status' => 'resolved',
            'resolution_notes' => 'Asphalt patched, compacted, and traffic cone removed.',
        ])->assertRedirect();

        $issue->refresh();
        $this->assertEquals('resolved', $issue->status);
        $this->assertNotNull($issue->resolved_at);
        $this->assertEquals('Asphalt patched, compacted, and traffic cone removed.', $issue->resolution_notes);

        // Verify closure notification sent to resident reporter
        $this->assertDatabaseHas('in_app_notifications', [
            'user_id' => $resident->id,
            'category' => 'maintenance',
            'title' => 'Maintenance Resolved: MNT-2026-0001',
        ]);

        $notification = InAppNotification::where('user_id', $resident->id)->first();
        $this->assertNotNull($notification);
        $this->assertStringContainsString('Asphalt patched', $notification->body);
    }

    public function test_reporting_fails_without_required_fields(): void
    {
        $resident = User::factory()->role(UserRole::Homeowner)->create();

        $response = $this->actingAs($resident)->post('/dashboard/maintenance', [
            'title' => '',
            'description' => 'Too short',
        ]);

        $response->assertSessionHasErrors(['title', 'category', 'description', 'location', 'priority']);
    }

    public function test_a_resident_cannot_triage_and_never_sees_internal_notes(): void
    {
        $resident = User::factory()->role(UserRole::Homeowner)->create();
        $issue = MaintenanceIssue::create([
            'reference_number' => 'MNT-2026-0100', 'reporter_id' => $resident->id, 'category' => 'drainage',
            'title' => 'Blocked drain', 'description' => 'Water pooling after rain.', 'location' => 'Hibiscus Lane',
            'priority' => 'medium', 'status' => 'reported',
        ]);
        $staff = User::factory()->role(UserRole::Staff)->create();
        foreach ([[false, 'Crew booked for Thursday.'], [true, 'Contractor quote is over budget.']] as [$internal, $note]) {
            MaintenanceIssueUpdate::create([
                'maintenance_issue_id' => $issue->id, 'user_id' => $staff->id, 'from_status' => 'reported',
                'to_status' => 'triaged', 'note' => $note, 'is_internal' => $internal,
            ]);
        }

        $this->actingAs($resident)->patch("/dashboard/maintenance/{$issue->id}", ['status' => 'closed'])->assertForbidden();
        $this->assertSame('reported', $issue->fresh()->status);

        $this->get('/dashboard/maintenance')->assertInertia(fn ($page) => $page
            ->where('issues.data.0.updates', fn ($updates) => collect($updates)->pluck('note')->all() === ['Crew booked for Thursday.'])
            ->where('staffRoster', [])
        );

        $this->actingAs($staff)->get('/dashboard/maintenance')->assertInertia(fn ($page) => $page
            ->where('canTriage', true)
            ->has('issues.data.0.updates', 2)
        );
    }
}
