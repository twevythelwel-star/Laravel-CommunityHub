<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AnnouncementRecipient;
use App\Models\Community;
use App\Models\Notification;
use App\Models\Property;
use App\Models\User;
use App\Services\Communications\NotificationBoardPresenter;
use App\Services\NotificationEngine\NotificationEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnouncementAcknowledgementTest extends TestCase
{
    use RefreshDatabase;

    private Community $community;

    private User $admin;

    private User $resident;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.googleai.key' => null]);

        $this->community = Community::create([
            'name' => 'Sunset Palms Estate',
            'code' => 'SP-01',
        ]);

        config(['gatepass.default_community_id' => 'SP-01']);

        $this->admin = User::factory()->role(UserRole::Admin)->create([
            'email' => 'admin@sunsetpalms.test',
        ]);

        Property::create([
            'community_id' => $this->community->id,
            'owner_user_id' => $this->admin->id,
            'property_code' => 'P-ADM',
            'street_address' => 'Admin Blvd',
            'lot_number' => 'ADM-1',
            'property_type' => 'Office',
            'status' => 'Occupied',
        ]);

        $this->resident = User::factory()->role(UserRole::Homeowner)->create([
            'name' => 'Trevaughn Resident',
            'email' => 'resident@sunsetpalms.test',
            'phone' => '+18765551234',
        ]);

        Property::create([
            'community_id' => $this->community->id,
            'owner_user_id' => $this->resident->id,
            'property_code' => 'P-001',
            'street_address' => 'Hibiscus Avenue',
            'lot_number' => 'Block-B-14',
            'property_type' => 'Single Family',
            'status' => 'Occupied',
        ]);
    }

    public function test_admin_can_publish_announcement_requiring_acknowledgement(): void
    {
        $deadline = now()->addDays(2)->format('Y-m-d\TH:i');

        $response = $this->from('/dashboard/notifications')
            ->actingAs($this->admin)
            ->post('/dashboard/notifications', [
                'title' => 'Scheduled Water Outage Notice',
                'content' => 'Water will be interrupted on Friday from 9am to 3pm for main valve repair.',
                'category' => 'maintenance',
                'priority' => 'urgent',
                'target_type' => 'entire_community',
                'channels' => ['in_app'],
                'requires_acknowledgement' => true,
                'acknowledgement_deadline' => $deadline,
            ]);

        $response->assertRedirect('/dashboard/notifications');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('notifications', [
            'title' => 'Scheduled Water Outage Notice',
            'requires_acknowledgement' => true,
        ]);

        $announcement = Notification::where('title', 'Scheduled Water Outage Notice')->first();
        $this->assertNotNull($announcement);
        $this->assertNotNull($announcement->acknowledgement_deadline);

        $recipient = AnnouncementRecipient::where('notification_id', $announcement->id)
            ->where('user_id', $this->resident->id)
            ->first();

        $this->assertNotNull($recipient);
        $this->assertEquals('delivered', $recipient->delivery_state);
        $this->assertNull($recipient->read_at);
        $this->assertNull($recipient->acknowledged_at);
    }

    public function test_reading_a_notice_marks_read_at_without_marking_acknowledgement(): void
    {
        $announcement = Notification::create([
            'title' => 'Annual General Meeting Policy Notice',
            'content' => 'Mandatory meeting for all registered homeowners.',
            'category' => 'general',
            'priority' => 'urgent',
            'status' => 'published',
            'published_at' => now(),
            'target_type' => 'entire_community',
            'channels' => ['in_app'],
            'requires_acknowledgement' => true,
            'acknowledgement_deadline' => now()->addDays(3),
            'author_user_id' => $this->admin->id,
            'author_name' => $this->admin->name,
        ]);

        $recipient = AnnouncementRecipient::create([
            'notification_id' => $announcement->id,
            'user_id' => $this->resident->id,
            'recipient_name' => $this->resident->name,
            'recipient_email' => $this->resident->email,
            'recipient_phone' => $this->resident->phone,
            'delivery_state' => 'delivered',
            'attempted_channels' => ['in_app'],
            'delivered_at' => now(),
        ]);

        // Resident marks the notice as read
        $response = $this->actingAs($this->resident)->post("/dashboard/notifications/{$announcement->id}/read");
        $response->assertSessionHas('success');

        $recipient->refresh();
        $this->assertNotNull($recipient->read_at, 'Read receipt must be registered.');
        $this->assertNull($recipient->acknowledged_at, 'Reading a message does not mean agreeing to or acknowledging it.');
        $this->assertNull($recipient->acknowledgement_note);
        $this->assertTrue($recipient->isRead());
        $this->assertFalse($recipient->isAcknowledged());
    }

    public function test_a_resident_cannot_read_or_acknowledge_a_notice_not_yet_published(): void
    {
        $scheduled = Notification::create([
            'title' => 'Draft Levy Increase', 'content' => 'Not public yet.', 'status' => 'scheduled',
            'scheduled_for' => now()->addWeek(), 'published_at' => now()->addWeek(), 'target_type' => 'entire_community',
            'channels' => ['in_app'], 'author_name' => $this->admin->name,
        ]);

        $this->actingAs($this->resident)->post("/dashboard/notifications/{$scheduled->id}/read")->assertForbidden();
        $this->actingAs($this->resident)->post("/dashboard/notifications/{$scheduled->id}/acknowledge", ['note' => 'Seen'])->assertForbidden();

        $this->assertSame(0, AnnouncementRecipient::where('notification_id', $scheduled->id)->count(), 'no recipient row is invented');
    }

    public function test_resident_can_explicitly_acknowledge_notice_with_notes(): void
    {
        $announcement = Notification::create([
            'title' => 'Emergency Water Interruption Protocol',
            'content' => 'Please confirm valves are turned off during system pressure testing.',
            'category' => 'emergency',
            'priority' => 'critical',
            'status' => 'published',
            'published_at' => now(),
            'target_type' => 'entire_community',
            'channels' => ['in_app'],
            'requires_acknowledgement' => true,
            'acknowledgement_deadline' => now()->addDays(1),
            'author_user_id' => $this->admin->id,
            'author_name' => $this->admin->name,
        ]);

        $recipient = AnnouncementRecipient::create([
            'notification_id' => $announcement->id,
            'user_id' => $this->resident->id,
            'recipient_name' => $this->resident->name,
            'recipient_email' => $this->resident->email,
            'recipient_phone' => $this->resident->phone,
            'delivery_state' => 'delivered',
            'attempted_channels' => ['in_app'],
            'delivered_at' => now(),
        ]);

        // Resident submits explicit acknowledgement with a custom note
        $response = $this->actingAs($this->resident)->post("/dashboard/notifications/{$announcement->id}/acknowledge", [
            'note' => 'Unit 14B main valve confirmed shut off.',
        ]);

        $response->assertSessionHas('success');

        $recipient->refresh();
        $this->assertNotNull($recipient->read_at, 'Acknowledging should also ensure notice is marked as read.');
        $this->assertNotNull($recipient->acknowledged_at, 'Explicit acknowledgement must have timestamp.');
        $this->assertEquals('Unit 14B main valve confirmed shut off.', $recipient->acknowledgement_note);
        $this->assertTrue($recipient->isRead());
        $this->assertTrue($recipient->isAcknowledged());
    }

    public function test_presenter_correctly_audits_delivered_vs_read_vs_acknowledged(): void
    {
        $announcement = Notification::create([
            'title' => 'Security Gate System Upgrade',
            'content' => 'New RFID tags issued this Wednesday.',
            'category' => 'security',
            'priority' => 'urgent',
            'status' => 'published',
            'published_at' => now(),
            'target_type' => 'entire_community',
            'channels' => ['in_app'],
            'requires_acknowledgement' => true,
            'author_user_id' => $this->admin->id,
            'author_name' => $this->admin->name,
        ]);

        // Resident 1: delivered only
        AnnouncementRecipient::create([
            'notification_id' => $announcement->id,
            'user_id' => $this->resident->id,
            'recipient_name' => $this->resident->name,
            'delivery_state' => 'delivered',
            'attempted_channels' => ['in_app'],
            'delivered_at' => now(),
        ]);

        // Resident 2: delivered and read, but not acknowledged
        $resident2 = User::factory()->role(UserRole::Homeowner)->create();
        AnnouncementRecipient::create([
            'notification_id' => $announcement->id,
            'user_id' => $resident2->id,
            'recipient_name' => $resident2->name,
            'delivery_state' => 'delivered',
            'attempted_channels' => ['in_app'],
            'delivered_at' => now(),
            'read_at' => now(),
        ]);

        // Resident 3: delivered, read, and explicitly acknowledged
        $resident3 = User::factory()->role(UserRole::Homeowner)->create();
        AnnouncementRecipient::create([
            'notification_id' => $announcement->id,
            'user_id' => $resident3->id,
            'recipient_name' => $resident3->name,
            'delivery_state' => 'delivered',
            'attempted_channels' => ['in_app'],
            'delivered_at' => now(),
            'read_at' => now(),
            'acknowledged_at' => now(),
            'acknowledgement_note' => 'Understood and acknowledged.',
        ]);

        $presenter = app(NotificationBoardPresenter::class);
        $engine = app(NotificationEngine::class);
        $data = $presenter->present($this->admin, $engine);

        $this->assertNotEmpty($data['deliveryReports']);
        $report = collect($data['deliveryReports'])->firstWhere('announcementId', $announcement->id);
        $this->assertNotNull($report);

        $this->assertEquals(3, $report['totalRecipients']);
        $this->assertEquals(3, $report['deliveredCount']);
        $this->assertEquals(2, $report['readCount'], '2 recipients have read receipts.');
        $this->assertEquals(1, $report['acknowledgedCount'], 'Only 1 recipient explicitly acknowledged.');
        $this->assertEquals(2, $report['pendingAcknowledgementCount'], '2 recipients still pending acknowledgement.');
    }
}
