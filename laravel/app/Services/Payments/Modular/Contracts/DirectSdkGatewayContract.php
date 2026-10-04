<?php

declare(strict_types=1);

namespace App\Services\Payments\Modular\Contracts;

use App\Services\Payments\Modular\DTOs\CheckoutSessionRequest;
use App\Services\Payments\Modular\DTOs\CheckoutSessionResult;

/**
 * Capability contract for payment integrations that offer direct SDK
 * checkouts, charge captures, payment verifications, and refunds.
 */
interface DirectSdkGatewayContract extends PaymentModuleContract
{
    /**
     * Create a hosted or embedded checkout session.
     */
    public function createCheckoutSession(CheckoutSessionRequest $request): CheckoutSessionResult;

    /**
     * Verify the real-time status of a payment by its provider reference.
     *
     * @return array<string, mixed>
     */
    public function verifyPayment(string $paymentReference): array;

    /**
     * Execute a full or partial refund.
     *
     * @return array<string, mixed>
     */
    public function refund(string $transactionReference, float $amount, ?string $reason = null): array;
}
