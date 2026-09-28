<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The websocket details handed to token clients (the mobile shell and the
 * handheld scanners) at sign-in and from /api/auth/me.
 */
class DeviceRealtimeTest extends TestCase
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
            'app.url' => 'https://hub.example.org',
        ]);
    }

    private function signIn(User $user): TestResponse
    {
        return $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'Gate 1 handheld',
        ]);
    }

    /** @return list<string> */
    private function channelNames(TestResponse $response): array
    {
        return array_column($response->json('realtime.channels'), 'name');
    }

    public function test_devices_are_told_to_poll_when_reverb_is_off(): void
    {
        $user = User::factory()->role(UserRole::Homeowner)->create();

        $this->signIn($user)->assertOk()->assertJsonPath('realtime', null);
        $this->actingAs($user, 'sanctum')->getJson('/api/auth/me')->assertOk()->assertJsonPath('realtime', null);
    }

    public function test_sign_in_gives_a_resident_their_own_channel_and_the_alerts(): void
    {
        $this->useReverb();
        $resident = User::factory()->role(UserRole::Homeowner)->create();

        $response = $this->signIn($resident)->assertOk()
            ->assertJsonPath('realtime.broadcaster', 'reverb')
            ->assertJsonPath('realtime.key', 'public-key')
            ->assertJsonPath('realtime.host', 'hub.example.org')
            ->assertJsonPath('realtime.port', 443)
            ->assertJsonPath('realtime.scheme', 'https')
            ->assertJsonPath('realtime.origin', 'https://hub.example.org')
            ->assertJsonPath('realtime.authEndpoint', url('/api/broadcasting/auth'))
            ->assertJsonPath('realtime.channels.0.events', ['inbox.message', 'visitor.checked-in'])
            ->assertJsonPath('realtime.channels.1.events', ['security.alert']);

        $this->assertSame(
            ["private-user.{$resident->id}", 'private-community-alerts'],
            $this->channelNames($response),
        );
        $this->assertStringNotContainsString('very-secret', $response->getContent());
    }

    public function test_a_gate_scanner_also_gets_the_gatehouse_feed(): void
    {
        $this->useReverb();
        $guard = User::factory()->role(UserRole::Security)->create();

        $response = $this->actingAs($guard, 'sanctum')->getJson('/api/auth/me')->assertOk();

        $this->assertContains('private-gatehouse-stream', $this->channelNames($response));
        $this->assertSame(['visitor.checked-in'], $response->json('realtime.channels.2.events'));
    }

    public function test_every_channel_a_device_is_given_is_one_its_token_can_join(): void
    {
        $this->useReverb();

        foreach ([UserRole::Homeowner, UserRole::Security, UserRole::Staff] as $role) {
            $user = User::factory()->role($role)->create();
            $channels = $this->channelNames($this->actingAs($user, 'sanctum')->getJson('/api/auth/me'));

            // Check each against the real channel rules, as Reverb would ask.
            config([
                'broadcasting.default' => 'authcheck',
                'broadcasting.connections.authcheck' => ['driver' => 'redis', 'connection' => 'default'],
            ]);
            require base_path('routes/channels.php');

            $token = $user->createToken('handheld')->plainTextToken;
            foreach ($channels as $channel) {
                $this->app['auth']->forgetGuards();
                $this->withToken($token)
                    ->postJson('/api/broadcasting/auth', ['channel_name' => $channel, 'socket_id' => '1234.5678'])
                    ->assertOk();
            }

            $this->useReverb();
        }
    }
}
