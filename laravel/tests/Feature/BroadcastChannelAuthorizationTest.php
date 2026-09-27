<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Events\SecurityAlertBroadcastEvent;
use App\Events\VisitorCheckedInEvent;
use App\Models\User;
use App\Models\Visitor;
use App\Models\Warning;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Who may subscribe to each websocket channel (routes/channels.php).
 *
 * The default "null" broadcaster approves everything without looking, so
 * these run against the Redis broadcaster, which applies the channel rules
 * the same way Pusher and Reverb do and needs no server to authorise.
 */
class BroadcastChannelAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'broadcasting.default' => 'authcheck',
            'broadcasting.connections.authcheck' => ['driver' => 'redis', 'connection' => 'default'],
        ]);

        // Channel rules attach to the broadcaster configured at boot (the null
        // one here), so load them again for this one, as production would.
        require base_path('routes/channels.php');
    }

    /**
     * Within one test the app keeps the last signed-in user between requests;
     * a real request starts fresh. Forget it, so each subscription is judged
     * on its own credentials alone.
     */
    private function freshRequest(): void
    {
        $this->app['auth']->forgetGuards();
    }

    private function subscribe(?User $as, string $channel): TestResponse
    {
        $this->freshRequest();
        $request = $as ? $this->actingAs($as) : $this;

        return $request->post('/broadcasting/auth', ['channel_name' => "private-{$channel}", 'socket_id' => '1234.5678']);
    }

    private function subscribeWithToken(User $user, string $channel): TestResponse
    {
        $this->freshRequest();
        $token = $user->createToken('scanner')->plainTextToken;

        return $this->withToken($token)->postJson('/api/broadcasting/auth', ['channel_name' => "private-{$channel}", 'socket_id' => '1234.5678']);
    }

    private function user(UserRole $role = UserRole::Homeowner): User
    {
        return User::factory()->role($role)->create();
    }

    public function test_a_resident_may_listen_to_their_own_channel_only(): void
    {
        $me = $this->user();
        $other = $this->user();

        $this->subscribe($me, "user.{$me->id}")->assertOk();
        $this->subscribe($me, "user.{$other->id}")->assertForbidden();
    }

    public function test_only_gate_staff_may_listen_to_the_gatehouse_feed(): void
    {
        $this->subscribe($this->user(UserRole::Security), 'gatehouse-stream')->assertOk();
        $this->subscribe($this->user(UserRole::Admin), 'gatehouse-stream')->assertOk();

        $this->subscribe($this->user(UserRole::Homeowner), 'gatehouse-stream')->assertForbidden();
        $this->subscribe($this->user(UserRole::Staff), 'gatehouse-stream')->assertForbidden();
    }

    public function test_any_signed_in_member_may_receive_safety_alerts_but_no_one_outside(): void
    {
        $this->subscribe($this->user(UserRole::Homeowner), 'community-alerts')->assertOk();
        $this->subscribe($this->user(UserRole::Staff), 'community-alerts')->assertOk();

        $this->assertNotSame(200, $this->subscribe(null, 'community-alerts')->getStatusCode());
    }

    public function test_a_deactivated_account_may_subscribe_to_nothing(): void
    {
        $gone = User::factory()->role(UserRole::Security)->inactive()->create();

        foreach (["user.{$gone->id}", 'gatehouse-stream', 'community-alerts'] as $channel) {
            $this->assertNotSame(200, $this->subscribe($gone, $channel)->getStatusCode(), $channel);
            $this->subscribeWithToken($gone, $channel)->assertForbidden();
        }
    }

    public function test_a_channel_with_no_rule_is_refused(): void
    {
        $this->subscribe($this->user(UserRole::Admin), 'something-else')->assertForbidden();
    }

    public function test_token_clients_get_the_same_rules(): void
    {
        $guard = $this->user(UserRole::Security);
        $resident = $this->user();

        $this->subscribeWithToken($guard, 'gatehouse-stream')->assertOk();
        $this->subscribeWithToken($resident, "user.{$resident->id}")->assertOk();
        $this->subscribeWithToken($resident, 'gatehouse-stream')->assertForbidden();
        $this->freshRequest();
        $this->withoutToken()->postJson('/api/broadcasting/auth', ['channel_name' => 'private-community-alerts', 'socket_id' => '1.2'])->assertUnauthorized();
    }

    public function test_every_broadcast_event_uses_only_private_channels(): void
    {
        $host = $this->user();
        $visitor = Visitor::create([
            'name' => 'Liam', 'type' => 'One-time', 'status' => 'Checked In',
            'expected_at' => now(), 'homeowner_id' => $host->id,
        ]);
        $warning = Warning::create([
            'title' => 'Fence damage', 'description' => 'North section.',
            'author_name' => 'Security', 'issued_at' => now(),
        ]);

        $channels = [
            ...(new VisitorCheckedInEvent($visitor))->broadcastOn(),
            ...(new SecurityAlertBroadcastEvent($warning))->broadcastOn(),
        ];

        foreach ($channels as $channel) {
            $this->assertInstanceOf(PrivateChannel::class, $channel, "{$channel->name} is public");
        }
    }
}
