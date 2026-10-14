<?php

namespace App\Http\Requests\Boundary;

use Illuminate\Foundation\Http\FormRequest;

class ValidateBoundaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageBoundary') ?? false;
    }

    public function rules(): array
    {
        return [
            'points' => ['required', 'array', 'min:1', 'max:8'],
            'points.*.lat' => ['required', 'numeric', 'between:-90,90'],
            'points.*.lng' => ['required', 'numeric', 'between:-180,180'],
        ];
    }
}
