<?php

namespace App\Services\Payments\Drivers;

use App\Models\PaymentChannelSetting;
use Illuminate\Support\Str;

class BankTransferDriver implements PaymentDriverInterface
{
    public function key(): string
    {
        return 'bank_wire';
    }

    public function label(): string
    {
        return 'Direct Bank Transfer / ACH';
    }

    public function initiate(array $params): array
    {
        $amountMinor = $params['amount_minor'] ?? 0;
        $currency = $params['currency'] ?? 'JMD';
        $invNum = isset($params['invoice_id']) ? (int) $params['invoice_id'] : 4821;
        $reference = sprintf('CH-INV-%06d-%04d', $invNum, mt_rand(1000, 9999));

        // The deposit account is whatever the estate entered for this channel;
        // there is no built-in account to fall back on.
        $account = PaymentChannelSetting::accountFor('bank_wire');

        return [
            'type' => 'bank_wire',
            'account' => $account,
            'wire_reference' => $reference,
            'amount_minor' => $amountMinor,
            'currency' => $currency,
            'instructions' => $account
                ? "Transfer to {$account} and include reference {$reference} in the description."
                : 'Bank transfer is not set up for this estate yet.',
        ];
    }

    public function settle(array $params): array
    {
        return [
            'success' => true,
            'reference' => $params['wire_reference'] ?? ('WIRE-'.strtoupper(Str::random(8))),
            'notes' => 'Settled via Direct Bank Wire transfer.',
        ];
    }
}
