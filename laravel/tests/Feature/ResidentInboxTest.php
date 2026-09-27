<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Notification;
use App\Models\ResidentMessage;
use App\Models\User;
use App\Services\NotificationEngine\NotificationEngine;
use App\Services\NotificationEngine\NotificationPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Each resident's inbox is theirs alone: they see and change only their own
 * messages, and the inbox channel only ever writes to one person.
 */
class ResidentInboxTest extends TestCase
{
    use RefreshDatabase;

    private function resident(): User
    {
        return User::factory()->role(UserRole::Homeowner)->create();
    }

    public function test_a_resident_sees_only_their_own_messages_newest_first(): void
    {
        $me = $this->resident();
        ResidentMessage::factory()->for($me)->create(['title' => 'Older', 'created_at' => now()->subDay()]);
        ResidentMessage::factory()->for($me)->create(['title' => 'Newer']);
        ResidentMessage::factory()->for($this->resident())->create(['title' => 'Someone else']);

        $this->actingAs($me)->get(route('dashboard.inbox'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Inbox')
                ->has('messages', 2)
                ->where('messages.0.title', 'Newer')
                ->where('messages.1.title', 'Older'));
    }

    public function test_the_unread_count_is_shared_with_every_page(): void
    {
        $me = $this->resident();
        ResidentMessage::factory()->for($me)->count(2)->create();
        ResidentMessage::factory()->for($me)->read()->create();
        ResidentMessage::factory()->for($this->resident())->create();

        $this->actingAs($me)->get(route('dashboard.inbox'))
            ->assertInertia(fn (Assert $page) => $page->where('inboxUnread', 2));
    }

    public function test_a_resident_marks_one_message_and_then_all_as_read(): void
    {
        $me = $this->resident();
        [$first, $second] = ResidentMessage::factory()->for($me)->count(2)->create();

        $this->actingAs($me)->post(route('dashboard.inbox.read', $first->id))->assertRedirect();
        $this->assertNotNull($first->fresh()->read_at);
        $this->assertNull($second->fresh()->read_at);

        $this->actingAs($me)->post(route('dashboard.inbox.read-all'))->assertRedirect();
        $this->assertNotNull($second->fresh()->read_at);
    }

    public function test_someone_elses_message_cannot_be_touched_or_detected(): void
    {
        $theirs = ResidentMessage::factory()->for($this->resident())->create();
        $me = $this->resident();

        $this->actingAs($me)->post(route('dashboard.inbox.read', $theirs->id))->assertNotFound();
        $this->actingAs($me)->post(route('dashboard.inbox.read-all'));

        $this->assertNull($theirs->fresh()->read_at);
    }

    public function test_the_inbox_requires_sign_in(): void
    {
        $this->get(route('dashboard.inbox'))->assertRedirect(route('login'));
    }

    public function test_the_inbox_channel_writes_to_one_person_and_never_to_the_notice_board(): void
    {
        $me = $this->resident();

        $result = app(NotificationEngine::class)->send(['inbox'], new NotificationPayload(
            title: 'Hello', body: 'Just for you', user: $me, actionUrl: '/dashboard/visitors',
        ))['inbox'];

        $this->assertTrue($result->isSent());
        $this->assertSame('/dashboard/visitors', $me->inboxMessages()->sole()->action_url);
        $this->assertSame(0, Notification::count());
    }

    public function test_the_inbox_channel_refuses_a_message_with_no_person_or_with_an_audience(): void
    {
        $engine = app(NotificationEngine::class);

        $noPerson = $engine->send(['inbox'], new NotificationPayload(title: 'T', body: 'B'))['inbox'];
        $withAudience = $engine->send(['inbox'], new NotificationPayload(title: 'T', body: 'B', user: $this->resident(), toEveryone: true))['inbox'];

        $this->assertSame('failed', $noPerson->status);
        $this->assertSame('failed', $withAudience->status);
        $this->assertSame(0, ResidentMessage::count());
    }
}
