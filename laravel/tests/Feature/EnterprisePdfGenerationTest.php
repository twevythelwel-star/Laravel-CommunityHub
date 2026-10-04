<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Pdf\PdfGenerationHub;
use App\Models\User;
use App\Services\Pdf\EnterprisePdfService;
use Barryvdh\DomPDF\PDF;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EnterprisePdfGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_dompdf_package_is_registered_and_bound_in_container(): void
    {
        $this->assertTrue(app()->bound('dompdf.wrapper'));
        $pdfInstance = app('dompdf.wrapper');
        $this->assertInstanceOf(PDF::class, $pdfInstance);
    }

    public function test_pdf_service_returns_all_eight_enterprise_document_types(): void
    {
        $service = app(EnterprisePdfService::class);
        $catalog = $service->getDocumentCatalog();

        $this->assertCount(8, $catalog);

        $expectedTypes = [
            'invoice' => 'Invoices',
            'certificate' => 'Certificates',
            'report' => 'Reports',
            'receipt' => 'Receipts',
            'statement' => 'Statements',
            'letter' => 'Letters',
            'ticket' => 'Tickets',
            'government_form' => 'Government forms',
        ];

        foreach ($expectedTypes as $typeKey => $expectedName) {
            $this->assertArrayHasKey($typeKey, $catalog);
            $this->assertNotEmpty($catalog[$typeKey]['name']);
            $this->assertNotEmpty($catalog[$typeKey]['view']);
            $this->assertNotEmpty($catalog[$typeKey]['paper_size']);
            $this->assertContains($catalog[$typeKey]['orientation'], ['portrait', 'landscape']);
        }
    }

    public function test_driver_matrix_reports_barryvdh_dompdf_and_spatie_rendering_options(): void
    {
        $service = app(EnterprisePdfService::class);
        $drivers = $service->getSupportedDrivers();

        $this->assertArrayHasKey('dompdf', $drivers);
        $this->assertArrayHasKey('chromium', $drivers);
        $this->assertArrayHasKey('gotenberg', $drivers);
        $this->assertArrayHasKey('cloudflare', $drivers);
        $this->assertArrayHasKey('weasyprint', $drivers);

        $dompdf = $drivers['dompdf'];
        $this->assertTrue($dompdf['is_installed']);
        $this->assertStringContainsString('Laravel 9 through 13', $dompdf['laravel_support']);
        $this->assertFalse($dompdf['requires_headless_binary']);

        $activeDriver = $service->getActiveDriver();
        $this->assertSame('dompdf', $activeDriver['key']);
    }

    public function test_pdf_service_generates_valid_pdf_binaries_for_all_eight_use_cases(): void
    {
        $service = app(EnterprisePdfService::class);
        $documentTypes = [
            'invoice',
            'certificate',
            'report',
            'receipt',
            'statement',
            'letter',
            'ticket',
            'government_form',
        ];

        foreach ($documentTypes as $type) {
            $domPdf = $service->generate($type, [
                'recipient' => ['name' => 'Test Resident Jane Doe'],
            ]);
            $pdfBinary = $domPdf->output();

            $this->assertIsString($pdfBinary);
            $this->assertGreaterThan(500, strlen($pdfBinary), "PDF for [{$type}] should be non-empty binary.");
            $this->assertStringStartsWith('%PDF-', $pdfBinary, "Binary for [{$type}] must start with standard PDF magic bytes.");
        }
    }

    public function test_api_v1_pdf_catalog_and_drivers_endpoints(): void
    {
        $this->actingAs(User::factory()->role(UserRole::Admin)->create()); // issuing documents and exports needs an administrator
        $catalogResponse = $this->getJson(route('api.v1.pdf.catalog'));
        $catalogResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(8, 'data.catalog');

        $driversResponse = $this->getJson(route('api.v1.pdf.drivers'));
        $driversResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.active_driver.key', 'dompdf');
    }

    public function test_api_v1_pdf_preview_and_download_return_proper_mime_headers(): void
    {
        $this->actingAs(User::factory()->role(UserRole::Admin)->create()); // issuing documents and exports needs an administrator
        // 1. Preview (Inline)
        $previewResponse = $this->post(route('api.v1.pdf.preview', ['type' => 'invoice']));
        $previewResponse->assertOk();
        $this->assertSame('application/pdf', $previewResponse->headers->get('Content-Type'));
        $this->assertStringContainsString('inline', (string) $previewResponse->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF-', $previewResponse->getContent());

        // 2. Download (Attachment)
        $downloadResponse = $this->post(route('api.v1.pdf.download', ['type' => 'certificate']));
        $downloadResponse->assertOk();
        $this->assertSame('application/pdf', $downloadResponse->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', (string) $downloadResponse->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF-', $downloadResponse->getContent());
    }

    public function test_api_v1_pdf_generate_supports_custom_runtime_payload(): void
    {
        $this->actingAs(User::factory()->role(UserRole::Admin)->create()); // issuing documents and exports needs an administrator
        $response = $this->postJson(route('api.v1.pdf.generate'), [
            'type' => 'receipt',
            'data' => [
                'reference' => 'RCP-TEST-9999',
                'recipient_name' => 'Custom API Payer',
                'total' => 775.50,
            ],
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.type', 'receipt');
    }

    public function test_api_v1_pdf_rejects_unknown_document_type(): void
    {
        $this->actingAs(User::factory()->role(UserRole::Admin)->create()); // issuing documents and exports needs an administrator
        $response = $this->post(route('api.v1.pdf.preview', ['type' => 'nonexistent_blueprint']));
        $response->assertNotFound();
    }

    public function test_operations_pdf_route_requires_authentication(): void
    {
        $response = $this->get(route('operations.pdf'));
        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_access_pdf_operations_hub(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $response = $this->actingAs($user)->get(route('operations.pdf'));
        $response->assertOk();
        $response->assertSee('Enterprise PDF Generation Architecture');
        $response->assertSee('Barryvdh DOMPDF');
    }

    public function test_livewire_pdf_generation_hub_renders_and_handles_actions(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $component = Livewire::actingAs($user)
            ->test(PdfGenerationHub::class)
            ->assertSee('Document Showcase')
            ->assertSee('HOA Assessments & Dues Invoice')
            ->assertSee('Municipal Residency Declaration (RES-104)')
            ->call('selectTab', 'drivers')
            ->assertSee('Rendering Architecture & Driver Ecosystem')
            ->assertSee('Chromium / Puppeteer (Spatie Laravel PDF)')
            ->call('selectTab', 'generator')
            ->assertSee('Dynamic Generation Studio')
            ->call('selectTab', 'specifications')
            ->assertSee('Enterprise Statutory Document Standards');

        // Test download call
        $downloadCall = $component->call('downloadDocument', 'ticket');
        $this->assertNotNull($downloadCall);
    }
}
