<?php

declare(strict_types=1);

namespace App\Services\Payments\Modular\DTOs;

class CouponValidationResult
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly bool $isValid,
        public readonly string $code,
        public readonly ?string $discountType = null, // 'percent', 'fixed'
        public readonly ?float $discountValue = null,
        public readonly ?string $currency = null,
        public readonly ?string $description = null,
        public readonly ?string $errorMessage = null,
        public readonly array $metadata = []
    ) {}
}
