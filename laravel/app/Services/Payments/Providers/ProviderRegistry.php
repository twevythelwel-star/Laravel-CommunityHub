<?php

namespace App\Services\Payments\Providers;

use App\Services\Payments\Providers\Contracts\PaymentProvider;
use App\Services\Payments\Providers\Office\OfficeProvider;
use App\Services\Payments\Providers\Stripe\StripeProvider;
use App\Services\Payments\Providers\Wallet\WalletProvider;
use App\Services\Payments\Providers\WiPay\WiPayProvider;

/**
 * Which provider handles a channel.
 *
 *   card      the estate's configured card processor (config
 *             payments.card_provider: "stripe" or "wipay"). Left empty,
 *             Stripe is used if its keys are set; otherwise card is off.
 *   wallet    the Community Wallet.
 *   anything  else the community office (bank wire, cash, QR, NFC, digital
 *             wallets, Zelle, Cash App).
 *
 * Nothing names a processor anywhere else; adding one is a new provider class
 * and a line in providers() — no controller or state-machine change.
 */
class ProviderRegistry
{
    public function __construct(
        protected StripeProvider $stripe,
        protected WiPayProvider $wipay,
        protected OfficeProvider $office,
        protected WalletProvider $wallet,
    ) {}

    /** @return array<string, PaymentProvider> keyed by PaymentProvider::key() */
    public function providers(): array
    {
        return [
            $this->stripe->key() => $this->stripe,
            $this->wipay->key() => $this->wipay,
            $this->office->key() => $this->office,
            $this->wallet->key() => $this->wallet,
        ];
    }

    public function byKey(string $key): ?PaymentProvider
    {
        return $this->providers()[$key] ?? null;
    }

    /** The provider for a channel, or null when none is available to take it. */
    public function forChannel(string $channel): ?PaymentProvider
    {
        return match ($channel) {
            'card', 'stripe_card' => $this->cardProvider(),
            'wallet' => $this->wallet,
            default => $this->office,
        };
    }

    /**
     * The card processor for this estate. A configured one that is not set
     * up (no keys) makes card unavailable rather than falling back to
     * another processor the estate did not choose.
     */
    public function cardProvider(): ?PaymentProvider
    {
        $configured = config('payments.card_provider');

        if (filled($configured)) {
            $provider = $this->byKey((string) $configured);

            return $provider && ! in_array($provider->key(), [OfficeProvider::KEY, WalletProvider::KEY], true) && $provider->isAvailable()
                ? $provider
                : null;
        }

        return $this->stripe->isAvailable() ? $this->stripe : null;
    }
}
