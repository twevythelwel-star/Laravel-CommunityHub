<?php

namespace App\Http\Requests\Directory;

use Illuminate\Foundation\Http\FormRequest;

class StoreDirectoryPropertyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageUsers') ?? false;
    }

    public function rules(): array
    {
        return [
            'lot_number' => ['required', 'string', 'max:50'],
            'street_address' => ['nullable', 'string', 'max:255'],
        ];
    }
}
