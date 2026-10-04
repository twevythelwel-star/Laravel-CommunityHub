<?php

declare(strict_types=1);

namespace App\Services\Payments\Modular\DTOs;

class SubscriptionResult
{
    /**
     * @param  array<string, mixed>  $rawResponse
     */
    public function __construct(
        public readonly bool $success,
        public readonly string $subscriptionId,
        public readonly string $status, // 'active', 'trialing', 'past_due', 'canceled'
        public readonly ?string $planId = null,
        public readonly ?string $trialEndsAt = null,
        public readonly ?string $nextBillingDate = null,
        public readonly ?string $checkoutUrl = null,
        public readonly ?string $errorMessage = null,
        public readonly array $rawResponse = []
    ) {}
}
