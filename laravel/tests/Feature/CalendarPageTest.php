<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CommunityEvent;
use App\Models\InAppNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The event form's save handler used to close the dialog and reload without
 * sending anything, while toasting "Event Created". These cover the endpoints
 * it now posts to, in the payload shape the page sends.
 */
class CalendarPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_create_an_event_that_then_appears_on_the_calendar(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();

        $this->actingAs($admin)->post('/dashboard/calendar', [
            'title' => 'Community Pool Party',
            'description' => 'Bring the whole family to the clubhouse pool.',
            'event_type' => 'event',
            'location' => 'Clubhouse Pool',
            'start_date' => '2026-10-03T14:00:00.000Z',
            'end_date' => '2026-10-03T18:00:00.000Z',
            'image_url' => null,
        ])->assertRedirect()->assertSessionHas('success', 'Event created.');

        $event = CommunityEvent::sole();
        $this->assertSame('Community Pool Party', $event->title);
        $this->assertSame('event', $event->event_type);
        $this->assertSame('Clubhouse Pool', $event->location);
        $this->assertSame($admin->id, $event->created_by);

        $this->actingAs($admin)->get('/dashboard/calendar')
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard/Calendar')
                ->has('events', 1)
                ->where('events.0.title', 'Community Pool Party')
                ->where('events.0.eventType', 'event')
                ->where('events.0.location', 'Clubhouse Pool')
            );
    }

    public function test_admin_can_create_holidays_office_closures_and_maintenance_windows(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();

        $this->actingAs($admin)->post('/dashboard/calendar', [
            'title' => 'National Heroes Day',
            'description' => 'Public holiday observances.',
            'event_type' => 'public_holiday',
            'location' => null,
            'is_all_day' => true,
            'start_date' => '2026-10-19T00:00:00.000Z',
        ])->assertSessionHas('success', 'Event created.');

        $this->actingAs($admin)->post('/dashboard/calendar', [
            'title' => 'Main Gate Sensor Maintenance Window',
            'description' => 'Contractors servicing the north loop sensors.',
            'event_type' => 'maintenance_window',
            'location' => 'North Gate',
            'start_date' => '2026-10-21T08:00:00.000Z',
            'end_date' => '2026-10-21T12:00:00.000Z',
        ])->assertSessionHas('success', 'Event created.');

        $this->assertSame(2, CommunityEvent::count());
        $this->assertTrue(CommunityEvent::where('event_type', 'public_holiday')->exists());
        $this->assertTrue(CommunityEvent::where('event_type', 'maintenance_window')->exists());
    }

    public function test_entry_windows_come_from_configuration(): void
    {
        $homeowner = User::factory()->role(UserRole::Homeowner)->create();

        $this->actingAs($homeowner)->get('/dashboard/calendar')
            ->assertInertia(fn ($page) => $page
                ->has('entrySlots', 4)
                ->where('entrySlots.0.id', 'slot-morning')
                ->where('userStay', null)
            );

        config(['visitor_pass.entry_slots' => [['id' => 'slot-day', 'name' => 'Day', 'timeRange' => '08:00 AM – 05:00 PM', 'status' => 'Available', 'description' => 'Daytime only.']]]);

        $this->get('/dashboard/calendar')
            ->assertInertia(fn ($page) => $page->has('entrySlots', 1)->where('entrySlots.0.id', 'slot-day'));
    }

    public function test_an_all_day_event_can_be_created_without_an_end_date(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();

        $this->actingAs($admin)->post('/dashboard/calendar', [
            'title' => 'Estate Clean-up Day',
            'description' => 'Meet at the front gate with gloves.',
            'start_date' => '2026-10-10T05:00:00.000Z',
            'end_date' => null,
            'image_url' => null,
        ])->assertSessionHasNoErrors();

        $this->assertNull(CommunityEvent::sole()->end_date);
    }

    public function test_an_event_cannot_end_before_it_starts(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();

        $this->actingAs($admin)->post('/dashboard/calendar', [
            'title' => 'Backwards Event',
            'description' => 'This one ends before it begins.',
            'start_date' => '2026-10-03T18:00:00.000Z',
            'end_date' => '2026-10-03T14:00:00.000Z',
        ])->assertSessionHasErrors('end_date');

        $this->assertDatabaseCount('community_events', 0);
    }

    public function test_updating_an_event_schedule_notifies_residents_of_change(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $resident = User::factory()->role(UserRole::Homeowner)->create();

        $event = CommunityEvent::create([
            'title' => 'Annual General Meeting',
            'description' => 'Annual meeting of all homeowners.',
            'event_type' => 'meeting',
            'location' => 'Main Hall',
            'start_date' => '2026-10-25 18:00:00',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->patch("/dashboard/calendar/{$event->id}", [
            'title' => 'Annual General Meeting',
            'description' => 'Annual meeting of all homeowners.',
            'event_type' => 'meeting',
            'location' => 'Virtual / Zoom Link in Notes',
            'change_summary' => 'Moved to online Zoom conference due to weather.',
            'start_date' => '2026-10-25T19:00:00.000Z',
            'end_date' => null,
        ])->assertSessionHas('success', 'Event updated.');

        $this->assertSame('Virtual / Zoom Link in Notes', $event->fresh()->location);

        // Resident received notification of the schedule update
        $notification = InAppNotification::where('user_id', $resident->id)->latest('id')->first();
        $this->assertNotNull($notification);
        $this->assertStringContainsString('Annual General Meeting', $notification->title);
        $this->assertStringContainsString('Moved to online Zoom conference', $notification->body);
    }

    public function test_updating_description_only_does_not_spam_schedule_change_alerts(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $resident = User::factory()->role(UserRole::Homeowner)->create();

        $event = CommunityEvent::create([
            'title' => 'Yoga in the Park',
            'description' => 'Morning yoga session.',
            'event_type' => 'event',
            'start_date' => '2026-10-25 08:00:00',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->patch("/dashboard/calendar/{$event->id}", [
            'title' => 'Yoga in the Park',
            'description' => 'Morning yoga session. Please bring your own mat.',
            'event_type' => 'event',
            'start_date' => '2026-10-25 08:00:00',
        ])->assertSessionHas('success', 'Event updated.');

        $this->assertSame(0, InAppNotification::where('user_id', $resident->id)->count());
    }

    public function test_scheduled_command_reminds_residents_of_events_in_next_24_hours(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $resident = User::factory()->role(UserRole::Homeowner)->create();

        // 1. Event in 12 hours - should be reminded
        $soonEvent = CommunityEvent::create([
            'title' => 'Clubhouse Film Screening',
            'description' => 'Family movie night.',
            'event_type' => 'event',
            'location' => 'Clubhouse',
            'start_date' => now()->addHours(12),
            'created_by' => $admin->id,
        ]);

        // 2. Event in 3 days - should not be reminded yet
        CommunityEvent::create([
            'title' => 'Tennis Tournament',
            'description' => 'Annual doubles championship.',
            'event_type' => 'event',
            'start_date' => now()->addDays(3),
            'created_by' => $admin->id,
        ]);

        // 3. Event already reminded - should not be reminded again
        CommunityEvent::create([
            'title' => 'Early Bird Meeting',
            'description' => 'Morning check-in.',
            'event_type' => 'meeting',
            'start_date' => now()->addHours(6),
            'reminded_at' => now()->subHour(),
            'created_by' => $admin->id,
        ]);

        // Dry run
        $this->artisan('calendar:send-event-reminders', ['--dry-run' => true])
            ->expectsOutputToContain('1 event reminder(s) would be queued.')
            ->assertSuccessful();

        $this->assertNull($soonEvent->fresh()->reminded_at);

        // Real command execution
        $this->artisan('calendar:send-event-reminders')
            ->expectsOutputToContain('1 calendar event reminder(s) queued.')
            ->assertSuccessful();

        $this->assertNotNull($soonEvent->fresh()->reminded_at);

        // Verify notification delivered to resident
        $notification = InAppNotification::where('user_id', $resident->id)->sole();
        $this->assertStringContainsString('Clubhouse Film Screening', $notification->title);
        $this->assertStringContainsString('Reminder:', $notification->body);

        // Running again sends 0
        $this->artisan('calendar:send-event-reminders')
            ->expectsOutputToContain('0 calendar event reminder(s) queued.')
            ->assertSuccessful();
    }

    public function test_a_homeowner_cannot_create_an_event(): void
    {
        $homeowner = User::factory()->role(UserRole::Homeowner)->create();

        $this->actingAs($homeowner)->post('/dashboard/calendar', [
            'title' => 'Unofficial Party',
            'description' => 'Not an estate-sanctioned event.',
            'start_date' => '2026-10-03T14:00:00.000Z',
        ])->assertForbidden();

        $this->assertDatabaseCount('community_events', 0);
    }

    public function test_reminders_reach_the_whole_community_as_an_announcement_would(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $tenant = User::factory()->role(UserRole::TemporaryHomeowner)->create();
        $owner = User::factory()->role(UserRole::Homeowner)->create();
        $leftTheCommunity = User::factory()->role(UserRole::Homeowner)->inactive()->create();
        CommunityEvent::create([
            'title' => 'Pool Party', 'description' => 'Bring towels.', 'start_date' => now()->addHours(3), 'created_by' => $admin->id,
        ]);

        $this->artisan('calendar:send-event-reminders')->assertSuccessful();

        $this->assertSame(1, InAppNotification::where('user_id', $tenant->id)->count(), 'tenants are told too');
        $this->assertSame(1, InAppNotification::where('user_id', $owner->id)->count());
        $this->assertSame(0, InAppNotification::where('user_id', $leftTheCommunity->id)->count(), 'deactivated accounts are not');
    }

    public function test_each_event_is_reminded_within_its_own_window(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $make = fn (string $title, int $inHours, ?int $window = null) => CommunityEvent::create(array_filter([
            'title' => $title, 'description' => 'x', 'start_date' => now()->addHours($inHours),
            'remind_before_hours' => $window, 'created_by' => $admin->id,
        ], fn ($v) => $v !== null));

        $make('Default window, 30h away', 30);
        $longWindow = $make('72h window, 48h away', 48, 72);
        $shortWindow = $make('2h window, 5h away', 5, 2);

        $this->artisan('calendar:send-event-reminders')->expectsOutputToContain('1 calendar event reminder(s) queued.');

        $this->assertNotNull($longWindow->fresh()->reminded_at);
        $this->assertNull($shortWindow->fresh()->reminded_at);
    }

    public function test_a_rescheduled_event_is_reminded_again_before_its_new_time(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $event = CommunityEvent::create([
            'title' => 'AGM', 'description' => 'Annual meeting.', 'start_date' => now()->addHours(10),
            'reminded_at' => now()->subHour(), 'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->patch("/dashboard/calendar/{$event->id}", [
            'start_date' => now()->addDays(4)->toIso8601String(),
        ])->assertSessionHas('success', 'Event updated.');

        $this->assertNull($event->fresh()->reminded_at, 'the old reminder no longer counts');
    }
}
