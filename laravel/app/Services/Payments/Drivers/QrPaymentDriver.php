<?php

namespace App\Services\Payments\Drivers;

use App\Services\QrCodePng;
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
        $qrToken = 'CH-'.strtoupper(Str::random(12));
        $paymentUrl = url('/p/'.$qrToken);

        /*
         | A failed render leaves the image out and the payment URL still works.
         | It used to fall back to api.qrserver.com, which handed a third party
         | the payment URL.
         */
        $qrPngBase64 = null;
        try {
            $pngBinary = app(QrCodePng::class)->render($paymentUrl, 260, 2);
            $qrPngBase64 = 'data:image/png;base64,'.base64_encode($pngBinary);
        } catch (\Throwable $e) {
            report($e);
        }

        return [
            'type' => 'qr_code',
            'qr_token' => $qrToken,
            'payment_url' => $paymentUrl,
            'qr_svg_url' => $qrPngBase64,
            'amount_minor' => $amountMinor,
            'currency' => $currency,
            'expires_in_seconds' => 900,
            'instructions' => "Scan this dynamic payment QR with your camera or banking app to open the hosted checkout request ({$paymentUrl}).",
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
