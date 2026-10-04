<?php

declare(strict_types=1);

namespace App\Services\Payments\Modular\Contracts;

use App\Services\Payments\Modular\DTOs\CouponValidationResult;
use App\Services\Payments\Modular\DTOs\CustomerPortalRequest;
use App\Services\Payments\Modular\DTOs\CustomerPortalResult;
use App\Services\Payments\Modular\DTOs\SubscriptionRequest;
use App\Services\Payments\Modular\DTOs\SubscriptionResult;

/**
 * Capability contract for payment integrations that support
 * recurring subscriptions, trial periods, discount coupons,
 * and customer self-service billing portals.
 */
interface SubscriptionGatewayContract extends PaymentModuleContract
{
    /**
     * Create or schedule a recurring subscription.
     */
    public function createSubscription(SubscriptionRequest $request): SubscriptionResult;

    /**
     * Cancel an active recurring subscription.
     */
    public function cancelSubscription(string $subscriptionId, bool $immediately = false): bool;

    /**
     * Resume a cancelled subscription that is still on grace period.
     */
    public function resumeSubscription(string $subscriptionId): bool;

    /**
     * Generate a customer billing portal URL for managing cards, invoices, and plans.
     */
    public function createBillingPortalSession(CustomerPortalRequest $request): CustomerPortalResult;

    /**
     * Validate a promo / coupon discount code.
     */
    public function validateCoupon(string $couponCode): CouponValidationResult;
}
