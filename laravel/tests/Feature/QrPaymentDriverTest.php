<?php

namespace Tests\Feature;

use App\Services\Payments\Drivers\QrPaymentDriver;
use App\Services\QrCodePng;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

/**
 * The scan-to-pay QR is rendered on the server. When rendering failed, the
 * driver used to fall back to api.qrserver.com, which received the payment URL.
 */
class QrPaymentDriverTest extends TestCase
{
    public function test_the_qr_is_rendered_locally_from_the_payment_url(): void
    {
        $intent = (new QrPaymentDriver)->initiate(['amount_minor' => 5000, 'currency' => 'JMD']);

        $this->assertStringStartsWith(url('/p/CH-'), $intent['payment_url']);
        $this->assertStringStartsWith('data:image/png;base64,', $intent['qr_svg_url']);

        $png = base64_decode(substr($intent['qr_svg_url'], strlen('data:image/png;base64,')), true);
        $this->assertStringStartsWith("\x89PNG", $png);
    }

    public function test_a_failed_render_omits_the_image_instead_of_calling_a_third_party(): void
    {
        $this->mock(QrCodePng::class, function (MockInterface $qr) {
            $qr->shouldReceive('render')->once()->andThrow(new RuntimeException('GD unavailable'));
        });

        $intent = (new QrPaymentDriver)->initiate(['amount_minor' => 5000, 'currency' => 'JMD']);

        $this->assertNull($intent['qr_svg_url']);
        $this->assertStringStartsWith(url('/p/CH-'), $intent['payment_url']);
        $this->assertStringNotContainsString('qrserver', json_encode($intent));
    }

    public function test_no_page_loads_a_qr_from_a_third_party(): void
    {
        $offenders = [];

        foreach ([resource_path('js'), resource_path('views')] as $root) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

            foreach ($files as $file) {
                // A URL, not a mention: comments may name the old host.
                if (preg_match('#//api\.qrserver\.com#i', file_get_contents($file->getPathname()))) {
                    $offenders[] = $file->getPathname();
                }
            }
        }

        $this->assertSame([], $offenders, 'QR codes must be drawn locally, not fetched from api.qrserver.com');
    }
}
