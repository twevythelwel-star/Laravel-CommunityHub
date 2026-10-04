<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Payments\PaymentGatewayHub;
use App\Models\User;
use App\Services\Payments\Modular\Drivers\FlutterwaveModuleDriver;
use App\Services\Payments\Modular\Drivers\PaystackModuleDriver;
use App\Services\Payments\Modular\DTOs\CheckoutSessionRequest;
use App\Services\Payments\Modular\DTOs\CustomerPortalRequest;
use App\Services\Payments\Modular\DTOs\SubscriptionRequest;
use App\Services\Payments\Modular\PaymentGatewayManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

class ModularPaymentsArchitectureTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_gateway_manager_discovers_all_ten_drivers(): void
    {
        $manager = app(PaymentGatewayManager::class);

        $expectedKeys = [
            'cashier',
            'stripe',
            'paypal',
            'square',
            'adyen',
            'braintree',
            'flutterwave',
            'paystack',
            'mollie',
            'authorizenet',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertTrue($manager->hasDriver($key), "Driver [{$key}] was not registered in PaymentGatewayManager.");
            $driver = $manager->driver($key);
            $this->assertSame($key, $driver->key());
            $this->assertNotEmpty($driver->getCapabilities());
        }

        $catalog = $manager->catalog();
        $this->assertCount(10, $catalog);

        $subDrivers = $manager->getSubscriptionDrivers();
        $this->assertArrayHasKey('cashier', $subDrivers);
        $this->assertArrayHasKey('paypal', $subDrivers);
        $this->assertArrayHasKey('square', $subDrivers);
    }

    public function test_cashier_subscription_driver_handles_recurring_trials_and_coupons(): void
    {
        $manager = app(PaymentGatewayManager::class);
        $cashier = $manager->subscriptionDriver('cashier');

        // 1. Subscription with 14-day trial
        $subRequest = new SubscriptionRequest(
            planId: 'plan_homeowner_standard',
            customerId: 'cus_test_123',
            cadence: 'monthly',
            trialDays: 14,
            couponCode: 'WELCOME20'
        );

        $subResult = $cashier->createSubscription($subRequest);
        $this->assertTrue($subResult->success);
        $this->assertSame('trialing', $subResult->status);
        $this->assertNotNull($subResult->trialEndsAt);
        $this->assertStringStartsWith('sub_cashier_', $subResult->subscriptionId);

        // 2. Billing Portal Session Generation
        $portalReq = new CustomerPortalRequest(
            customerId: 'cus_test_123',
            returnUrl: 'https://communityhub.io/dashboard/billing'
        );
        $portalResult = $cashier->createBillingPortalSession($portalReq);
        $this->assertTrue($portalResult->success);
        $this->assertStringContainsString('billing.stripe.com', $portalResult->portalUrl);

        // 3. Coupon Validation
        $validCoupon = $cashier->validateCoupon('WELCOME20');
        $this->assertTrue($validCoupon->isValid);
        $this->assertSame(20.0, $validCoupon->discountValue);
        $this->assertSame('percent', $validCoupon->discountType);

        $invalidCoupon = $cashier->validateCoupon('NONEXISTENT_CODE');
        $this->assertFalse($invalidCoupon->isValid);
        $this->assertNotNull($invalidCoupon->errorMessage);
    }

    public function test_direct_sdk_drivers_generate_checkout_sessions(): void
    {
        $manager = app(PaymentGatewayManager::class);

        $driversToTest = ['paypal', 'square', 'flutterwave', 'paystack', 'mollie', 'authorizenet'];

        foreach ($driversToTest as $driverKey) {
            $driver = $manager->directSdkDriver($driverKey);

            $req = new CheckoutSessionRequest(
                amount: 75.00,
                currency: 'USD',
                successUrl: 'https://communityhub.io/success',
                cancelUrl: 'https://communityhub.io/cancel',
                customerEmail: 'test@communityhub.io',
                description: 'Special Assessment Fee'
            );

            $result = $driver->createCheckoutSession($req);
            $this->assertTrue($result->success, "Checkout failed for driver [{$driverKey}]");
            $this->assertNotEmpty($result->sessionId);
            $this->assertNotEmpty($result->redirectUrl);
        }
    }

    public function test_webhook_verification_across_modular_drivers(): void
    {
        // 1. Flutterwave secret hash verification
        config(['services.flutterwave.secret_hash' => 'flw_secret_test_hash_456']);
        $flwDriver = app(FlutterwaveModuleDriver::class);

        $validFlwReq = Request::create('/api/webhooks/flutterwave', 'POST', [], [], [], [
            'HTTP_VERIF_HASH' => 'flw_secret_test_hash_456',
        ]);
        $this->assertTrue($flwDriver->verifyWebhookSignature($validFlwReq));

        $invalidFlwReq = Request::create('/api/webhooks/flutterwave', 'POST', [], [], [], [
            'HTTP_VERIF_HASH' => 'wrong_hash',
        ]);
        $this->assertFalse($flwDriver->verifyWebhookSignature($invalidFlwReq));

        // 2. Paystack HMAC-SHA512 verification
        config(['services.paystack.secret_key' => 'sk_test_paystack_secret']);
        $pstkDriver = app(PaystackModuleDriver::class);

        $content = json_encode(['event' => 'charge.success', 'data' => ['reference' => 'ref_123']]);
        $validPstkSig = hash_hmac('sha512', (string) $content, 'sk_test_paystack_secret');

        $validPstkReq = Request::create(
            '/api/webhooks/paystack',
            'POST',
            [],
            [],
            [],
            ['HTTP_X_PAYSTACK_SIGNATURE' => $validPstkSig],
            $content
        );
        $this->assertTrue($pstkDriver->verifyWebhookSignature($validPstkReq));
    }

    public function test_modular_payments_api_endpoints_return_standardized_json(): void
    {
        // 1. Public catalog endpoint
        $catalogRes = $this->getJson('/api/v1/payments/modules');
        $catalogRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(10, 'data.modules');

        // 2. Authenticated user actions
        $user = User::factory()->create(['role' => UserRole::Homeowner]);
        Sanctum::actingAs($user, ['*']);

        // Coupon validation
        $couponRes = $this->postJson('/api/v1/payments/coupons/validate', [
            'coupon_code' => 'COMMUNITY50',
            'driver' => 'cashier',
        ]);
        $couponRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.code', 'COMMUNITY50')
            ->assertJsonPath('data.discount_value', 50);

        // Checkout session creation
        $checkoutRes = $this->postJson('/api/v1/payments/checkout-session', [
            'driver' => 'paypal',
            'amount' => 120.00,
            'currency' => 'USD',
            'success_url' => 'https://example.com/success',
            'cancel_url' => 'https://example.com/cancel',
        ]);
        $checkoutRes->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.driver', 'paypal');
        $this->assertNotNull($checkoutRes->json('data.session_id'));

        // Subscription creation
        $subRes = $this->postJson('/api/v1/payments/subscriptions', [
            'driver' => 'cashier',
            'plan_id' => 'plan_resident_annual',
            'cadence' => 'yearly',
            'trial_days' => 30,
        ]);
        $subRes->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'trialing');

        // Billing portal session
        $portalRes = $this->postJson('/api/v1/payments/billing-portal', [
            'driver' => 'cashier',
            'return_url' => 'https://example.com/dashboard/billing',
        ]);
        $portalRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.driver', 'cashier');
    }

    public function test_livewire_payment_gateway_hub_renders_and_executes_actions(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        Livewire::actingAs($admin)
            ->test(PaymentGatewayHub::class)
            ->assertSee('Gateway Catalog')
            ->call('selectTab', 'checkout')
            ->set('selectedDriver', 'paypal')
            ->call('testCheckoutSession')
            ->assertSee('Session Initialized')
            ->call('selectTab', 'subscriptions')
            ->set('testCoupon', 'WELCOME20')
            ->call('testSubscription')
            ->assertSee('Subscription Active')
            ->call('selectTab', 'coupons')
            ->set('couponInput', 'WELCOME20')
            ->call('testCouponValidation')
            ->assertSee('Valid Coupon: WELCOME20');
    }
}
