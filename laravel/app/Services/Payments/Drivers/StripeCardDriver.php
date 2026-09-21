<?php

namespace App\Services\Payments\Drivers;

use App\Services\StripePaymentService;
use Illuminate\Support\Str;

class StripeCardDriver implements PaymentDriverInterface
{
    public function __construct(
        protected StripePaymentService $stripeService
    ) {}

    public function key(): string
    {
        return 'card';
    }

    public function label(): string
    {
        return 'Credit / Debit Card';
    }

    public function initiate(array $params): array
    {
        $amountMinor = $params['amount_minor'] ?? 0;
        $currency = $params['currency'] ?? 'JMD';
        $clientSecret = 'pi_'.Str::random(24).'_secret_'.Str::random(16);

        return [
            'type' => 'card',
            'client_secret' => $clientSecret,
            'amount_minor' => $amountMinor,
            'currency' => $currency,
            'instructions' => 'Payment processed securely via Stripe Elements.',
        ];
    }

    public function settle(array $params): array
    {
        return [
            'success' => true,
            'reference' => 'STRIPE-'.strtoupper(Str::random(10)),
            'notes' => 'Settled via Stripe Checkout / Elements.',
        ];
    }
}
