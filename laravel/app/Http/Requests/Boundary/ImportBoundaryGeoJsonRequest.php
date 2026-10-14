<?php

namespace App\Http\Requests\Boundary;

use Illuminate\Foundation\Http\FormRequest;

class ImportBoundaryGeoJsonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageBoundary') ?? false;
    }

    public function rules(): array
    {
        return [
            'file' => ['nullable', 'file'],
            'geojson' => ['nullable'],
        ];
    }
}
