<?php

namespace App\Services\Payments\Drivers;

use Illuminate\Support\Str;

class QrPaymentDriver implements PaymentDriverInterface
{
    public function key(): string
    {
        return 'qr_code';
    }

    public function label(): string
    {
        return 'QR Code Scan to Pay';
    }

    public function initiate(array $params): array
    {
        $amountMinor = $params['amount_minor'] ?? 0;
        $currency = $params['currency'] ?? 'JMD';
        $qrToken = 'qr_'.Str::random(24);

        $payload = json_encode([
            'token' => $qrToken,
            'merchant' => 'Cypress Bay Community',
            'amount' => $amountMinor / 100,
            'currency' => $currency,
            'expires_at' => now()->addMinutes(15)->toIso8601String(),
        ]);

        return [
            'type' => 'qr_code',
            'qr_token' => $qrToken,
            'qr_payload' => $payload,
            'qr_svg_url' => 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data='.urlencode(url('/pay/qr/'.$qrToken)),
            'amount_minor' => $amountMinor,
            'currency' => $currency,
            'expires_in_seconds' => 900,
            'instructions' => 'Scan this dynamic QR code using your banking or digital wallet app.',
        ];
    }

    public function settle(array $params): array
    {
        return [
            'success' => true,
            'reference' => 'QR-'.strtoupper(Str::random(10)),
            'notes' => 'Settled via dynamic QR Code scanner.',
        ];
    }
}
