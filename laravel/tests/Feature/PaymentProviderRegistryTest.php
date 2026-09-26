<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Payments\Providers\Contracts\ConfirmsReturns;
use App\Services\Payments\Providers\Contracts\HandlesWebhooks;
use App\Services\Payments\Providers\Contracts\RefundsPayments;
use App\Services\Payments\Providers\ProviderRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Which provider handles each channel. Card goes to the processor the estate
 * configured — nothing is hard-wired to Stripe, whose availability does not
 * include Jamaica — and is off when none is set up.
 */
class PaymentProviderRegistryTest extends TestCase
{
    use RefreshDatabase;

    private function registry(): ProviderRegistry
    {
        return app(ProviderRegistry::class);
    }

    private function wipayConfigured(): void
    {
        config(['payments.providers.wipay' => [
            'account_number' => '1234567890', 'api_key' => '123', 'environment' => 'sandbox',
            'country_code' => 'JM', 'fee_structure' => 'merchant_absorb', 'origin' => 'CommunityHub',
        ]]);
    }

    public function test_with_nothing_configured_card_is_off(): void
    {
        config(['payments.card_provider' => null, 'services.stripe.secret' => null]);

        $this->assertNull($this->registry()->cardProvider());

        $user = User::factory()->role(UserRole::Homeowner)->create();
        $invoice = Invoice::create([
            'user_id' => $user->id, 'reference' => 'INV-REG-1', 'amount_minor' => 10_000, 'currency' => 'JMD',
            'period_start' => now()->startOfMonth(), 'period_end' => now()->endOfMonth(), 'due_on' => now()->addDays(5), 'status' => 'Unpaid',
        ]);

        $this->actingAs($user)
            ->post(route('dashboard.billing.pay'), ['channel' => 'card', 'amount' => 100, 'invoice_id' => $invoice->id])
            ->assertSessionHasErrors('channel');

        $this->actingAs($user)
            ->get(route('dashboard.billing'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('payments.cardCheckout', false)
                ->where('payments.cardProvider', null));
    }

    public function test_stripe_is_used_only_where_its_keys_are_set(): void
    {
        config(['payments.card_provider' => null, 'services.stripe.secret' => 'sk_test_registry']);

        $this->assertSame('stripe', $this->registry()->cardProvider()?->key());
    }

    public function test_the_configured_processor_wins(): void
    {
        $this->wipayConfigured();
        config(['payments.card_provider' => 'wipay', 'services.stripe.secret' => 'sk_test_registry']);

        $this->assertSame('wipay', $this->registry()->cardProvider()?->key());

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard.billing'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('payments.cardCheckout', true)
                ->where('payments.cardProvider', 'WiPay'));
    }

    public function test_a_chosen_processor_that_is_not_set_up_does_not_fall_back_to_another(): void
    {
        // WiPay chosen but its keys missing: card is off, not silently Stripe.
        config(['payments.card_provider' => 'wipay', 'payments.providers.wipay.api_key' => null, 'services.stripe.secret' => 'sk_test_registry']);

        $this->assertNull($this->registry()->cardProvider());
    }

    public function test_the_office_and_the_wallet_cannot_be_chosen_as_the_card_processor(): void
    {
        config(['payments.card_provider' => 'office']);

        $this->assertNull($this->registry()->cardProvider());
    }

    public function test_every_other_channel_goes_to_its_provider(): void
    {
        foreach (['bank_wire', 'cash_office', 'qr_code', 'nfc_pos', 'apple_pay', 'google_pay', 'samsung_wallet', 'zelle', 'cash_app'] as $channel) {
            $this->assertSame('office', $this->registry()->forChannel($channel)->key(), $channel);
        }

        $this->assertSame('internal', $this->registry()->forChannel('wallet')->key());
    }

    public function test_providers_declare_only_what_they_can_do(): void
    {
        $stripe = $this->registry()->byKey('stripe');
        $wipay = $this->registry()->byKey('wipay');
        $office = $this->registry()->byKey('office');

        $this->assertInstanceOf(HandlesWebhooks::class, $stripe);
        $this->assertInstanceOf(RefundsPayments::class, $stripe);

        // WiPay's Payments API has no webhooks and no refunds.
        $this->assertInstanceOf(ConfirmsReturns::class, $wipay);
        $this->assertNotInstanceOf(HandlesWebhooks::class, $wipay);
        $this->assertNotInstanceOf(RefundsPayments::class, $wipay);

        $this->assertNotInstanceOf(HandlesWebhooks::class, $office);
        $this->assertNotInstanceOf(RefundsPayments::class, $office);
    }

    public function test_a_provider_without_webhooks_has_no_webhook_endpoint(): void
    {
        $this->wipayConfigured();

        $this->postJson('/api/webhooks/wipay', ['status' => 'success'])->assertNotFound();
        $this->postJson('/api/webhooks/office', [])->assertNotFound();
        $this->postJson('/api/webhooks/nobody', [])->assertNotFound();
    }
}
