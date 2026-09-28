<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Events\ResidentMessageCreated;
use App\Events\VisitorCheckedInEvent;
use App\Models\User;
use App\Models\Visitor;
use App\Services\NotificationEngine\NotificationEngine;
use App\Services\NotificationEngine\NotificationPayload;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Live updates over Reverb: what is sent, to whom, and that a websocket
 * problem never costs a resident their message.
 */
class RealtimeUpdatesTest extends TestCase
{
    use RefreshDatabase;

    private function useReverb(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'public-key',
            'broadcasting.connections.reverb.secret' => 'very-secret',
            'broadcasting.connections.reverb.app_id' => 'app-id',
            'broadcasting.browser.host' => 'hub.example.org',
            'broadcasting.browser.port' => 443,
            'broadcasting.browser.scheme' => 'https',
        ]);
    }

    public function test_an_arrival_broadcasts_a_summary_not_the_visitor_record(): void
    {
        $host = User::factory()->role(UserRole::Homeowner)->create();
        $visitor = Visitor::create([
            'name' => 'Liam', 'type' => 'One-time', 'status' => 'Checked In', 'expected_at' => now(),
            'homeowner_id' => $host->id, 'homeowner_name' => 'Marcus', 'vehicle' => 'Blue Toyota',
            'id_number' => 'JM-1234-5678', 'contact' => '+18765550199', 'share_token' => 'secret-pass-token',
        ]);

        $sent = json_encode((new VisitorCheckedInEvent($visitor, 'Main Gate'))->broadcastWith());

        $this->assertStringContainsString('Liam', $sent);
        $this->assertStringContainsString('Main Gate', $sent);
        foreach (['JM-1234-5678', '+18765550199', 'secret-pass-token'] as $private) {
            $this->assertStringNotContainsString($private, $sent);
        }
    }

    public function test_an_inbox_message_is_announced_on_that_residents_own_channel(): void
    {
        Event::fake([ResidentMessageCreated::class]);
        $resident = User::factory()->role(UserRole::Homeowner)->create();

        app(NotificationEngine::class)->send(['inbox'], new NotificationPayload(title: 'Hello', body: 'B', user: $resident));

        Event::assertDispatched(ResidentMessageCreated::class, function (ResidentMessageCreated $e) use ($resident) {
            return $e->broadcastOn()[0]->name === "private-user.{$resident->id}"
                && $e->broadcastWith()['unread'] === 1
                && $e->broadcastWith()['title'] === 'Hello'
                && ! array_key_exists('body', $e->broadcastWith());
        });
    }

    public function test_the_message_is_kept_even_when_reverb_cannot_be_reached(): void
    {
        $this->useReverb();
        // Nothing listens here: publishing to Reverb fails.
        config(['broadcasting.connections.reverb.options' => ['host' => '127.0.0.1', 'port' => 1, 'scheme' => 'http', 'useTLS' => false]]);
        $resident = User::factory()->role(UserRole::Homeowner)->create();
        Exceptions::fake();

        $result = app(NotificationEngine::class)->send(['inbox'], new NotificationPayload(title: 'Hello', body: 'B', user: $resident))['inbox'];

        Exceptions::assertReported(BroadcastException::class);
        $this->assertTrue($result->isSent());
        $this->assertSame(1, $resident->inboxMessages()->count());
    }

    public function test_pages_get_no_realtime_config_unless_reverb_is_on(): void
    {
        $this->actingAs(User::factory()->role(UserRole::Homeowner)->create())->get(route('dashboard.inbox'))
            ->assertInertia(fn (Assert $page) => $page->where('realtime', null));
    }

    public function test_pages_get_the_public_key_and_their_own_channels_but_never_the_secret(): void
    {
        $this->useReverb();
        $resident = User::factory()->role(UserRole::Homeowner)->create();
        $guard = User::factory()->role(UserRole::Security)->create();

        $response = $this->actingAs($resident)->get(route('dashboard.inbox'));
        $response->assertInertia(fn (Assert $page) => $page
            ->where('realtime.key', 'public-key')
            ->where('realtime.host', 'hub.example.org')
            ->where('realtime.userChannel', "user.{$resident->id}")
            ->where('realtime.gateFeed', false));
        $this->assertStringNotContainsString('very-secret', $response->getContent());

        $this->actingAs($guard)->get(route('dashboard.inbox'))
            ->assertInertia(fn (Assert $page) => $page->where('realtime.gateFeed', true));
    }

    public function test_a_visitor_arrival_still_succeeds_when_reverb_is_down(): void
    {
        $this->useReverb();
        config(['broadcasting.connections.reverb.options' => ['host' => '127.0.0.1', 'port' => 1, 'scheme' => 'http', 'useTLS' => false]]);
        $host = User::factory()->role(UserRole::Homeowner)->create();
        $visitor = Visitor::create([
            'name' => 'Liam', 'type' => 'One-time', 'status' => 'Checked In',
            'expected_at' => now(), 'homeowner_id' => $host->id,
        ]);
        Exceptions::fake();

        event(new VisitorCheckedInEvent($visitor, 'Main Gate'));

        Exceptions::assertReported(BroadcastException::class);
        $this->assertSame(1, $host->inboxMessages()->count());
    }
}
