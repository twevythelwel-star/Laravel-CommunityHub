<?php

declare(strict_types=1);

namespace App\Services\Payments\Modular\DTOs;

class CustomerPortalResult
{
    /**
     * @param  array<string, mixed>  $rawResponse
     */
    public function __construct(
        public readonly bool $success,
        public readonly string $portalUrl,
        public readonly ?string $sessionId = null,
        public readonly ?string $errorMessage = null,
        public readonly array $rawResponse = []
    ) {}
}
