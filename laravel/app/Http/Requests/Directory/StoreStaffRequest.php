<?php

namespace App\Http\Requests\Directory;

use Illuminate\Foundation\Http\FormRequest;

class StoreStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('registerStaff') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'job' => ['required', 'string', 'max:120'],
            'id_type' => ['required', 'string', 'max:60'],
            'id_number' => ['required', 'string', 'max:60'],
            'id_expiry' => ['required', 'date', 'after:today'],
            'id_image_url' => ['nullable', 'url', 'max:2048'],
            'photo_url' => ['nullable', 'url', 'max:2048'],
        ];
    }
}
