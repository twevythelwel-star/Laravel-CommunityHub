<?php

declare(strict_types=1);

namespace App\Services\Payments\Modular\DTOs;

class CustomerPortalRequest
{
    /**
     * @param  array<string, mixed>  $configuration
     */
    public function __construct(
        public readonly string $customerId,
        public readonly string $returnUrl,
        public readonly ?string $flow = null, // e.g. 'payment_method_update', 'subscription_cancel'
        public readonly array $configuration = []
    ) {}
}
