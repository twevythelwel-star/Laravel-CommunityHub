<?php

namespace App\Http\Requests\Blocklist;

use Illuminate\Foundation\Http\FormRequest;

class StoreBlocklistEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageBlocklist') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'reason' => ['required', 'string', 'max:1000'],
            'photo_url' => ['nullable', 'url', 'max:2048'],
            'expiry_date' => ['nullable', 'date', 'after:today'],
        ];
    }
}
