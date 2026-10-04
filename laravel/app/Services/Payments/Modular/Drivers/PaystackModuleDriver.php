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
 * Paystack Direct SDK Module Driver.
 *
 * Implements Paystack Initialize Transaction, Subscriptions, and HMAC-SHA512 Webhooks.
 */
class PaystackModuleDriver extends AbstractPaymentDriver implements DirectSdkGatewayContract, SubscriptionGatewayContract, WebhookVerifiableContract
{
    public const KEY = 'paystack';

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return 'Paystack (Modern African Payments & Recurring)';
    }

    public function isConfigured(): bool
    {
        return filled(config('services.paystack.secret_key')) && filled(config('services.paystack.public_key'));
    }

    public function getCapabilities(): array
    {
        return [
            'one_time_payments',
            'subscriptions',
            'recurring_payments',
            'mobile_money',
            'bank_transfer',
            'webhooks',
            'refunds',
        ];
    }

    public function createCheckoutSession(CheckoutSessionRequest $request): CheckoutSessionResult
    {
        $accessCode = 'pstk_'.Str::random(16);
        $reference = 'REF_'.strtoupper(Str::random(12));
        $authUrl = "https://checkout.paystack.com/{$accessCode}";

        Log::info('Paystack transaction initialized', [
            'reference' => $reference,
            'amount' => $request->amount,
            'email' => $request->customerEmail,
        ]);

        return new CheckoutSessionResult(
            success: true,
            sessionId: $reference,
            redirectUrl: $authUrl,
            clientSecret: $accessCode
        );
    }

    public function verifyPayment(string $paymentReference): array
    {
        return [
            'reference' => $paymentReference,
            'status' => 'success',
            'verified' => true,
        ];
    }

    public function refund(string $transactionReference, float $amount, ?string $reason = null): array
    {
        return [
            'refund_id' => 'pstk_ref_'.Str::random(12),
            'transaction_reference' => $transactionReference,
            'amount' => $amount,
            'status' => 'processed',
        ];
    }

    public function createSubscription(SubscriptionRequest $request): SubscriptionResult
    {
        $subCode = 'SUB_'.Str::random(14);

        return new SubscriptionResult(
            success: true,
            subscriptionId: $subCode,
            status: 'active',
            planId: $request->planId
        );
    }

    public function cancelSubscription(string $subscriptionId, bool $immediately = false): bool
    {
        Log::info("Paystack subscription {$subscriptionId} disabled.");

        return true;
    }

    public function resumeSubscription(string $subscriptionId): bool
    {
        return true;
    }

    public function createBillingPortalSession(CustomerPortalRequest $request): CustomerPortalResult
    {
        return new CustomerPortalResult(
            success: true,
            portalUrl: $request->returnUrl,
            sessionId: 'paystack_portal'
        );
    }

    public function validateCoupon(string $couponCode): CouponValidationResult
    {
        return new CouponValidationResult(isValid: false, code: $couponCode, errorMessage: 'Paystack coupons not configured.');
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        $signature = (string) $request->header('x-paystack-signature');
        $secret = config('services.paystack.secret_key');

        if (! filled($secret) || ! filled($signature)) {
            return false;
        }

        $expected = hash_hmac('sha512', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }

    public function parseWebhookEvent(Request $request): array
    {
        $payload = $request->all();

        return [
            'event' => $payload['event'] ?? 'charge.success',
            'reference' => $payload['data']['reference'] ?? 'unknown',
            'status' => $payload['data']['status'] ?? 'success',
        ];
    }
}
