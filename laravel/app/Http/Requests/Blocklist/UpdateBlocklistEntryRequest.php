<?php

namespace App\Http\Requests\Blocklist;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBlocklistEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageBlocklist') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:120'],
            'reason' => ['sometimes', 'string', 'max:1000'],
            'photo_url' => ['nullable', 'url', 'max:2048'],
            'expiry_date' => ['nullable', 'date'],
        ];
    }
}
