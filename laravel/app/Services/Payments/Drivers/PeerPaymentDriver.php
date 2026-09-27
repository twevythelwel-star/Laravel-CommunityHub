<?php

namespace App\Services\Payments\Drivers;

use App\Models\PaymentChannelSetting;
use Illuminate\Support\Str;

class PeerPaymentDriver implements PaymentDriverInterface
{
    public function __construct(
        protected string $rail = 'zelle'
    ) {}

    public function key(): string
    {
        return $this->rail;
    }

    public function label(): string
    {
        return match ($this->rail) {
            'cash_app' => 'Cash App',
            default => 'Zelle',
        };
    }

    public function initiate(array $params): array
    {
        $amountMinor = $params['amount_minor'] ?? 0;
        $currency = $params['currency'] ?? 'JMD';
        $memo = 'LOT-'.($params['lot'] ?? 'CYPRESS').'-'.strtoupper(Str::random(4));

        // The recipient is whatever the estate entered for this rail; there is no
        // built-in cashtag or Zelle address to fall back on.
        $recipient = PaymentChannelSetting::accountFor($this->rail);

        $deepLink = $recipient && $this->rail === 'cash_app'
            ? 'https://cash.app/$'.rawurlencode(ltrim($recipient, '$')).'/'.($amountMinor / 100)
            : null;

        return [
            'type' => $this->rail,
            'recipient' => $recipient,
            'memo' => $memo,
            'deep_link' => $deepLink,
            'amount_minor' => $amountMinor,
            'currency' => $currency,
            'instructions' => $recipient
                ? "Send payment to {$recipient} with memo: {$memo}"
                : $this->label().' is not set up for this estate yet.',
        ];
    }

    public function settle(array $params): array
    {
        return [
            'success' => true,
            'reference' => strtoupper($this->rail).'-'.strtoupper(Str::random(8)),
            'notes' => 'Settled via '.$this->label().' transfer verification.',
        ];
    }
}
