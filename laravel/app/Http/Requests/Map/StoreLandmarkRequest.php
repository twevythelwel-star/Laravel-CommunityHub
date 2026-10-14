<?php

namespace App\Http\Requests\Map;

use Illuminate\Foundation\Http\FormRequest;

class StoreLandmarkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageLandmarks') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'category' => ['required', 'string', 'in:Security Gate,Community Center,Park'],
            'description' => ['nullable', 'string', 'max:1000'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'icon' => ['nullable', 'string', 'max:40'],
        ];
    }
}
