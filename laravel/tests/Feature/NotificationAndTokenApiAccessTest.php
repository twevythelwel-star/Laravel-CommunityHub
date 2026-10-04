<?php

namespace Tests\Feature;

use App\Enums\TokenAbility;
use App\Enums\UserRole;
use App\Models\InAppNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sending a notification reaches residents' inboxes, phones and email in the
 * estate's name, and a token is a key to the API. Neither may be had without
 * signing in, and a signed-in account gets no more than its role allows.
 */
class NotificationAndTokenApiAccessTest extends TestCase
{
    use RefreshDatabase;

    private function bearer(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('Device', TokenAbility::forUser($user))->plainTextToken];
    }

    public function test_sending_a_notification_needs_a_token(): void
    {
        $this->postJson('/api/v1/notifications/send', [
            'phone' => '+18765550100',
            'title' => 'Your gate code has changed',
            'body' => 'Confirm at a link of my choosing.',
        ])->assertUnauthorized();
    }

    public function test_a_resident_cannot_send_notifications_to_others(): void
    {
        $resident = User::factory()->role(UserRole::Homeowner)->create();
        $neighbour = User::factory()->role(UserRole::Homeowner)->create();

        foreach (['/api/v1/notifications/send', '/api/v1/notifications/dispatch'] as $uri) {
            $this->postJson($uri, [
                'user_id' => $neighbour->id,
                'title' => 'Fake notice',
                'body' => 'Pay your dues to this account instead.',
            ], $this->bearer($resident))->assertForbidden();
        }

        $this->assertSame(0, InAppNotification::where('user_id', $neighbour->id)->count());
    }

    public function test_the_inbox_needs_a_token(): void
    {
        $this->getJson('/api/v1/notifications/inbox')->assertUnauthorized();
    }

    public function test_the_inbox_shows_only_the_callers_own_notifications(): void
    {
        $resident = User::factory()->role(UserRole::Homeowner)->create();
        $neighbour = User::factory()->role(UserRole::Homeowner)->create();
        InAppNotification::create([
            'user_id' => $neighbour->id,
            'title' => 'Private: your invoice is overdue',
            'body' => 'Balance J$75,000.',
            'category' => 'billing',
        ]);

        $this->getJson("/api/v1/notifications/inbox?user_id={$neighbour->id}", $this->bearer($resident))
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('unread_count', 0);
    }

    public function test_a_resident_cannot_mint_a_token_wider_than_their_role(): void
    {
        $resident = User::factory()->role(UserRole::Homeowner)->create();

        $this->postJson('/api/v1/tokens', [
            'name' => 'Escalated',
            'abilities' => [TokenAbility::SecurityManage->value],
        ], $this->bearer($resident))->assertStatus(422);

        $this->assertFalse($resident->fresh()->tokens->contains(fn ($token) => $token->can(TokenAbility::SecurityManage->value)));
    }

    public function test_a_token_may_be_narrower_than_its_account(): void
    {
        $guard = User::factory()->role(UserRole::Security)->create();

        $this->postJson('/api/v1/tokens', [
            'name' => 'Scanner only',
            'abilities' => [TokenAbility::GatePassScan->value],
        ], $this->bearer($guard))->assertCreated();
    }
}
