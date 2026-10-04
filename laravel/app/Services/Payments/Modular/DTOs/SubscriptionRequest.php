<?php

declare(strict_types=1);

namespace App\Services\Payments\Modular\DTOs;

class SubscriptionRequest
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $planId,
        public readonly string $customerId,
        public readonly string $cadence = 'monthly', // 'monthly', 'yearly', 'weekly'
        public readonly ?int $trialDays = null,
        public readonly ?string $couponCode = null,
        public readonly ?string $paymentMethodId = null,
        public readonly ?string $returnUrl = null,
        public readonly array $metadata = []
    ) {}
}
