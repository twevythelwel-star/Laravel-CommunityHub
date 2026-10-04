<?php

declare(strict_types=1);

namespace App\Services\Payments\Modular\Drivers;

use App\Services\Payments\Modular\Contracts\PaymentModuleContract;

abstract class AbstractPaymentDriver implements PaymentModuleContract
{
    /**
     * @return array<int, string>
     */
    abstract public function getCapabilities(): array;

    public function supports(string $capability): bool
    {
        return in_array($capability, $this->getCapabilities(), true);
    }

    public function isAvailable(): bool
    {
        return $this->isConfigured();
    }
}
