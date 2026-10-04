<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Pdf\EnterprisePdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PdfApiController extends Controller
{
    /**
     * Rendering is administrators' (`issueDocuments`): these are invoices,
     * receipts and certificates in the estate's name, filled with whatever
     * the caller sends. Also on the routes; declared here so a regrouped
     * routes/api.php cannot open it.
     */
    public function __construct(
        protected EnterprisePdfService $pdfService
    ) {
        $this->middleware(['auth:sanctum', 'active']);
        $this->middleware('can:issueDocuments')->only(['preview', 'download', 'generate']);
    }

    /**
     * Get catalog of all 8 supported document use cases.
     */
    public function catalog(): JsonResponse
    {
        $catalog = $this->pdfService->getDocumentCatalog();

        return ApiResponse::success(
            [
                'total_documents' => count($catalog),
                'active_driver' => $this->pdfService->getActiveDriver(),
                'catalog' => $catalog,
            ],
            'Enterprise PDF document catalog retrieved.'
        );
    }

    /**
     * Get comparison of supported PDF engines (Barryvdh DOMPDF & Spatie Laravel PDF).
     */
    public function drivers(): JsonResponse
    {
        $drivers = $this->pdfService->getSupportedDrivers();

        return ApiResponse::success(
            [
                'active_driver' => $this->pdfService->getActiveDriver(),
                'drivers' => $drivers,
            ],
            'Supported PDF rendering engines retrieved.'
        );
    }

    /**
     * Preview inline PDF document in browser.
     */
    public function preview(Request $request, string $type): Response
    {
        $normalizedType = str_replace('-', '_', strtolower($type));
        $catalog = $this->pdfService->getDocumentCatalog();

        if (! isset($catalog[$normalizedType])) {
            abort(404, "Document type [{$type}] not found.");
        }

        $data = $request->all();

        return $this->pdfService->streamDocument($type, $data);
    }

    /**
     * Download document attachment PDF.
     */
    public function download(Request $request, string $type): Response
    {
        $normalizedType = str_replace('-', '_', strtolower($type));
        $catalog = $this->pdfService->getDocumentCatalog();

        if (! isset($catalog[$normalizedType])) {
            abort(404, "Document type [{$type}] not found.");
        }

        $data = $request->all();
        $filename = $request->query('filename');

        return $this->pdfService->downloadDocument($type, $data, $filename);
    }

    /**
     * Compile and save PDF document, returning metadata.
     */
    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string',
            'data' => 'nullable|array',
            'disk' => 'nullable|string|in:local,public',
        ]);

        $type = $validated['type'];
        $data = $validated['data'] ?? [];
        $disk = $validated['disk'] ?? 'local';

        $filePath = $this->pdfService->saveDocument($type, $data, $disk);

        return ApiResponse::success(
            [
                'type' => $type,
                'disk' => $disk,
                'path' => $filePath,
                'reference' => $data['reference'] ?? 'DOC-'.time(),
                'generated_at' => now()->toIso8601String(),
            ],
            "PDF document [{$type}] compiled and stored successfully.",
            201
        );
    }
}
