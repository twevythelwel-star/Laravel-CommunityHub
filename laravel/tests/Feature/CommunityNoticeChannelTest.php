<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Notification;
use App\Models\User;
use App\Services\NotificationEngine\NotificationEngine;
use App\Services\NotificationEngine\NotificationPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The community notice channel publishes to the notice board, which residents
 * read by role. It must never be mistaken for a personal message: it needs an
 * explicit audience, refuses a personal recipient, and never signs a notice
 * with the name of the person a message is about.
 */
class CommunityNoticeChannelTest extends TestCase
{
    use RefreshDatabase;

    private function engine(): NotificationEngine
    {
        return app(NotificationEngine::class);
    }

    public function test_a_notice_with_no_audience_is_refused_and_nothing_is_posted(): void
    {
        $result = $this->engine()->send(['community_notice'], new NotificationPayload(title: 'Hello', body: 'Body'))['community_notice'];

        $this->assertSame('failed', $result->status);
        $this->assertStringContainsString('audience', $result->reason);
        $this->assertSame(0, Notification::count());
    }

    public function test_a_personal_message_is_refused_rather_than_published(): void
    {
        $resident = User::factory()->role(UserRole::Homeowner)->create();

        $result = $this->engine()->send(['community_notice'], new NotificationPayload(
            title: 'Your booking was cancelled',
            body: 'Private details',
            recipient: $resident->email,
            user: $resident,
            toEveryone: true,
        ))['community_notice'];

        $this->assertSame('failed', $result->status);
        $this->assertSame(0, Notification::count());
    }

    public function test_a_notice_for_chosen_roles_is_posted_to_those_roles_by_its_author(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();

        $result = $this->engine()->broadcast('Water outage', 'Saturday 09:00-14:00.', ['Homeowner'], $admin)['community_notice'];

        $this->assertTrue($result->isSent());
        $notice = Notification::sole();
        $this->assertSame(['Homeowner'], $notice->target_roles);
        $this->assertSame($admin->id, $notice->author_id);
    }

    public function test_everyone_must_be_asked_for_explicitly(): void
    {
        $this->engine()->broadcastToEveryone('Storm advisory', 'Secure outdoor items.');

        $notice = Notification::sole();
        $this->assertNull($notice->target_roles);
        $this->assertTrue($notice->targetsRole(UserRole::Security->value));
    }

    public function test_the_subject_of_a_message_is_never_used_as_the_author(): void
    {
        $resident = User::factory()->role(UserRole::Homeowner)->create(['display_name' => 'Marcus V']);

        $this->engine()->send(['community_notice'], new NotificationPayload(
            title: 'Gate maintenance',
            body: 'North gate closed Monday.',
            user: $resident,
            targetRoles: ['Homeowner'],
        ));

        $notice = Notification::sole();
        $this->assertNull($notice->author_id);
        $this->assertNotSame('Marcus V', $notice->author_name);
    }

    public function test_the_old_in_app_name_no_longer_posts_anything(): void
    {
        $result = $this->engine()->send(['in_app'], new NotificationPayload(title: 'T', body: 'B', toEveryone: true))['in_app'];

        $this->assertSame('failed', $result->status);
        $this->assertSame(0, Notification::count());
    }
}
