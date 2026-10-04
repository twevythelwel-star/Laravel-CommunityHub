<?php

declare(strict_types=1);

namespace App\Services\Payments\Modular\DTOs;

class CheckoutSessionRequest
{
    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $lineItems
     */
    public function __construct(
        public readonly float $amount,
        public readonly string $currency,
        public readonly string $successUrl,
        public readonly string $cancelUrl,
        public readonly ?string $customerEmail = null,
        public readonly ?string $customerName = null,
        public readonly ?string $description = null,
        public readonly ?string $referenceId = null,
        public readonly array $lineItems = [],
        public readonly array $metadata = []
    ) {}
}
