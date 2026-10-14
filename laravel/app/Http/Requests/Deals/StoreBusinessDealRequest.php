<?php

namespace App\Http\Requests\Deals;

use Illuminate\Foundation\Http\FormRequest;

class StoreBusinessDealRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageUsers') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'logo_url' => $this->input('logo_url', $this->input('logoUrl')),
            'ai_hint' => $this->input('ai_hint', $this->input('aiHint')),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'logo_url' => ['nullable', 'url', 'max:2048'],
            'ai_hint' => ['nullable', 'string', 'max:160'],
        ];
    }
}
