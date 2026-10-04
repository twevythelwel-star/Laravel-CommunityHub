<?php

namespace Tests\Feature;

use App\Enums\VisitorStatus;
use App\Models\User;
use App\Models\Visitor;
use App\Services\GatePassEngine;
use App\Services\QrCodePng;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Gate and visitor pass PDFs carry their QR code as an image rendered on the
 * server. They used to point at api.qrserver.com, which received each pass's
 * QR content — for a visitor, the guest-pass link, a bearer credential — and
 * which DomPDF cannot fetch anyway with remote loading off.
 */
class PassPdfQrCodeTest extends TestCase
{
    use RefreshDatabase;

    /** The PDF embeds an image object only if DomPDF actually drew the QR. */
    private function assertEmbedsAnImage(string $pdf): void
    {
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertMatchesRegularExpression('#/Subtype\s*/Image#', $pdf, 'the QR image should be embedded in the PDF');
    }

    /** Lets the real renderer run while checking what it was asked to encode. */
    private function expectQrFor(string $content): void
    {
        $this->partialMock(QrCodePng::class, function (MockInterface $qr) use ($content) {
            $qr->shouldReceive('render')->once()->with($content, 180, 4)->passthru();
        });
    }

    public function test_the_visitor_pass_pdf_embeds_a_locally_rendered_qr(): void
    {
        $homeowner = User::factory()->create();
        $visitor = Visitor::create([
            'name' => 'Michael Chang', 'type' => 'One-time',
            'homeowner_id' => $homeowner->id, 'homeowner_name' => $homeowner->name,
            'status' => VisitorStatus::Expected, 'expected_at' => now()->addHours(2),
        ]);

        $this->expectQrFor(route('guest-pass.show', ['token' => $visitor->share_token]));

        $response = $this->get(route('pdf.visitor-pass', ['token' => $visitor->share_token]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertEmbedsAnImage($response->getContent());
    }

    public function test_the_resident_gate_pass_pdf_embeds_a_locally_rendered_qr(): void
    {
        $holder = User::factory()->create();
        $pass = app(GatePassEngine::class)->issuePassFor($holder);

        $this->expectQrFor("GATE-PASS-{$pass->pass_id}");

        $response = $this->actingAs($holder)
            ->get(route('dashboard.gate-pass.pdf', ['gatePass' => $pass->id]))
            ->assertOk();

        $this->assertEmbedsAnImage($response->getContent());
    }

    public function test_the_template_no_longer_calls_a_third_party(): void
    {
        $template = file_get_contents(resource_path('views/pdf/gate-pass.blade.php'));

        // No image is loaded from anywhere off the server (the comment may name the old host).
        $this->assertDoesNotMatchRegularExpression('#src\s*=\s*["\']\s*(https?:)?//#i', $template);
        $this->assertStringContainsString('src="{{ $qrImage }}"', $template);
    }
}
