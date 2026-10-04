<?php

declare(strict_types=1);

namespace App\Services\Payments\Modular\Contracts;

/**
 * Base contract for all optional modular payment integrations.
 *
 * Keeps payment integrations completely decoupled from core Laravel.
 */
interface PaymentModuleContract
{
    /**
     * Unique stable driver identifier (e.g. "cashier", "stripe", "paypal", "square", etc.).
     */
    public function key(): string;

    /**
     * Human-readable label for administrators and payers.
     */
    public function label(): string;

    /**
     * Check whether required environment credentials or API keys are present.
     */
    public function isConfigured(): bool;

    /**
     * Check whether the integration is currently enabled and operational.
     */
    public function isAvailable(): bool;

    /**
     * Return list of supported features:
     * ['one_time_payments', 'subscriptions', 'recurring_payments', 'trials', 'coupons', 'billing_portal', 'invoices', 'webhooks', 'refunds']
     *
     * @return array<int, string>
     */
    public function getCapabilities(): array;

    /**
     * Verify whether a specific capability is offered by this driver.
     */
    public function supports(string $capability): bool;
}
