<?php

namespace App\Http\Requests\Directory;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageUsers') ?? false;
    }

    public function rules(): array
    {
        return [
            'display_name' => ['sometimes', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'role' => ['sometimes', 'string', 'in:'.implode(',', array_column(UserRole::cases(), 'value'))],
            'title' => ['nullable', 'string', 'max:120'],
            'lot' => ['nullable', 'string', 'max:60'],
            'street' => ['nullable', 'string', 'max:120'],
            'status' => ['sometimes', 'in:Active,Inactive'],
        ];
    }
}
