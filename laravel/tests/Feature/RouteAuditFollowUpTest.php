<?php

namespace Tests\Feature;

use App\Enums\TokenAbility;
use App\Enums\UserRole;
use App\Livewire\Notifications\UniversalNotificationHub;
use App\Models\InAppNotification;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Models\WebhookSubscription;
use App\Services\Payments\Modular\Drivers\CashierModuleDriver;
use App\Services\Payments\Modular\DTOs\CustomerPortalRequest;
use App\Services\Payments\Modular\PaymentGatewayManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

/**
 * Follow-ups from auditing every registered route: endpoints that were
 * signed-in but unscoped, or public when they should not have been.
 */
class RouteAuditFollowUpTest extends TestCase
{
    use RefreshDatabase;

    private function as(UserRole $role): User
    {
        return User::factory()->role($role)->create();
    }

    /** Forgets the guards so a test can switch between tokens. */
    private function bearer(User $user): array
    {
        $this->app['auth']->forgetGuards();

        return ['Authorization' => 'Bearer '.$user->createToken('Device', TokenAbility::forUser($user))->plainTextToken];
    }

    private function delivery(User $user, string $recipient): NotificationDelivery
    {
        return NotificationDelivery::create([
            'user_id' => $user->id,
            'channel' => 'sms',
            'recipient' => $recipient,
            'provider' => 'twilio',
            'status' => 'delivered',
        ]);
    }

    // ── Notification delivery log ───────────────────────────────────

    public function test_a_resident_sees_only_their_own_deliveries(): void
    {
        $resident = $this->as(UserRole::Homeowner);
        $this->delivery($resident, '+18765550101');
        $this->delivery($this->as(UserRole::Homeowner), '+18765550202');

        $response = $this->getJson('/api/v1/notifications/deliveries', $this->bearer($resident))->assertOk();

        $this->assertSame(['+18765550101'], collect($response->json('data'))->pluck('recipient')->all());
    }

    public function test_an_administrator_sees_every_delivery(): void
    {
        $this->delivery($this->as(UserRole::Homeowner), '+18765550101');
        $this->delivery($this->as(UserRole::Homeowner), '+18765550202');

        $this->getJson('/api/v1/notifications/deliveries', $this->bearer($this->as(UserRole::Admin)))
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    // ── Notification hub page ───────────────────────────────────────

    public function test_a_resident_cannot_send_from_the_notification_hub(): void
    {
        Livewire::actingAs($this->as(UserRole::Homeowner))
            ->test(UniversalNotificationHub::class)
            ->set('title', 'Your dues are overdue')
            ->set('body', 'Pay at this link.')
            ->set('recipientEmail', 'neighbour@example.com')
            ->call('triggerDispatch')
            ->assertForbidden();
    }

    public function test_the_hub_shows_a_resident_only_their_own_deliveries(): void
    {
        $resident = $this->as(UserRole::Homeowner);
        $this->delivery($resident, '+18765550101');
        $this->delivery($this->as(UserRole::Homeowner), '+18765550202');

        $deliveries = Livewire::actingAs($resident)->test(UniversalNotificationHub::class)->viewData('deliveries');

        $this->assertSame(['+18765550101'], collect($deliveries->items())->pluck('recipient')->all());
    }

    public function test_marking_read_only_touches_your_own_notification(): void
    {
        $theirs = InAppNotification::create([
            'user_id' => $this->as(UserRole::Homeowner)->id,
            'title' => 'Private', 'body' => 'Not yours.', 'category' => 'general',
        ]);

        Livewire::actingAs($this->as(UserRole::Homeowner))
            ->test(UniversalNotificationHub::class)
            ->call('markAsRead', $theirs->id);

        $this->assertNull($theirs->fresh()->read_at);
    }

    // ── Webhook subscriptions ───────────────────────────────────────

    public function test_a_resident_cannot_manage_webhook_subscriptions(): void
    {
        $headers = $this->bearer($this->as(UserRole::Homeowner));

        $this->getJson('/api/v1/webhooks/subscriptions', $headers)->assertForbidden();
        $this->postJson('/api/v1/webhooks/subscriptions', [
            'name' => 'Mine', 'url' => 'https://attacker.example/hook', 'events' => ['*'],
        ], $headers)->assertForbidden();

        $this->assertSame(0, WebhookSubscription::count());
    }

    public function test_a_signed_in_browser_user_cannot_reach_someone_elses_subscription(): void
    {
        $subscription = WebhookSubscription::create([
            'user_id' => $this->as(UserRole::SystemAdmin)->id,
            'name' => 'Accounting', 'url' => 'https://erp.example/hook',
            'secret' => WebhookSubscription::generateSecret(), 'events' => ['payment.succeeded'], 'is_active' => true,
        ]);

        // A session (cookie) request carries Sanctum's TransientToken, whose
        // tokenCan('*') is true — the old ownership check let this through.
        $this->actingAs($this->as(UserRole::Admin))
            ->deleteJson("/api/v1/webhooks/subscriptions/{$subscription->id}")
            ->assertForbidden();

        $this->assertModelExists($subscription);
    }

    public function test_a_system_admin_can_create_a_subscription(): void
    {
        $this->postJson('/api/v1/webhooks/subscriptions', [
            'name' => 'ERP', 'url' => 'https://erp.example/hook', 'events' => ['payment.succeeded'],
        ], $this->bearer($this->as(UserRole::SystemAdmin)))->assertSuccessful();
    }

    // ── Incoming webhooks ───────────────────────────────────────────

    public function test_an_unconfigured_incoming_webhook_is_refused(): void
    {
        $this->postJson('/api/v1/webhooks/incoming/unknownvendor', ['event' => 'x'])->assertNotFound();
    }

    public function test_an_incoming_webhook_is_verified_against_its_secret(): void
    {
        config(['services.webhooks.acme.secret' => 'acme_secret']);
        $body = json_encode(['event' => 'gate.opened']);

        $this->call('POST', '/api/v1/webhooks/incoming/acme', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_WEBHOOK_SIGNATURE' => 'sha256=wrong',
        ], $body)->assertUnauthorized();

        $this->call('POST', '/api/v1/webhooks/incoming/acme', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_WEBHOOK_SIGNATURE' => 'sha256='.hash_hmac('sha256', $body, 'acme_secret'),
        ], $body)->assertOk();
    }

    // ── Modular payments ────────────────────────────────────────────

    public function test_the_billing_portal_is_always_the_callers_own(): void
    {
        $resident = $this->as(UserRole::Homeowner);
        $neighbour = $this->as(UserRole::Homeowner);

        $driver = Mockery::mock(CashierModuleDriver::class)->makePartial();
        $driver->shouldReceive('createBillingPortalSession')
            ->once()
            ->withArgs(fn (CustomerPortalRequest $request) => $request->customerId === (string) $resident->id)
            ->passthru();

        $this->mock(PaymentGatewayManager::class, fn ($manager) => $manager->shouldReceive('subscriptionDriver')->andReturn($driver));

        $this->postJson('/api/v1/payments/billing-portal', [
            'return_url' => 'https://hub.example/billing',
            'customer_id' => (string) $neighbour->id,
        ], $this->bearer($resident))->assertOk();
    }

    public function test_a_resident_cannot_cancel_an_arbitrary_subscription(): void
    {
        $this->deleteJson('/api/v1/payments/subscriptions/sub_someone_else', [], $this->bearer($this->as(UserRole::Homeowner)))
            ->assertForbidden();
    }

    // ── Horizon and API docs ────────────────────────────────────────

    public function test_horizon_is_the_system_admins(): void
    {
        foreach ([UserRole::Security, UserRole::Admin] as $role) {
            $this->assertFalse(Gate::forUser($this->as($role))->allows('viewHorizon'), "{$role->value} should not see Horizon");
            $this->actingAs($this->as($role))->getJson('/horizon/api/stats')->assertForbidden();
        }

        $this->assertTrue(Gate::forUser($this->as(UserRole::SystemAdmin))->allows('viewHorizon'));
    }

    public function test_the_api_reference_is_not_public(): void
    {
        $this->get('/docs/api')->assertForbidden();
        $this->actingAs($this->as(UserRole::Admin))->get('/docs/api')->assertForbidden();
        $this->actingAs($this->as(UserRole::SystemAdmin))->get('/docs/api')->assertOk();
    }
}
