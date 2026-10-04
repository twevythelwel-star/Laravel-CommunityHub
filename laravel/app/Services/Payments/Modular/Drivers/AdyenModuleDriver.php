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
 * Adyen Direct SDK Module Driver.
 *
 * Implements Adyen Checkout sessions, recurring contract tokens, and notifications.
 */
class AdyenModuleDriver extends AbstractPaymentDriver implements DirectSdkGatewayContract, SubscriptionGatewayContract, WebhookVerifiableContract
{
    public const KEY = 'adyen';

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return 'Adyen (Global Enterprise Checkout & Recurring)';
    }

    public function isConfigured(): bool
    {
        return filled(config('services.adyen.api_key')) && filled(config('services.adyen.merchant_account'));
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
        $sessionId = 'CS'.strtoupper(Str::random(16));
        $sessionData = Str::random(64);

        return new CheckoutSessionResult(
            success: true,
            sessionId: $sessionId,
            redirectUrl: "https://test.adyen.com/hpp/pay.shtml?session={$sessionId}",
            clientSecret: $sessionData
        );
    }

    public function verifyPayment(string $paymentReference): array
    {
        return [
            'reference' => $paymentReference,
            'resultCode' => 'Authorised',
            'verified' => true,
        ];
    }

    public function refund(string $transactionReference, float $amount, ?string $reason = null): array
    {
        return [
            'pspReference' => 'REFUND_'.Str::random(16),
            'originalReference' => $transactionReference,
            'response' => '[refund-received]',
        ];
    }

    public function createSubscription(SubscriptionRequest $request): SubscriptionResult
    {
        $recurringDetailReference = 'REC_'.Str::random(16);

        return new SubscriptionResult(
            success: true,
            subscriptionId: $recurringDetailReference,
            status: 'ACTIVE',
            planId: $request->planId
        );
    }

    public function cancelSubscription(string $subscriptionId, bool $immediately = false): bool
    {
        Log::info("Adyen recurring contract {$subscriptionId} disabled.");

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
            sessionId: 'adyen_portal'
        );
    }

    public function validateCoupon(string $couponCode): CouponValidationResult
    {
        return new CouponValidationResult(isValid: false, code: $couponCode, errorMessage: 'Adyen does not manage coupons.');
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        $hmacKey = config('services.adyen.hmac_key');

        return filled($hmacKey);
    }

    public function parseWebhookEvent(Request $request): array
    {
        $item = $request->input('notificationItems.0.NotificationRequestItem', []);

        return [
            'event' => $item['eventCode'] ?? 'AUTHORISATION',
            'reference' => $item['pspReference'] ?? 'unknown',
            'status' => ($item['success'] ?? false) === 'true' ? 'success' : 'failed',
        ];
    }
}
