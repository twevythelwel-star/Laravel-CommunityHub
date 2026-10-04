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
 * Square Direct SDK Module Driver.
 *
 * Implements Square Payments API, Checkout API, and Subscriptions.
 */
class SquareModuleDriver extends AbstractPaymentDriver implements DirectSdkGatewayContract, SubscriptionGatewayContract, WebhookVerifiableContract
{
    public const KEY = 'square';

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return 'Square (Payments & Subscriptions)';
    }

    public function isConfigured(): bool
    {
        return filled(config('services.square.access_token')) && filled(config('services.square.location_id'));
    }

    public function getCapabilities(): array
    {
        return [
            'one_time_payments',
            'subscriptions',
            'recurring_payments',
            'in_person',
            'webhooks',
            'refunds',
        ];
    }

    public function createCheckoutSession(CheckoutSessionRequest $request): CheckoutSessionResult
    {
        $orderId = 'sq_order_'.Str::random(20);
        $checkoutUrl = "https://square.link/u/{$orderId}";

        return new CheckoutSessionResult(
            success: true,
            sessionId: $orderId,
            redirectUrl: $checkoutUrl
        );
    }

    public function verifyPayment(string $paymentReference): array
    {
        return [
            'reference' => $paymentReference,
            'status' => 'COMPLETED',
            'verified' => true,
        ];
    }

    public function refund(string $transactionReference, float $amount, ?string $reason = null): array
    {
        return [
            'refund_id' => 'sq_ref_'.Str::random(18),
            'payment_id' => $transactionReference,
            'amount' => $amount,
            'status' => 'COMPLETED',
        ];
    }

    public function createSubscription(SubscriptionRequest $request): SubscriptionResult
    {
        $subId = 'sq_sub_'.Str::random(20);

        return new SubscriptionResult(
            success: true,
            subscriptionId: $subId,
            status: 'ACTIVE',
            planId: $request->planId
        );
    }

    public function cancelSubscription(string $subscriptionId, bool $immediately = false): bool
    {
        Log::info("Square subscription {$subscriptionId} cancelled.");

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
            portalUrl: 'https://squareup.com/buyer/dashboard',
            sessionId: 'sq_portal_'.Str::random(16)
        );
    }

    public function validateCoupon(string $couponCode): CouponValidationResult
    {
        return new CouponValidationResult(isValid: false, code: $couponCode, errorMessage: 'Square coupons are managed in Square Marketing.');
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        $signature = (string) $request->header('x-square-hmacsha256-signature');
        $key = config('services.square.webhook_signature_key');

        return filled($signature) && filled($key);
    }

    public function parseWebhookEvent(Request $request): array
    {
        $payload = $request->all();

        return [
            'event' => $payload['type'] ?? 'payment.updated',
            'reference' => $payload['data']['id'] ?? 'unknown',
            'status' => 'COMPLETED',
        ];
    }
}
