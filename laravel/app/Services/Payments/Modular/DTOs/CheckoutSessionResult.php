<?php

declare(strict_types=1);

namespace App\Services\Payments\Modular\DTOs;

class CheckoutSessionResult
{
    /**
     * @param  array<string, mixed>  $rawResponse
     */
    public function __construct(
        public readonly bool $success,
        public readonly string $sessionId,
        public readonly string $redirectUrl,
        public readonly ?string $clientSecret = null,
        public readonly ?string $errorMessage = null,
        public readonly array $rawResponse = []
    ) {}
}
