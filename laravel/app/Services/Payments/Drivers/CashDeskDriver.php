<?php

namespace App\Services\Payments\Drivers;

use Illuminate\Support\Str;

class CashDeskDriver implements PaymentDriverInterface
{
    public function key(): string
    {
        return 'cash_office';
    }

    public function label(): string
    {
        return 'Cash at Administration Desk';
    }

    public function initiate(array $params): array
    {
        $amountMinor = $params['amount_minor'] ?? 0;
        $currency = $params['currency'] ?? 'JMD';
        $deskVoucher = 'CASH-VOUCHER-'.strtoupper(Str::random(6));

        return [
            'type' => 'cash_office',
            'desk_voucher' => $deskVoucher,
            'amount_minor' => $amountMinor,
            'currency' => $currency,
            'instructions' => 'Present this voucher code ('.$deskVoucher.') at the community office desk along with your cash payment.',
        ];
    }

    public function settle(array $params): array
    {
        return [
            'success' => true,
            'reference' => 'CASH-REC-'.strtoupper(Str::random(8)),
            'notes' => 'Settled in cash at estate administration desk. Official stamped receipt provided.',
        ];
    }
}
