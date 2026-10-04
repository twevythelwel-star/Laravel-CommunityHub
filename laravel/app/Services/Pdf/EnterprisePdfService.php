<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Models\Community;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfWrapper;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class EnterprisePdfService
{
    /**
     * Supported rendering engines comparing Barryvdh DOMPDF with Spatie Laravel PDF options.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getSupportedDrivers(): array
    {
        return [
            'dompdf' => [
                'name' => 'Barryvdh DOMPDF (Active)',
                'provider' => 'barryvdh/laravel-dompdf',
                'engine' => 'Pure PHP / CPDF',
                'status' => 'active_default',
                'is_installed' => true,
                'requires_headless_binary' => false,
                'laravel_support' => 'Laravel 9 through 13',
                'supported_laravel_versions' => 'Laravel 9 through 13',
                'description' => 'Mature pure-PHP wrapper for Dompdf. Highly dependable for statutory forms, receipts, and invoices without needing external binaries.',
                'strengths' => 'Zero external server dependencies, low memory footprint, instantaneous local execution, reliable page numbering.',
                'tradeoffs' => 'Limited modern CSS (no Flexbox or CSS Grid), requires standard HTML tables for multi-column layouts.',
                'ideal_for' => 'Invoices, Receipts, Statements, Letters, Certificates, Government Forms',
            ],
            'chromium' => [
                'name' => 'Chromium / Puppeteer (Spatie Laravel PDF)',
                'provider' => 'spatie/laravel-pdf',
                'engine' => 'Headless Google Chrome',
                'status' => 'supported_bridge',
                'is_installed' => false,
                'requires_headless_binary' => true,
                'laravel_support' => 'Laravel 10 through 12',
                'supported_laravel_versions' => 'Laravel 10 through 12',
                'description' => 'Full Chrome engine rendering with complete modern CSS Flexbox, Grid, and JavaScript graph rendering capabilities.',
                'strengths' => 'Pixel-perfect rendering, full Tailwind CSS, JavaScript charts (Chart.js, ApexCharts), complex layouts.',
                'tradeoffs' => 'Higher CPU and RAM overhead, requires Node.js and Chromium installed on host or container.',
                'ideal_for' => 'Marketing Brochures, High-Fidelity Executive Analytics, Rich Dashboards',
            ],
            'gotenberg' => [
                'name' => 'Gotenberg API (Spatie Laravel PDF)',
                'provider' => 'spatie/laravel-pdf',
                'engine' => 'Dockerized Gotenberg Microservice',
                'status' => 'supported_bridge',
                'is_installed' => false,
                'requires_headless_binary' => true,
                'laravel_support' => 'Laravel 10 through 12',
                'supported_laravel_versions' => 'Laravel 10 through 12',
                'description' => 'Stateless API microservice running Chromium and LibreOffice in Docker containers.',
                'strengths' => 'Offloads rendering load from app servers, horizontal microservice scaling, multi-format conversion.',
                'tradeoffs' => 'Requires dedicated Docker microservice infrastructure and network roundtrips.',
                'ideal_for' => 'High-throughput enterprise batch rendering, multi-tenant container fleets',
            ],
            'cloudflare' => [
                'name' => 'Cloudflare Browser Rendering (Spatie Laravel PDF)',
                'provider' => 'spatie/laravel-pdf',
                'engine' => 'Serverless Cloudflare Worker Chrome',
                'status' => 'supported_bridge',
                'is_installed' => false,
                'requires_headless_binary' => false,
                'laravel_support' => 'Laravel 10 through 12',
                'supported_laravel_versions' => 'Laravel 10 through 12',
                'description' => 'Serverless browser automation running directly on Cloudflare edge network.',
                'strengths' => 'Zero infrastructure maintenance, elastic serverless concurrency, global edge delivery.',
                'tradeoffs' => 'Requires active Cloudflare Workers Paid plan with Browser Rendering entitlement.',
                'ideal_for' => 'Serverless deployments, distributed micro-apps, bursty workloads',
            ],
            'weasyprint' => [
                'name' => 'WeasyPrint Engine (Spatie Laravel PDF)',
                'provider' => 'spatie/laravel-pdf',
                'engine' => 'Python Paged Media Renderer',
                'status' => 'supported_bridge',
                'is_installed' => false,
                'requires_headless_binary' => true,
                'laravel_support' => 'Laravel 10 through 12',
                'supported_laravel_versions' => 'Laravel 10 through 12',
                'description' => 'Visual rendering engine for HTML and CSS conforming strictly to W3C Paged Media standards.',
                'strengths' => 'Strict W3C CSS Paged Media standards, elegant automatic footnotes, page numbering, and book layouts.',
                'tradeoffs' => 'Requires Python runtime with Cairo, Pango, and GDK-PixBuf system libraries.',
                'ideal_for' => 'Statutory Books, Formal Reports, High-End Print Publishing',
            ],
        ];
    }

    /**
     * Get the active PDF driver.
     *
     * @return array<string, mixed>
     */
    public function getActiveDriver(): array
    {
        $key = config('pdf.driver', 'dompdf');
        $drivers = $this->getSupportedDrivers();

        return array_merge(['key' => $key], $drivers[$key] ?? [
            'name' => 'Barryvdh DOMPDF',
            'package' => 'barryvdh/laravel-dompdf',
            'engine' => 'CPDF / HTML5 Parser',
            'is_installed' => true,
        ]);
    }

    /**
     * Document Catalog defining the 8 core enterprise use cases.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getDocumentCatalog(): array
    {
        return [
            'invoice' => [
                'name' => 'HOA Assessments & Dues Invoice',
                'view' => 'pdf.documents.invoice',
                'paper_size' => 'a4',
                'orientation' => 'portrait',
                'target_entity' => 'Property Owners & Tenants',
                'legal_validity' => 'Statutory Enforceable',
                'security_hash' => true,
                'description' => 'Itemized billing for maintenance, reserve sinking fund, and gate access tags.',
                'use_case' => 'Invoices',
                'sample_data' => [
                    'reference' => 'INV-2026-0841',
                    'recipient_name' => 'Alexander Vance',
                    'lot_number' => '42',
                    'street_address' => 'Palmetto Way',
                    'status' => 'paid',
                    'total' => 325.00,
                ],
            ],
            'certificate' => [
                'name' => 'Good Standing & Residency Certificate',
                'view' => 'pdf.documents.certificate',
                'paper_size' => 'a4',
                'orientation' => 'landscape',
                'target_entity' => 'Financial Institutions & Conveyancers',
                'legal_validity' => 'Board Certified Record',
                'security_hash' => true,
                'description' => 'Ornate double-bordered official certificate with digital verification seal and Trustee signatures.',
                'use_case' => 'Certificates',
                'sample_data' => [
                    'reference' => 'CERT-2026-9041',
                    'certificate_title' => 'Certificate of Good Standing & Residency',
                    'recipient_name' => 'Eleanor Rigby-Montague',
                    'lot_number' => '108',
                    'street_address' => 'Ocean View Terrace',
                ],
            ],
            'report' => [
                'name' => 'Monthly Operations & Security Report',
                'view' => 'pdf.documents.report',
                'paper_size' => 'a4',
                'orientation' => 'portrait',
                'target_entity' => 'Executive Board & Security Chiefs',
                'legal_validity' => 'Internal Audited Document',
                'security_hash' => true,
                'description' => 'Executive operations overview, checkpoint crossing KPIs, incident table, and reconciliation.',
                'use_case' => 'Reports',
                'sample_data' => [
                    'reference' => 'REP-2026-10',
                    'period' => 'October 2026',
                    'total_crossings' => 18450,
                    'active_passes' => 2314,
                    'incidents_count' => 3,
                ],
            ],
            'receipt' => [
                'name' => 'Official Revenue & Dues Receipt',
                'view' => 'pdf.documents.receipt',
                'paper_size' => 'a5',
                'orientation' => 'portrait',
                'target_entity' => 'Resident Payers',
                'legal_validity' => 'Tax & Audit Compliant',
                'security_hash' => true,
                'description' => 'Clean receipt with transaction reference, ledger coding, and PAID watermark stamp.',
                'use_case' => 'Receipts',
                'sample_data' => [
                    'reference' => 'REC-2026-4401',
                    'transaction_id' => 'tx_3NqL2e2eZvKYlo2C',
                    'recipient_name' => 'Julian Montgomery',
                    'lot_number' => '18',
                    'total' => 375.00,
                ],
            ],
            'statement' => [
                'name' => 'Periodic Resident Account Statement',
                'view' => 'pdf.documents.statement',
                'paper_size' => 'a4',
                'orientation' => 'portrait',
                'target_entity' => 'Lot Owners',
                'legal_validity' => 'Accounting Ledger Truth',
                'security_hash' => true,
                'description' => 'Detailed multi-month ledger history, debits/credits, running balance, and aging analysis.',
                'use_case' => 'Statements',
                'sample_data' => [
                    'account_number' => 'ACC-LOT-042',
                    'recipient_name' => 'Alexander Vance',
                    'lot_number' => '42',
                    'ending_balance' => 0.00,
                ],
            ],
            'letter' => [
                'name' => 'Formal Board Notice & Policy Letter',
                'view' => 'pdf.documents.letter',
                'paper_size' => 'a4',
                'orientation' => 'portrait',
                'target_entity' => 'Specific Resident / Recipient',
                'legal_validity' => 'Official Legal Notice',
                'security_hash' => true,
                'description' => 'Executive letterhead document for architectural variance decisions, notices, and covenants.',
                'use_case' => 'Letters',
                'sample_data' => [
                    'reference' => 'LTR-2026-0312',
                    'recipient_name' => 'Marcus & Elena Vance',
                    'lot_number' => '42',
                    'subject' => 'Approval of Architectural Variance Application #AV-2026-09',
                ],
            ],
            'ticket' => [
                'name' => 'Amenity & Clubhouse Admission Ticket',
                'view' => 'pdf.documents.ticket',
                'paper_size' => 'a5',
                'orientation' => 'landscape',
                'target_entity' => 'Event Attendees & Guests',
                'legal_validity' => 'Single-Use Access Token',
                'security_hash' => true,
                'description' => 'Perforated-stub admission ticket with access tier badge, barcode, and gate directions.',
                'use_case' => 'Tickets',
                'sample_data' => [
                    'ticket_code' => 'TCK-8492-01A',
                    'event_name' => 'Annual Community Gala & Wine Tasting',
                    'access_tier' => 'VIP Resident Pass',
                    'recipient_name' => 'Alexander Vance',
                    'lot_number' => '42',
                ],
            ],
            'government_form' => [
                'name' => 'Municipal Residency Declaration (RES-104)',
                'view' => 'pdf.documents.government-form',
                'paper_size' => 'a4',
                'orientation' => 'portrait',
                'target_entity' => 'Parish Registry & Electoral Dept',
                'legal_validity' => 'Statutory Affidavit',
                'security_hash' => true,
                'description' => 'Statutory municipal occupancy declaration form for utility connection and precinct validation.',
                'use_case' => 'Government forms',
                'sample_data' => [
                    'reference' => 'GOV-2026-8819',
                    'recipient_name' => 'Alexander Montgomery Vance',
                    'lot_number' => '42',
                    'street_address' => '42 Palmetto Way, Cypress Bay, Coastal Parish',
                ],
            ],
        ];
    }

    /**
     * Generate a compiled DomPDF instance for the requested document type.
     *
     * @param  array<string, mixed>  $data
     */
    public function generate(string $type, array $data = []): DomPdfWrapper
    {
        $normalizedType = str_replace('-', '_', strtolower($type));
        $catalog = $this->getDocumentCatalog();

        if (! isset($catalog[$normalizedType])) {
            throw new InvalidArgumentException("Unsupported document type [{$type}]. Supported types: ".implode(', ', array_keys($catalog)));
        }

        $meta = $catalog[$normalizedType];
        $community = Community::default() ?? Community::first();
        $communityName = $community?->name ?? 'Community Hub';

        $mergedData = array_merge(
            $meta['sample_data'],
            [
                'community_name' => $communityName,
                'community' => $community,
            ],
            $data
        );

        $viewName = 'pdf.documents.'.str_replace('_', '-', $normalizedType);

        return Pdf::loadView($viewName, $mergedData)
            ->setPaper($meta['paper_size'], $meta['orientation'])
            ->setOption([
                'isHtml5ParserEnabled' => true,
                // The templates load nothing remote, and caller data is
                // merged into them: never let a render fetch URLs.
                'isRemoteEnabled' => false,
            ]);
    }

    /**
     * Stream inline PDF response to the browser.
     *
     * @param  array<string, mixed>  $data
     */
    public function streamDocument(string $type, array $data = []): Response
    {
        $pdf = $this->generate($type, $data);
        $filename = "{$type}-".($data['reference'] ?? date('Ymd-His')).'.pdf';

        return $pdf->stream($filename);
    }

    /**
     * Download attachment PDF response.
     *
     * @param  array<string, mixed>  $data
     */
    public function downloadDocument(string $type, array $data = [], ?string $filename = null): Response
    {
        $pdf = $this->generate($type, $data);
        $filename = $filename ?? "{$type}-".($data['reference'] ?? date('Ymd-His')).'.pdf';

        return $pdf->download($filename);
    }

    /**
     * Save generated PDF binary to disk.
     *
     * @param  array<string, mixed>  $data
     */
    public function saveDocument(string $type, array $data = [], string $disk = 'local', ?string $path = null): string
    {
        $pdf = $this->generate($type, $data);
        $filePath = $path ?? "documents/pdf/{$type}-".($data['reference'] ?? date('Ymd-His')).'.pdf';

        Storage::disk($disk)->put($filePath, $pdf->output());

        return $filePath;
    }
}
