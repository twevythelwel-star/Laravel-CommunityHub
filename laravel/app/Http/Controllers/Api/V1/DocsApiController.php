<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Api\OpenApiSpec;
use App\Services\Api\Versioning\ApiVersionManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

class DocsApiController extends Controller
{
    /**
     * Return OpenAPI 3.0 specification JSON for the requested API version.
     */
    public function openApiJson(OpenApiSpec $spec, string $version = 'v1'): JsonResponse
    {
        return response()->json($spec->build($this->supported($version)), 200, [
            'Content-Type' => 'application/json',
            'Access-Control-Allow-Origin' => '*',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Render the interactive Swagger UI / OpenAPI documentation interface.
     */
    public function ui(string $version = 'v1'): View
    {
        $version = $this->supported($version);

        return view('api.docs', [
            'version' => $version,
            'specUrl' => url("/api/{$version}/openapi.json"),
        ]);
    }

    /** The requested version if it is supported, otherwise v1. */
    private function supported(string $version): string
    {
        $version = strtolower($version);

        return in_array($version, ApiVersionManager::SUPPORTED_VERSIONS, true) ? $version : 'v1';
    }
}
