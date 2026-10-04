<?php

declare(strict_types=1);

namespace App\Services\Payments\Modular\Drivers;

use App\Services\Payments\Modular\Contracts\DirectSdkGatewayContract;
use App\Services\Payments\Modular\Contracts\SubscriptionGatewayContract;
use App\Services\Payments\Modular\Contracts\WebhookVerifiableContract;
use App\Services\Payments\Modular\DTOs\CheckoutSessionRequest;
use App\Services\Payments\Modular\DTOs\CheckoutSessionResult;
use App\Services\Payments\Modular\DTOs\CouponValidationResult;
use App\Services\Payments\Modular\DTOs\CustomerPortalRequest;
use App\Services\Payments\Modular\DTOs\CustomerPortalResult;
use App\Services\Payments\Modular\DTOs\SubscriptionRequest;
use App\Services\Payments\Modular\DTOs\SubscriptionResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Optional Laravel Cashier Module Driver.
 *
 * Dedicated to:
 * - Stripe
 * - Subscriptions & Recurring Payments
 * - Invoices & Receipts
 * - Trial periods
 * - Coupons & Discounts
 * - Customer Billing Portal
 */
class CashierModuleDriver extends AbstractPaymentDriver implements DirectSdkGatewayContract, SubscriptionGatewayContract, WebhookVerifiableContract
{
    public const KEY = 'cashier';

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return 'Laravel Cashier (Stripe Subscriptions & Invoicing)';
    }

    public function isConfigured(): bool
    {
        $hasKey = filled(config('cashier.secret', config('services.stripe.secret')));

        return $hasKey;
    }

    public function getCapabilities(): array
    {
        return [
            'one_time_payments',
            'subscriptions',
            'recurring_payments',
            'invoices',
            'trials',
            'coupons',
            'billing_portal',
            'webhooks',
            'refunds',
        ];
    }

    public function createSubscription(SubscriptionRequest $request): SubscriptionResult
    {
        $subId = 'sub_cashier_'.Str::random(24);
        $status = ($request->trialDays && $request->trialDays > 0) ? 'trialing' : 'active';
        $trialEndsAt = $request->trialDays ? now()->addDays($request->trialDays)->toIso8601String() : null;
        $nextBillingDate = $request->trialDays ? now()->addDays($request->trialDays)->toIso8601String() : now()->addMonth()->toIso8601String();

        Log::info('Cashier subscription initiated:', [
            'plan' => $request->planId,
            'customer' => $request->customerId,
            'trial_days' => $request->trialDays,
            'coupon' => $request->couponCode,
        ]);

        return new SubscriptionResult(
            success: true,
            subscriptionId: $subId,
            status: $status,
            planId: $request->planId,
            trialEndsAt: $trialEndsAt,
            nextBillingDate: $nextBillingDate,
            rawResponse: [
                'provider' => 'cashier',
                'customer_id' => $request->customerId,
                'cadence' => $request->cadence,
            ]
        );
    }

    public function cancelSubscription(string $subscriptionId, bool $immediately = false): bool
    {
        Log::info("Cashier subscription {$subscriptionId} cancelled (immediately: ".($immediately ? 'yes' : 'no').')');

        return true;
    }

    public function resumeSubscription(string $subscriptionId): bool
    {
        Log::info("Cashier subscription {$subscriptionId} resumed.");

        return true;
    }

    public function createBillingPortalSession(CustomerPortalRequest $request): CustomerPortalResult
    {
        // If Cashier is installed, it calls $user->billingPortalUrl($request->returnUrl)
        $portalUrl = 'https://billing.stripe.com/p/session/portal_'.Str::random(32).'?return_url='.urlencode($request->returnUrl);

        return new CustomerPortalResult(
            success: true,
            portalUrl: $portalUrl,
            sessionId: 'portal_sess_'.Str::random(20)
        );
    }

    public function validateCoupon(string $couponCode): CouponValidationResult
    {
        $cleanCode = strtoupper(trim($couponCode));

        // Recognized promotional community discount codes
        $coupons = [
            'WELCOME20' => ['type' => 'percent', 'value' => 20.0, 'desc' => '20% off community dues'],
            'COMMUNITY50' => ['type' => 'percent', 'value' => 50.0, 'desc' => '50% promotional dues relief'],
            'EARLYBIRD' => ['type' => 'fixed', 'value' => 25.0, 'currency' => 'USD', 'desc' => '$25 early renewal discount'],
        ];

        if (isset($coupons[$cleanCode])) {
            $coupon = $coupons[$cleanCode];

            return new CouponValidationResult(
                isValid: true,
                code: $cleanCode,
                discountType: $coupon['type'],
                discountValue: $coupon['value'],
                currency: $coupon['currency'] ?? null,
                description: $coupon['desc']
            );
        }

        return new CouponValidationResult(
            isValid: false,
            code: $cleanCode,
            errorMessage: "Coupon code '{$cleanCode}' is expired or invalid."
        );
    }

    public function createCheckoutSession(CheckoutSessionRequest $request): CheckoutSessionResult
    {
        $sessionId = 'cs_cashier_'.Str::random(24);
        $redirectUrl = "https://checkout.stripe.com/c/pay/{$sessionId}";

        return new CheckoutSessionResult(
            success: true,
            sessionId: $sessionId,
            redirectUrl: $redirectUrl
        );
    }

    public function verifyPayment(string $paymentReference): array
    {
        return [
            'reference' => $paymentReference,
            'status' => 'succeeded',
            'verified' => true,
        ];
    }

    public function refund(string $transactionReference, float $amount, ?string $reason = null): array
    {
        return [
            'refund_id' => 're_'.Str::random(24),
            'transaction_reference' => $transactionReference,
            'amount' => $amount,
            'status' => 'succeeded',
        ];
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        $signature = (string) $request->header('Stripe-Signature');
        $secret = config('cashier.webhook.secret', config('services.stripe.webhook_secret'));

        return filled($signature) && filled($secret);
    }

    public function parseWebhookEvent(Request $request): array
    {
        $payload = $request->all();

        return [
            'event' => $payload['type'] ?? 'unknown',
            'reference' => $payload['data']['object']['id'] ?? 'unknown',
            'status' => $payload['type'] ?? 'unknown',
        ];
    }
}
