<?php

namespace App\Http\Requests\Maintenance;

use Illuminate\Foundation\Http\FormRequest;

class StoreMaintenanceIssueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'category' => [
                'required',
                'string',
                'in:potholes,streetlights,drainage,water_leaks,waste_collection,damaged_common_areas,other',
            ],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'min:10'],
            'location' => ['required', 'string', 'max:255'],
            'priority' => ['required', 'string', 'in:low,medium,high,emergency'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'photos' => ['nullable', 'array', 'max:5'],
            'photos.*' => ['nullable', 'image', 'max:10240'], // 10MB max per photo
        ];
    }

    public function messages(): array
    {
        return [
            'category.in' => 'Please select a valid maintenance category.',
            'title.required' => 'A short summary title is required.',
            'description.min' => 'Please provide at least 10 characters describing the issue.',
            'location.required' => 'Please specify where this issue is located.',
            'photos.*.image' => 'Attachments must be valid image files (JPG, PNG, WebP).',
            'photos.*.max' => 'Each photo cannot exceed 10 MB.',
        ];
    }
}
