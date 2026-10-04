<?php

declare(strict_types=1);

namespace App\Livewire\Pdf;

use App\Services\Pdf\EnterprisePdfService;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PdfGenerationHub extends Component
{
    /**
     * Administrators only (`issueDocuments`): it renders invoices, receipts
     * and certificates in the estate's name, with custom content. boot() runs
     * on the first load and on every action request.
     */
    public function boot(): void
    {
        $this->authorize('issueDocuments');
    }

    public string $activeTab = 'catalog'; // catalog, drivers, generator, specifications

    public string $selectedDocType = 'invoice';

    public string $search = '';

    public string $filterOrientation = 'all'; // all, portrait, landscape

    public ?string $feedbackMessage = null;

    // Custom Generator State
    public string $customType = 'invoice';

    public string $recipientName = 'Alexandria Sterling';

    public string $referenceNumber = 'INV-2026-9021';

    public string $amount = '1450.00';

    public string $notes = 'Official document rendered via Community Hub Enterprise PDF Engine';

    public function selectTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function selectDocType(string $type): void
    {
        $this->selectedDocType = $type;
        $this->customType = $type;
    }

    public function downloadDocument(string $type, EnterprisePdfService $service): StreamedResponse
    {
        $catalog = $service->getDocumentCatalog();
        $meta = $catalog[$type] ?? null;

        if (! $meta) {
            abort(404, "Document type [{$type}] not found.");
        }

        $domPdf = $service->generate($type, []);
        $pdfBinary = $domPdf->output();
        $filename = "{$type}-".date('Ymd-His').'.pdf';

        $this->feedbackMessage = "Generated and downloaded [{$meta['name']}] ({$filename}).";

        return response()->streamDownload(
            fn () => print ($pdfBinary),
            $filename,
            ['Content-Type' => 'application/pdf']
        );
    }

    public function downloadCustom(EnterprisePdfService $service): StreamedResponse
    {
        $type = $this->customType;
        $overrides = [
            'reference_no' => $this->referenceNumber,
            'recipient' => [
                'name' => $this->recipientName,
                'email' => 'alex.sterling@example.org',
                'lot' => 'Lot 402 - Cedar Ridge',
            ],
            'total_amount' => (float) $this->amount,
            'notes' => $this->notes,
        ];

        $domPdf = $service->generate($type, $overrides);
        $pdfBinary = $domPdf->output();
        $filename = "custom-{$type}-".date('Ymd-His').'.pdf';

        $this->feedbackMessage = "Custom [{$type}] successfully generated ({$filename}).";

        return response()->streamDownload(
            fn () => print ($pdfBinary),
            $filename,
            ['Content-Type' => 'application/pdf']
        );
    }

    public function render(EnterprisePdfService $service): View
    {
        $catalog = $service->getDocumentCatalog();
        $drivers = $service->getSupportedDrivers();
        $activeDriver = $service->getActiveDriver();

        // Filter catalog
        $filteredCatalog = array_filter($catalog, function ($item, $key) {
            if ($this->filterOrientation !== 'all' && $item['orientation'] !== $this->filterOrientation) {
                return false;
            }

            if ($this->search) {
                $needle = strtolower($this->search);
                $haystack = strtolower($item['name'].' '.$key.' '.$item['description'].' '.$item['legal_validity']);
                if (! str_contains($haystack, $needle)) {
                    return false;
                }
            }

            return true;
        }, ARRAY_FILTER_USE_BOTH);

        return view('livewire.pdf.pdf-generation-hub', [
            'catalog' => $filteredCatalog,
            'totalDocuments' => count($catalog),
            'drivers' => $drivers,
            'activeDriver' => $activeDriver,
            'selectedMeta' => $catalog[$this->selectedDocType] ?? null,
        ])->layout('layouts.app', ['header' => 'Enterprise PDF Generation Hub']);
    }
}
