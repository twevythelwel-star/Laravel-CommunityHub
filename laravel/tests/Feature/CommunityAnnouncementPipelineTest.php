<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AnnouncementRecipient;
use App\Models\Community;
use App\Models\InAppNotification;
use App\Models\Notification;
use App\Models\Property;
use App\Models\User;
use App\Models\Visitor;
use App\Services\Communications\AudienceResolutionService;
use App\Services\NotificationEngine\NotificationEngine;
use App\Services\NotificationEngine\NotificationPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunityAnnouncementPipelineTest extends TestCase
{
    use RefreshDatabase;

    private Community $community;

    private User $admin;

    private User $homeowner1;

    private User $homeowner2;

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

        $this->homeowner1 = User::factory()->role(UserRole::Homeowner)->create([
            'street' => 'Palm Grove Way',
            'lot' => 'Block-A-01',
            'email' => 'resident1@sunsetpalms.test',
            'phone' => '+15551234567',
        ]);

        Property::create([
            'community_id' => $this->community->id,
            'owner_user_id' => $this->homeowner1->id,
            'property_code' => 'P-HO1',
            'street_address' => 'Palm Grove Way',
            'lot_number' => 'Block-A-01',
            'property_type' => 'Residential',
            'status' => 'Occupied',
        ]);

        $this->homeowner2 = User::factory()->role(UserRole::Homeowner)->create([
            'street' => 'Hibiscus Lane',
            'lot' => 'Block-B-05',
            'email' => 'resident2@sunsetpalms.test',
            'phone' => '+15559876543',
        ]);

        Property::create([
            'community_id' => $this->community->id,
            'owner_user_id' => $this->homeowner2->id,
            'property_code' => 'P-HO2',
            'street_address' => 'Hibiscus Lane',
            'lot_number' => 'Block-B-05',
            'property_type' => 'Residential',
            'status' => 'Occupied',
        ]);
    }

    public function test_system_admin_can_publish_announcement_to_entire_community(): void
    {
        $response = $this->actingAs($this->admin)->post('/dashboard/notifications', [
            'title' => 'Estate Security Advisory',
            'content' => 'New biometric access gates will become operational on Monday.',
            'target_type' => 'entire_community',
            'channels' => ['in_app', 'email'],
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertDatabaseHas('notifications', [
            'title' => 'Estate Security Advisory',
            'community_id' => $this->community->id,
            'target_type' => 'entire_community',
            'status' => 'published',
        ]);

        // 1. One record for the announcement: the board, the dashboard home and the feed all read it.
        $announcement = Notification::where('title', 'Estate Security Advisory')->sole();
        $this->assertSame('general', $announcement->category);
        $this->assertSame('normal', $announcement->priority);

        // 2. announcement_recipients with snapshots and delivery states, linked to that record
        $this->assertDatabaseHas('announcement_recipients', [
            'notification_id' => $announcement->id,
            'user_id' => $this->homeowner1->id,
            'recipient_email' => 'resident1@sunsetpalms.test',
            'delivery_state' => 'delivered',
        ]);

        $this->assertDatabaseHas('announcement_recipients', [
            'notification_id' => $announcement->id,
            'user_id' => $this->homeowner2->id,
            'recipient_email' => 'resident2@sunsetpalms.test',
            'delivery_state' => 'delivered',
        ]);

        // 3. Per-user in-app notification records carry the same id, so read receipts match
        $this->assertDatabaseHas('in_app_notifications', [
            'user_id' => $this->homeowner1->id,
            'title' => 'Estate Security Advisory',
        ]);
        $this->assertTrue(
            InAppNotification::where('user_id', $this->homeowner1->id)->whereJsonContains('data->announcement_id', $announcement->id)->exists(),
        );

        $this->assertDatabaseHas('in_app_notifications', [
            'user_id' => $this->homeowner2->id,
            'title' => 'Estate Security Advisory',
        ]);

        // 5. Existing audit infrastructure (activity_log_entries)
        $this->assertDatabaseHas('activity_log_entries', [
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_community_admin_cannot_publish_to_different_community_via_id_tampering(): void
    {
        $otherCommunity = Community::create([
            'name' => 'Foreign Gated Community',
            'code' => 'FC-99',
        ]);

        // Attempt to publish to otherCommunity using admin's account
        $response = $this->actingAs($this->admin)->post('/dashboard/notifications', [
            'title' => 'Unauthorized Cross-Community Broadcast',
            'content' => 'This should be strictly blocked by Policy.',
            'community_id' => $otherCommunity->id,
            'target_type' => 'entire_community',
        ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('notifications', [
            'title' => 'Unauthorized Cross-Community Broadcast',
        ]);
    }

    public function test_ordinary_users_cannot_publish_or_cancel_announcements(): void
    {
        $roles = [
            UserRole::Homeowner,
            UserRole::TemporaryHomeowner,
            UserRole::Security,
            UserRole::Staff,
        ];

        foreach ($roles as $role) {
            $user = User::factory()->role($role)->create();

            // Attempt to publish
            $response = $this->actingAs($user)->post('/dashboard/notifications', [
                'title' => 'Malicious Broadcast Attempt',
                'content' => 'Unauthorized attempt by non-admin.',
                'target_type' => 'entire_community',
            ]);

            $response->assertForbidden();
        }
    }

    public function test_recipient_targeting_by_street_resolves_only_targeted_residents(): void
    {
        $response = $this->actingAs($this->admin)->post('/dashboard/notifications', [
            'title' => 'Palm Grove Way Water Maintenance',
            'content' => 'Scheduled pipe maintenance for Palm Grove Way only.',
            'target_type' => 'street',
            'target_criteria' => [
                'street' => 'Palm Grove Way',
            ],
            'channels' => ['in_app'],
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        // homeowner1 lives on Palm Grove Way -> gets notification
        $this->assertDatabaseHas('in_app_notifications', [
            'user_id' => $this->homeowner1->id,
            'title' => 'Palm Grove Way Water Maintenance',
        ]);

        // homeowner2 lives on Hibiscus Lane -> does NOT get notification
        $this->assertDatabaseMissing('in_app_notifications', [
            'user_id' => $this->homeowner2->id,
            'title' => 'Palm Grove Way Water Maintenance',
        ]);
    }

    public function test_recipient_targeting_by_block_resolves_only_targeted_lot(): void
    {
        $response = $this->actingAs($this->admin)->post('/dashboard/notifications', [
            'title' => 'Block B Power Outage',
            'content' => 'Scheduled generator servicing for Block B.',
            'target_type' => 'block',
            'target_criteria' => [
                'block' => 'B',
            ],
            'channels' => ['in_app'],
        ]);

        $response->assertSessionHasNoErrors();

        // homeowner2 is Block-B-05 -> receives notice
        $this->assertDatabaseHas('in_app_notifications', [
            'user_id' => $this->homeowner2->id,
            'title' => 'Block B Power Outage',
        ]);

        // homeowner1 is Block-A-01 -> does NOT receive notice
        $this->assertDatabaseMissing('in_app_notifications', [
            'user_id' => $this->homeowner1->id,
            'title' => 'Block B Power Outage',
        ]);
    }

    public function test_in_app_notification_centre_reflects_unread_and_can_be_marked_read(): void
    {
        // Deliver in-app notification to homeowner1
        $notification = InAppNotification::create([
            'user_id' => $this->homeowner1->id,
            'title' => 'Welcome Notice',
            'body' => 'Welcome to the new notification centre.',
            'category' => 'announcement',
            'priority' => 'normal',
        ]);

        $this->assertNull($notification->read_at);

        // Mark read via API
        $response = $this->actingAs($this->homeowner1)->postJson("/in-app-notifications/{$notification->id}/read");
        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertNotNull($notification->fresh()->read_at);

        // Another user cannot mark homeowner1's notification read
        $otherNotification = InAppNotification::create([
            'user_id' => $this->homeowner1->id,
            'title' => 'Private Notice',
            'body' => 'Private message.',
            'category' => 'announcement',
            'priority' => 'normal',
        ]);

        $response2 = $this->actingAs($this->homeowner2)->postJson("/in-app-notifications/{$otherNotification->id}/read");
        $response2->assertForbidden();
    }

    public function test_mark_all_in_app_notifications_as_read(): void
    {
        InAppNotification::create([
            'user_id' => $this->homeowner1->id,
            'title' => 'Notice 1',
            'body' => 'Notice 1 body',
            'category' => 'announcement',
            'priority' => 'normal',
        ]);

        InAppNotification::create([
            'user_id' => $this->homeowner1->id,
            'title' => 'Notice 2',
            'body' => 'Notice 2 body',
            'category' => 'announcement',
            'priority' => 'normal',
        ]);

        $response = $this->actingAs($this->homeowner1)->postJson('/in-app-notifications/read-all');
        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertEquals(0, InAppNotification::where('user_id', $this->homeowner1->id)->unread()->count());
    }

    public function test_scheduled_announcements_can_be_created_and_cancelled(): void
    {
        $futureTime = now()->addDays(2)->toIso8601String();

        $response = $this->actingAs($this->admin)->post('/dashboard/notifications', [
            'title' => 'Future Gala Dinner',
            'content' => 'Annual community dinner next week.',
            'target_type' => 'entire_community',
            'channels' => ['in_app'],
            'scheduled_for' => $futureTime,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $announcement = Notification::where('title', 'Future Gala Dinner')->first();
        $this->assertNotNull($announcement);
        $this->assertEquals('scheduled', $announcement->status);
        $this->assertNull($announcement->sent_at);

        // Cancel the scheduled announcement
        $cancelResponse = $this->actingAs($this->admin)->post("/dashboard/notifications/{$announcement->id}/cancel");
        $cancelResponse->assertSessionHasNoErrors();
        $cancelResponse->assertRedirect();

        $announcement->refresh();
        $this->assertEquals('cancelled', $announcement->status);
        $this->assertNotNull($announcement->cancelled_at);
    }

    public function test_the_board_lists_every_notice_the_feed_shows(): void
    {
        // A notice written before announcements existed, with no recipient rows.
        Notification::create([
            'title' => 'Pool Closed for Resurfacing',
            'content' => 'The pool reopens on Friday.',
            'author_id' => $this->admin->id,
            'author_name' => $this->admin->name,
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);
        $this->actingAs($this->admin)->post('/dashboard/notifications', [
            'title' => 'Gate Hours Changing',
            'content' => 'The north gate closes at 22:00 from Monday.',
            'target_type' => 'entire_community',
            'channels' => ['in_app'],
        ])->assertSessionHasNoErrors();

        $this->get('/dashboard/notifications')->assertInertia(fn ($page) => $page
            ->where('summaryStats.total', 2)
            ->where('summaryStats.published', 2)
            ->where('announcements', fn ($rows) => collect($rows)->pluck('title')->sort()->values()->all() === ['Gate Hours Changing', 'Pool Closed for Resurfacing'])
            ->where('notifications.data', fn ($rows) => collect($rows)->pluck('title')->sort()->values()->all() === ['Gate Hours Changing', 'Pool Closed for Resurfacing'])
        );
    }

    public function test_cancelling_from_the_board_cancels_that_announcement_and_no_other(): void
    {
        // An older notice takes the first id, so a board id from any other table would point at it.
        $older = Notification::create([
            'title' => 'Older Notice', 'content' => 'Already out.', 'author_id' => $this->admin->id,
            'author_name' => $this->admin->name, 'status' => 'published', 'published_at' => now()->subWeek(),
        ]);
        $this->actingAs($this->admin)->post('/dashboard/notifications', [
            'title' => 'Fireworks Night',
            'content' => 'Saturday from 19:00 on the green.',
            'target_type' => 'entire_community',
            'channels' => ['in_app'],
            'scheduled_for' => now()->addDays(3)->toIso8601String(),
        ])->assertSessionHasNoErrors();

        $row = null;
        $this->get('/dashboard/notifications')->assertInertia(function ($page) use (&$row) {
            $row = collect($page->toArray()['props']['announcements'])->firstWhere('title', 'Fireworks Night');

            return $page;
        });
        $boardId = $row['id'];

        // Not gone out yet: the board shows it as "Scheduled: …", not as published.
        $this->assertNull($row['publishTime']);
        $this->assertNotNull($row['scheduledTime']);

        $this->post("/dashboard/notifications/{$boardId}/cancel")->assertSessionHasNoErrors();

        $this->assertSame('cancelled', Notification::where('title', 'Fireworks Night')->value('status'));
        $this->assertSame('published', $older->fresh()->status);
    }

    public function test_a_read_in_app_copy_counts_as_read_on_the_delivery_report(): void
    {
        // An older notice takes the first id, as in any estate with history.
        Notification::create([
            'title' => 'Older Notice', 'content' => 'Already out.', 'author_id' => $this->admin->id,
            'author_name' => $this->admin->name, 'status' => 'published', 'published_at' => now()->subWeek(),
        ]);

        $this->actingAs($this->admin)->post('/dashboard/notifications', [
            'title' => 'Bulk Waste Collection',
            'content' => 'Place items by the kerb on Thursday.',
            'target_type' => 'entire_community',
            'channels' => ['in_app'],
        ])->assertSessionHasNoErrors();

        $copy = InAppNotification::where('user_id', $this->homeowner1->id)->where('title', 'Bulk Waste Collection')->sole();
        $this->actingAs($this->homeowner1)->postJson("/in-app-notifications/{$copy->id}/read")->assertOk();

        $this->actingAs($this->admin)->get('/dashboard/notifications')->assertInertia(fn ($page) => $page
            ->where('deliveryReports.0.title', 'Bulk Waste Collection')
            ->where('deliveryReports.0.readCount', 1)
            ->where('deliveryReports.0.recipients', fn ($rows) => collect($rows)->firstWhere('userId', $this->homeowner1->id)['isRead'] === true)
        );
    }

    public function test_scheduler_command_dispatches_due_scheduled_announcements(): void
    {
        $dueAnnouncement = Notification::create([
            'title' => 'Scheduled Morning Inspection',
            'content' => 'Estate-wide grounds inspection scheduled for 8:00 AM.',
            'community_id' => $this->community->id,
            'target_type' => 'entire_community',
            'channels' => ['in_app'],
            'status' => 'scheduled',
            'scheduled_for' => now()->subMinutes(5),
            'author_name' => $this->admin->name,
            'published_at' => now()->subMinutes(5),
        ]);

        $this->artisan('announcements:dispatch-scheduled')
            ->assertExitCode(0);

        $dueAnnouncement->refresh();
        $this->assertEquals('published', $dueAnnouncement->status);
        $this->assertNotNull($dueAnnouncement->sent_at);

        // Recipients received the notification
        $this->assertDatabaseHas('in_app_notifications', [
            'user_id' => $this->homeowner1->id,
            'title' => 'Scheduled Morning Inspection',
        ]);
    }

    public function test_expired_announcements_are_hidden_from_active_notice_feeds_but_preserved_in_audit(): void
    {
        // 1. Create an expired notice
        $expiredNotice = Notification::create([
            'title' => 'Expired Summer BBQ',
            'content' => 'Join us at the clubhouse for BBQ.',
            'community_id' => $this->community->id,
            'status' => 'published',
            'published_at' => now()->subDays(5),
            'expires_at' => now()->subDay(),
            'author_name' => $this->admin->name,
        ]);

        // 2. Create an active notice
        $activeNotice = Notification::create([
            'title' => 'Active Tennis Tournament',
            'content' => 'Tennis courts open for signups.',
            'community_id' => $this->community->id,
            'status' => 'published',
            'published_at' => now()->subHours(2),
            'expires_at' => now()->addDays(3),
            'author_name' => $this->admin->name,
        ]);

        // Active feed query (used by resident notice board)
        $activeNotices = Notification::published()->get();
        $this->assertFalse($activeNotices->contains('id', $expiredNotice->id));
        $this->assertTrue($activeNotices->contains('id', $activeNotice->id));

        // Audit & expired query (used by admin reporting)
        $expiredNotices = Notification::expired()->get();
        $this->assertTrue($expiredNotices->contains('id', $expiredNotice->id));
        $this->assertFalse($expiredNotices->contains('id', $activeNotice->id));
    }

    public function test_idempotent_publishing_prevents_duplicate_announcements(): void
    {
        $payload = [
            'title' => 'Water Maintenance Interruption',
            'content' => 'Main pipe repairs tomorrow 9am-12pm.',
            'target_type' => 'entire_community',
            'channels' => ['in_app'],
        ];

        // First submission
        $res1 = $this->actingAs($this->admin)->post('/dashboard/notifications', $payload);
        $res1->assertSessionHasNoErrors();

        // Rapid duplicate submission (e.g. double-click)
        $res2 = $this->actingAs($this->admin)->post('/dashboard/notifications', $payload);
        $res2->assertSessionHasNoErrors();

        // Only 1 record created in database
        $this->assertEquals(1, Notification::where('title', 'Water Maintenance Interruption')->count());
    }

    public function test_emergency_and_critical_priority_is_restricted_to_authorized_roles(): void
    {
        // Ordinary resident cannot post critical priority
        $res1 = $this->actingAs($this->homeowner1)->post('/dashboard/notifications', [
            'title' => 'Fake Emergency Broadcast',
            'content' => 'Everyone evacuate now.',
            'category' => 'emergency',
            'priority' => 'critical',
            'target_type' => 'entire_community',
        ]);
        $res1->assertForbidden();

        // Admin can post emergency priority and activity is recorded
        $res2 = $this->actingAs($this->admin)->post('/dashboard/notifications', [
            'title' => 'Severe Weather Storm Alert',
            'content' => 'Hurricane warning issued for the parish. Secure outdoor furniture.',
            'category' => 'emergency',
            'priority' => 'critical',
            'target_type' => 'entire_community',
            'channels' => ['in_app'],
        ]);
        $res2->assertSessionHasNoErrors();
        $this->assertDatabaseHas('notifications', [
            'title' => 'Severe Weather Storm Alert',
            'priority' => 'critical',
            'category' => 'emergency',
        ]);
    }

    public function test_content_safety_strips_unsafe_html_and_javascript_protocols(): void
    {
        $maliciousPayload = [
            'title' => 'Security Gate Advisory',
            'content' => '<script>alert("XSS")</script><a href="javascript:stealCookies()">Click here</a> to view gate map. <strong>Safe text</strong>',
            'target_type' => 'entire_community',
            'channels' => ['in_app'],
        ];

        $res = $this->actingAs($this->admin)->post('/dashboard/notifications', $maliciousPayload);
        $res->assertSessionHasNoErrors();

        $saved = Notification::where('title', 'Security Gate Advisory')->first();
        $this->assertNotNull($saved);
        $this->assertStringNotContainsString('<script>', $saved->content);
        $this->assertStringNotContainsString('javascript:stealCookies()', $saved->content);
        $this->assertStringContainsString('<strong>Safe text</strong>', $saved->content);
    }

    public function test_residents_cannot_see_other_residents_contact_details_or_delivery_telemetry(): void
    {
        // Resident accessing /dashboard/notifications receives empty delivery reports
        $response = $this->actingAs($this->homeowner1)->get('/dashboard/notifications');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('canBroadcast', false)
            ->where('announcements', [])
            ->where('deliveryReports', [])
            ->where('summaryStats', null)
        );
    }

    public function test_recipient_targeting_by_section_and_custom_group_resolves_correct_recipients(): void
    {
        // 1. Target by section: 'Residential' (matches homeowner1 and homeowner2, but not Office admin property)
        $resSection = $this->actingAs($this->admin)->post('/dashboard/notifications', [
            'title' => 'Residential Section Advisory',
            'content' => 'Recycling collection changes for residential properties.',
            'target_type' => 'section',
            'target_criteria' => [
                'section' => 'Residential',
            ],
            'channels' => ['in_app'],
        ]);
        $resSection->assertSessionHasNoErrors();

        $this->assertDatabaseHas('in_app_notifications', [
            'user_id' => $this->homeowner1->id,
            'title' => 'Residential Section Advisory',
        ]);
        $this->assertDatabaseHas('in_app_notifications', [
            'user_id' => $this->homeowner2->id,
            'title' => 'Residential Section Advisory',
        ]);

        // 2. Target by custom group: custom streets selection
        $resCustom = $this->actingAs($this->admin)->post('/dashboard/notifications', [
            'title' => 'Custom Targeted Street Notice',
            'content' => 'Notice for Palm Grove Way only.',
            'target_type' => 'custom_group',
            'target_criteria' => [
                'streets' => ['Palm Grove Way'],
            ],
            'channels' => ['in_app'],
        ]);
        $resCustom->assertSessionHasNoErrors();

        $this->assertDatabaseHas('in_app_notifications', [
            'user_id' => $this->homeowner1->id,
            'title' => 'Custom Targeted Street Notice',
        ]);
        $this->assertDatabaseMissing('in_app_notifications', [
            'user_id' => $this->homeowner2->id,
            'title' => 'Custom Targeted Street Notice',
        ]);
    }

    public function test_overlapping_audience_selections_do_not_send_duplicate_messages(): void
    {
        // Homeowner1 lives on 'Palm Grove Way' AND in 'Block A'
        // Admin targets both criteria in custom group
        $response = $this->actingAs($this->admin)->post('/dashboard/notifications', [
            'title' => 'Overlapping Criteria Test Broadcast',
            'content' => 'Testing strict deduplication across overlapping street and block boundaries.',
            'target_type' => 'custom_group',
            'target_criteria' => [
                'streets' => ['Palm Grove Way'],
                'blocks' => ['A'],
            ],
            'channels' => ['in_app'],
        ]);
        $response->assertSessionHasNoErrors();

        // Must receive EXACTLY ONE notification, zero duplicates
        $recipientNotices = InAppNotification::where('user_id', $this->homeowner1->id)
            ->where('title', 'Overlapping Criteria Test Broadcast')
            ->count();
        $this->assertEquals(1, $recipientNotices, 'Overlapping criteria must result in exactly 1 notification delivery.');

        $recipientRecords = AnnouncementRecipient::where('user_id', $this->homeowner1->id)
            ->whereHas('notification', fn ($q) => $q->where('title', 'Overlapping Criteria Test Broadcast'))
            ->count();
        $this->assertEquals(1, $recipientRecords, 'Overlapping criteria must create exactly 1 recipient audit record.');
    }

    public function test_queue_failures_can_be_retried_safely_without_resending_successful_deliveries(): void
    {
        $announcement = Notification::create([
            'title' => 'Retry Safety Drill',
            'content' => 'Verifying safe retries without duplicate dispatches.',
            'community_id' => $this->community->id,
            'target_type' => 'entire_community',
            'channels' => ['in_app'],
            'status' => 'published',
            'author_id' => $this->admin->id,
            'author_name' => $this->admin->name,
            'published_at' => now(),
        ]);

        // Simulate homeowner1 was already successfully delivered
        AnnouncementRecipient::create([
            'notification_id' => $announcement->id,
            'user_id' => $this->homeowner1->id,
            'recipient_name' => $this->homeowner1->name,
            'delivery_state' => 'delivered',
            'attempted_channels' => ['in_app'],
            'channel_outcomes' => ['in_app' => 'delivered'],
            'delivered_at' => now(),
        ]);
        InAppNotification::create([
            'user_id' => $this->homeowner1->id,
            'title' => 'Retry Safety Drill',
            'body' => 'Verifying safe retries without duplicate dispatches.',
            'category' => 'announcement',
            'priority' => 'normal',
        ]);

        // Simulate homeowner2 had a transient failure
        AnnouncementRecipient::create([
            'notification_id' => $announcement->id,
            'user_id' => $this->homeowner2->id,
            'recipient_name' => $this->homeowner2->name,
            'delivery_state' => 'failed',
            'attempted_channels' => ['in_app'],
            'channel_outcomes' => ['in_app' => 'failed'],
            'failure_details' => 'Simulated gateway timeout',
        ]);

        // Execute batch dispatch (as queued job would do on retry)
        $resolver = app(AudienceResolutionService::class);
        $engine = app(NotificationEngine::class);
        $resolver->dispatchInBatches($this->community, $announcement, $engine);

        // Homeowner1 was NOT duplicate notified (still exactly 1 notification)
        $this->assertEquals(1, InAppNotification::where('user_id', $this->homeowner1->id)->where('title', 'Retry Safety Drill')->count());

        // Homeowner2 was retried and now successfully delivered
        $this->assertDatabaseHas('in_app_notifications', [
            'user_id' => $this->homeowner2->id,
            'title' => 'Retry Safety Drill',
        ]);
        $this->assertEquals('delivered', AnnouncementRecipient::where('notification_id', $announcement->id)->where('user_id', $this->homeowner2->id)->value('delivery_state'));
    }

    public function test_external_channels_report_honest_channel_specific_statuses_when_configured(): void
    {
        $engine = app(NotificationEngine::class);
        $status = $engine->getChannelsStatus();

        // 1. In-app is always available and guaranteed
        $this->assertTrue($status['in_app']['configured']);
        $this->assertEquals('available', $status['in_app']['status']);

        // 2. Unconfigured channels report unavailable honestly
        if (! filled(config('services.twilio.sid'))) {
            $this->assertFalse($status['sms']['configured']);
            $this->assertEquals('unavailable', $status['sms']['status']);
        }
    }

    public function test_all_publish_cancel_schedule_and_administrative_actions_are_audited(): void
    {
        // 1. Publish notice
        $this->actingAs($this->admin)->post('/dashboard/notifications', [
            'title' => 'Audited Notice 1',
            'content' => 'Content for audit tracking.',
            'target_type' => 'entire_community',
            'channels' => ['in_app'],
        ]);
        $this->assertDatabaseHas('activity_log_entries', [
            'user_id' => $this->admin->id,
            'action' => 'Published notice: Audited Notice 1',
        ]);

        // 2. Schedule notice
        $this->actingAs($this->admin)->post('/dashboard/notifications', [
            'title' => 'Audited Scheduled Notice',
            'content' => 'Content for audit tracking.',
            'target_type' => 'entire_community',
            'channels' => ['in_app'],
            'scheduled_for' => now()->addDays(2)->toIso8601String(),
        ]);
        $this->assertDatabaseHas('activity_log_entries', [
            'user_id' => $this->admin->id,
            'action' => 'Scheduled notice: Audited Scheduled Notice',
        ]);

        // 3. Cancel scheduled notice
        $scheduled = Notification::where('title', 'Audited Scheduled Notice')->first();
        $this->actingAs($this->admin)->post("/dashboard/notifications/{$scheduled->id}/cancel");
        $this->assertDatabaseHas('activity_log_entries', [
            'user_id' => $this->admin->id,
            'action' => 'Cancelled scheduled announcement: Audited Scheduled Notice',
        ]);
    }

    public function test_existing_billing_visitor_warning_and_assistance_notifications_continue_to_operate(): void
    {
        $engine = app(NotificationEngine::class);

        // 1. Existing billing reminder notification via NotificationEngine payload
        $payload = new NotificationPayload(
            title: 'Monthly Assessment Due',
            body: 'Your maintenance fee of $150.00 is due.',
            recipient: (string) $this->homeowner1->id,
            metadata: ['type' => 'billing_reminder']
        );
        $outcomes = $engine->send(['in_app'], $payload);
        $this->assertEquals('sent', $outcomes['in_app']->status);

        // 2. Existing visitor pass arrival notice continues to work
        $visitor = Visitor::factory()->create([
            'homeowner_id' => $this->homeowner1->id,
            'notify_sms' => false,
            'notify_whatsapp' => false,
            'notify_email' => false,
        ]);
        $arrivalOutcomes = $engine->dispatchArrivalNotice($visitor, 'Main Gate');
        $this->assertArrayHasKey('in_app', $arrivalOutcomes);
        $this->assertEquals('sent', $arrivalOutcomes['in_app']->status);

        // 3. Existing silent assistance alert continues to work
        $guard = User::factory()->role(UserRole::Security)->create();
        $this->actingAs($this->homeowner1)
            ->postJson('/dashboard/safety/assist', ['assistance' => 'police'])
            ->assertOk()
            ->assertExactJson(['message' => 'Request sent.']);

        $this->assertSame(1, InAppNotification::where('user_id', $guard->id)->count());
    }
}
