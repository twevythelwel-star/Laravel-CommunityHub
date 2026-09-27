<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Fundraiser;
use App\Models\PaymentChannelSetting;
use App\Models\PaymentTerminal;
use App\Models\User;
use App\Services\Payments\PaymentOrchestratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Billing and Fundraising must offer the same payment methods.
 *
 * Both read `PaymentOrchestratorService::getAvailableChannels()`, which is
 * backed by `payment_channel_settings` — so an administrator enabling,
 * disabling, renaming or surcharging a channel affects both pages at once.
 */
class PaymentChannelsTest extends TestCase
{
    use RefreshDatabase;

    private function resident(): User
    {
        return User::factory()->role(UserRole::Homeowner)->create();
    }

    private function fundraiser(): Fundraiser
    {
        return Fundraiser::create([
            'title' => 'New Playground Equipment',
            'description' => 'A modern playground for the community children.',
            'goal_minor' => 150000000,
            'goal_currency' => 'JMD',
            'start_date' => now()->subWeek(),
            'end_date' => now()->addMonth(),
            'status' => 'Active',
        ]);
    }

    /** @return array<int, string> */
    private function channelKeysFrom(array $channels): array
    {
        return collect($channels)->pluck('key')->sort()->values()->all();
    }

    public function test_both_pages_offer_the_same_channels(): void
    {
        $this->fundraiser();
        $resident = $this->resident();

        $billing = $this->actingAs($resident)->get('/dashboard/billing');
        $fundraising = $this->actingAs($resident)->get('/dashboard/fundraising');

        $billingChannels = $this->channelKeysFrom(
            $billing->viewData('page')['props']['paymentCenter']['availableChannels'] ?? []
        );

        $fundraisingChannels = $this->channelKeysFrom(
            $fundraising->viewData('page')['props']['availableChannels'] ?? []
        );

        $this->assertNotEmpty($fundraisingChannels, 'fundraising must receive the channel list');
        $this->assertSame($billingChannels, $fundraisingChannels);
    }

    public function test_fundraising_receives_the_channel_list(): void
    {
        $this->fundraiser();

        $this->actingAs($this->resident())
            ->get('/dashboard/fundraising')
            ->assertInertia(fn (Assert $page) => $page
                ->has('availableChannels')
                ->has('availableChannels.0.key')
                ->has('availableChannels.0.label')
                ->has('availableChannels.0.fee_surcharge_percent')
            );
    }

    public function test_disabling_a_channel_removes_it_from_both_pages(): void
    {
        PaymentChannelSetting::updateOrCreate(
            ['channel_key' => 'zelle'],
            ['enabled' => false, 'display_label' => 'Zelle']
        );

        $this->fundraiser();
        $resident = $this->resident();

        $fundraising = $this->actingAs($resident)->get('/dashboard/fundraising');
        $billing = $this->actingAs($resident)->get('/dashboard/billing');

        $this->assertNotContains(
            'zelle',
            $this->channelKeysFrom($fundraising->viewData('page')['props']['availableChannels'] ?? [])
        );

        $this->assertNotContains(
            'zelle',
            $this->channelKeysFrom(
                $billing->viewData('page')['props']['paymentCenter']['availableChannels'] ?? []
            )
        );
    }

    public function test_a_channel_surcharge_reaches_the_donor(): void
    {
        /*
         | `fee_surcharge_percent` has always been on the settings table and was
         | passed to the payment UI without ever being displayed, under a badge
         | reading "Zero Surcharges". It has to reach the page before it can be
         | shown to the person paying it.
         */
        // Card is offered only with a card processor configured.
        config(['services.stripe.secret' => 'sk_test_surcharge']);

        PaymentChannelSetting::updateOrCreate(
            ['channel_key' => 'card'],
            ['enabled' => true, 'display_label' => 'Debit / Credit Card', 'fee_surcharge_percent' => 2.5]
        );

        $this->fundraiser();

        $this->actingAs($this->resident())
            ->get('/dashboard/fundraising')
            ->assertInertia(function (Assert $page) {
                $channels = collect($page->toArray()['props']['availableChannels']);
                $card = $channels->firstWhere('key', 'card');

                $this->assertNotNull($card);
                $this->assertSame(2.5, (float) $card['fee_surcharge_percent']);
            });
    }

    public function test_a_donation_records_the_channel_it_was_given_through(): void
    {
        $fundraiser = $this->fundraiser();

        $this->actingAs($this->resident())
            ->post("/dashboard/fundraising/{$fundraiser->id}/donate", [
                'amount' => 2500,
                'donor_name' => 'Marcus V.',
                'is_anonymous' => false,
                'channel' => 'cash_office',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('cash_office', $fundraiser->donations()->firstOrFail()->payment_channel);
    }

    public function test_an_unknown_channel_is_rejected_rather_than_throwing(): void
    {
        /*
         | `channel` was `['nullable', 'string']`, so an unrecognised key reached
         | PaymentOrchestratorService::getDriver(), which throws
         | InvalidArgumentException — a 500 from an ordinary form post.
         */
        $fundraiser = $this->fundraiser();

        $this->actingAs($this->resident())
            ->post("/dashboard/fundraising/{$fundraiser->id}/donate", [
                'amount' => 100,
                'donor_name' => 'Marcus V.',
                'channel' => 'definitely-not-a-driver',
            ])
            ->assertSessionHasErrors('channel');

        $this->assertDatabaseCount('donations', 0);
    }

    public function test_every_offered_channel_is_one_the_orchestrator_can_settle(): void
    {
        // Otherwise the page offers a button whose POST would 500.
        $this->fundraiser();

        $orchestrator = app(PaymentOrchestratorService::class);
        $known = $orchestrator->channelKeys();

        $response = $this->actingAs($this->resident())->get('/dashboard/fundraising');
        $offered = $this->channelKeysFrom($response->viewData('page')['props']['availableChannels']);

        foreach ($offered as $key) {
            $this->assertContains($key, $known, "offered channel [{$key}] has no driver");
        }
    }

    private function admin(): User
    {
        return User::factory()->role(UserRole::Admin)->create();
    }

    /** @return array<int, string> */
    private function billingChannelKeys(User $user): array
    {
        $page = $this->actingAs($user)->get('/dashboard/billing')->viewData('page');

        return $this->channelKeysFrom($page['props']['paymentCenter']['availableChannels'] ?? []);
    }

    public function test_default_channels_name_no_account_and_leave_account_channels_off(): void
    {
        foreach (PaymentChannelSetting::defaultChannels() as $channel) {
            $this->assertEmpty($channel['account_identifier'] ?? null, "{$channel['channel_key']} ships with an account");

            if (PaymentChannelSetting::requiresAccountDetails($channel['channel_key'])) {
                $this->assertFalse($channel['enabled'], "{$channel['channel_key']} is enabled with no account");
            }
        }
    }

    public function test_an_account_channel_without_an_account_is_never_offered(): void
    {
        // Enabled but with no account, and with no settings row at all.
        PaymentChannelSetting::updateOrCreate(['channel_key' => 'bank_wire'], ['enabled' => true, 'display_label' => 'Bank', 'account_identifier' => null]);

        $keys = $this->billingChannelKeys($this->resident());

        $this->assertNotContains('bank_wire', $keys);
        $this->assertNotContains('zelle', $keys);
        $this->assertNotContains('cash_app', $keys);
    }

    public function test_paying_through_an_account_channel_with_no_account_is_refused(): void
    {
        $this->actingAs($this->resident())
            ->post('/dashboard/billing/pay', ['amount' => 100, 'payment_channel' => 'bank_wire', 'settlement_mode' => 'full'])
            ->assertSessionHasErrors('channel');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_an_administrator_sets_the_account_payers_are_sent_to(): void
    {
        $this->actingAs($this->admin())
            ->patch(route('dashboard.billing.channels.account', 'zelle'), [
                'account_identifier' => '  dues@estate.example  ',
                'instructions' => 'Put your transaction ID in the memo.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('dues@estate.example', PaymentChannelSetting::accountFor('zelle'));

        $report = app(PaymentOrchestratorService::class)->validateChannelIntegration('zelle');
        $this->assertTrue($report['is_ready']);
        $this->assertStringContainsString('dues@estate.example', $report['checks'][0]['message']);

        PaymentChannelSetting::where('channel_key', 'zelle')->update(['enabled' => true]);
        $channel = collect($this->actingAs($this->resident())->get('/dashboard/billing')
            ->viewData('page')['props']['paymentCenter']['availableChannels'])->firstWhere('key', 'zelle');

        $this->assertSame('dues@estate.example', $channel['account_identifier']);
    }

    public function test_an_account_channel_cannot_be_enabled_until_it_has_an_account(): void
    {
        $admin = $this->admin();

        $report = app(PaymentOrchestratorService::class)->validateChannelIntegration('bank_wire');
        $this->assertFalse($report['is_ready']);
        $this->assertFalse($report['checks'][0]['passed']);

        $this->actingAs($admin)
            ->post(route('dashboard.billing.channels.toggle', 'bank_wire'))
            ->assertSessionHasErrors('channel');
    }

    public function test_clearing_the_account_disables_the_channel(): void
    {
        $this->configureAccountChannels();

        $this->actingAs($this->admin())
            ->patch(route('dashboard.billing.channels.account', 'cash_app'), ['account_identifier' => ''])
            ->assertSessionHasNoErrors();

        $setting = PaymentChannelSetting::where('channel_key', 'cash_app')->first();
        $this->assertNull($setting->account_identifier);
        $this->assertFalse($setting->enabled);
    }

    public function test_only_account_channels_take_an_account_and_only_administrators_set_one(): void
    {
        $this->actingAs($this->admin())
            ->patch(route('dashboard.billing.channels.account', 'card'), ['account_identifier' => 'x'])
            ->assertNotFound();

        $this->actingAs($this->resident())
            ->patch(route('dashboard.billing.channels.account', 'zelle'), ['account_identifier' => 'attacker@example.test'])
            ->assertForbidden();

        $this->assertNull(PaymentChannelSetting::accountFor('zelle'));
    }

    /** @return array<string, mixed> */
    private function readiness(string $channel): array
    {
        return app(PaymentOrchestratorService::class)->validateChannelIntegration($channel);
    }

    private function withoutAnyProcessor(): void
    {
        config([
            'services.stripe.secret' => null,
            'services.stripe.webhook_secret' => null,
            'payments.card_provider' => null,
            'payments.in_person_provider' => null,
            'payments.public_links_enabled' => false,
        ]);
    }

    public function test_nothing_is_ready_when_nothing_is_configured(): void
    {
        $this->withoutAnyProcessor();

        foreach (['card', 'apple_pay', 'google_pay', 'samsung_wallet', 'nfc_pos', 'qr_code', 'bank_wire', 'zelle', 'cash_app'] as $channel) {
            $report = $this->readiness($channel);

            $this->assertFalse($report['is_ready'], "{$channel} reports ready with nothing configured");
            $this->assertContains(false, array_column($report['checks'], 'passed'), "{$channel} shows no failing check");
        }
    }

    public function test_no_check_reports_details_the_application_never_verified(): void
    {
        $fabricated = ['SPM-99482', 'BCR2DN4TX76YQ', 'merchant.org.cypressbay', '#GH-01', '#CH-01', 'J$150,000', 'thermal receipt', 'JNCBJMKN', 'TLS 1.3'];

        foreach (app(PaymentOrchestratorService::class)->getChannelReadinessReport() as $report) {
            $channel = $report['channel_key'];
            $text = json_encode($report);

            foreach ($fabricated as $claim) {
                $this->assertStringNotContainsString($claim, $text, "{$channel} still claims {$claim}");
            }
        }
    }

    public function test_an_unconfigured_channel_cannot_be_enabled(): void
    {
        $this->withoutAnyProcessor();
        $admin = $this->admin();

        foreach (['card', 'apple_pay', 'samsung_wallet', 'nfc_pos', 'qr_code'] as $channel) {
            PaymentChannelSetting::forChannel($channel)->fill(['enabled' => false])->save();

            $this->actingAs($admin)
                ->post(route('dashboard.billing.channels.toggle', $channel))
                ->assertSessionHasErrors('channel');

            $this->assertFalse(PaymentChannelSetting::forChannel($channel)->enabled, "{$channel} was enabled");
        }
    }

    public function test_card_and_the_wallets_stripe_offers_are_ready_once_stripe_is_configured(): void
    {
        $this->withoutAnyProcessor();
        config([
            'services.stripe.secret' => 'sk_test_ready',
            'services.stripe.webhook_secret' => 'whsec_ready',
            'payments.wallets' => ['apple_pay', 'google_pay', 'samsung_wallet'],
        ]);

        $this->assertTrue($this->readiness('card')['is_ready']);
        $this->assertTrue($this->readiness('apple_pay')['is_ready']);
        $this->assertTrue($this->readiness('google_pay')['is_ready']);

        // Stripe does not take Samsung Wallet, whatever PAYMENT_WALLETS lists.
        $samsung = $this->readiness('samsung_wallet');
        $this->assertFalse($samsung['is_ready']);
        $this->assertStringContainsString('does not offer', $samsung['checks'][0]['message']);
    }

    public function test_card_is_not_ready_without_a_usable_stripe_webhook_secret(): void
    {
        $this->withoutAnyProcessor();
        config(['services.stripe.secret' => 'sk_test_ready', 'services.stripe.webhook_secret' => 'not-a-whsec']);

        $this->assertFalse($this->readiness('card')['is_ready']);
    }

    public function test_a_wallet_not_listed_by_the_estate_is_not_ready(): void
    {
        $this->withoutAnyProcessor();
        config(['services.stripe.secret' => 'sk_test_ready', 'services.stripe.webhook_secret' => 'whsec_ready', 'payments.wallets' => ['google_pay']]);

        $report = $this->readiness('apple_pay');
        $this->assertFalse($report['is_ready']);
        $this->assertStringContainsString('PAYMENT_WALLETS', $report['checks'][0]['message']);
    }

    public function test_in_person_payments_need_a_provider_and_a_verified_reader(): void
    {
        $this->withoutAnyProcessor();
        config(['services.stripe.secret' => 'sk_test_ready', 'payments.in_person_provider' => 'stripe_terminal']);

        $this->assertFalse($this->readiness('nfc_pos')['is_ready'], 'ready with no reader');

        PaymentTerminal::factory()->unverified()->create();
        $this->assertFalse($this->readiness('nfc_pos')['is_ready'], 'ready with only an unverified reader');

        PaymentTerminal::factory()->create();
        $this->assertTrue($this->readiness('nfc_pos')['is_ready']);
    }

    public function test_qr_payments_are_ready_only_when_public_links_are_on(): void
    {
        $this->withoutAnyProcessor();
        $this->assertFalse($this->readiness('qr_code')['is_ready']);

        config(['payments.public_links_enabled' => true]);
        $this->assertTrue($this->readiness('qr_code')['is_ready']);
    }

    public function test_validating_an_unknown_channel_reports_it_rather_than_failing(): void
    {
        $this->actingAs($this->admin())
            ->postJson(route('dashboard.billing.channels.validate', 'no_such_channel'))
            ->assertOk()
            ->assertJsonPath('is_ready', false);
    }

    public function test_cash_at_the_office_is_ready_because_the_office_confirms_it(): void
    {
        $report = $this->readiness('cash_office');

        $this->assertTrue($report['is_ready']);
        $this->assertSame('Office Confirmation', $report['checks'][0]['name']);
    }
}
