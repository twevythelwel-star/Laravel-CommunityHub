<?php

declare(strict_types=1);

namespace App\Livewire\Payments;

use App\Services\Payments\Modular\DTOs\CheckoutSessionRequest;
use App\Services\Payments\Modular\DTOs\SubscriptionRequest;
use App\Services\Payments\Modular\PaymentGatewayManager;
use Livewire\Component;

class PaymentGatewayHub extends Component
{
    public string $activeTab = 'catalog';

    public string $selectedDriver = 'cashier';

    // Checkout Test State
    public float $testAmount = 45.00;

    public string $testCurrency = 'USD';

    public string $testEmail = 'resident@communityhub.io';

    public ?string $checkoutSessionResult = null;

    // Subscription Test State
    public string $testPlanId = 'plan_dues_standard';

    public string $testCadence = 'monthly';

    public int $testTrialDays = 14;

    public string $testCoupon = '';

    public ?string $subscriptionResult = null;

    // Coupon Validation State
    public string $couponInput = 'WELCOME20';

    public ?array $couponResult = null;

    public ?string $couponError = null;

    public function selectTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function selectDriver(string $driver): void
    {
        $this->selectedDriver = $driver;
    }

    public function testCheckoutSession(?PaymentGatewayManager $manager = null): void
    {
        $manager = $manager ?: app(PaymentGatewayManager::class);

        try {
            $driver = $manager->directSdkDriver($this->selectedDriver);
            $req = new CheckoutSessionRequest(
                amount: $this->testAmount,
                currency: $this->testCurrency,
                successUrl: url('/dashboard/payments/success'),
                cancelUrl: url('/dashboard/payments/cancel'),
                customerEmail: $this->testEmail,
                description: 'Monthly Maintenance Assessment'
            );

            $res = $driver->createCheckoutSession($req);
            $this->checkoutSessionResult = "Session Initialized! Session ID: {$res->sessionId} | Redirect: {$res->redirectUrl}";
        } catch (\Throwable $e) {
            $this->checkoutSessionResult = "Error: {$e->getMessage()}";
        }
    }

    public function testSubscription(?PaymentGatewayManager $manager = null): void
    {
        $manager = $manager ?: app(PaymentGatewayManager::class);

        try {
            $driver = $manager->subscriptionDriver($this->selectedDriver);
            $req = new SubscriptionRequest(
                planId: $this->testPlanId,
                customerId: 'cust_livewire_'.(auth()->id() ?? '1'),
                cadence: $this->testCadence,
                trialDays: $this->testTrialDays > 0 ? $this->testTrialDays : null,
                couponCode: filled($this->testCoupon) ? $this->testCoupon : null
            );

            $res = $driver->createSubscription($req);
            $this->subscriptionResult = "Subscription Active! ID: {$res->subscriptionId} (Status: {$res->status})".
                ($res->trialEndsAt ? " | Trial Ends: {$res->trialEndsAt}" : '');
        } catch (\Throwable $e) {
            $this->subscriptionResult = "Error: {$e->getMessage()}";
        }
    }

    public function testCouponValidation(?PaymentGatewayManager $manager = null): void
    {
        $manager = $manager ?: app(PaymentGatewayManager::class);
        $this->couponResult = null;
        $this->couponError = null;

        try {
            $driver = $manager->subscriptionDriver('cashier');
            $res = $driver->validateCoupon($this->couponInput);

            if ($res->isValid) {
                $this->couponResult = [
                    'code' => $res->code,
                    'type' => $res->discountType,
                    'value' => $res->discountValue,
                    'desc' => $res->description,
                ];
            } else {
                $this->couponError = $res->errorMessage ?? 'Invalid coupon.';
            }
        } catch (\Throwable $e) {
            $this->couponError = $e->getMessage();
        }
    }

    public function render()
    {
        $manager = app(PaymentGatewayManager::class);

        return view('livewire.payments.payment-gateway-hub', [
            'catalog' => $manager->catalog(),
            'defaultGateway' => $manager->getDefaultDriver(),
        ])->layout('layouts.livewire', ['title' => 'Modular Payments & Subscriptions Gateway']);
    }
}
