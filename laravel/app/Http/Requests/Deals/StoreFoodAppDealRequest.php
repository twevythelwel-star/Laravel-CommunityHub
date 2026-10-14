<?php

namespace App\Http\Requests\Deals;

use Illuminate\Foundation\Http\FormRequest;

class StoreFoodAppDealRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageUsers') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'website_url' => $this->input('website_url', $this->input('websiteUrl')),
            'logo_url' => $this->input('logo_url', $this->input('logoUrl')),
            'ai_hint' => $this->input('ai_hint', $this->input('aiHint')),
            'coupon_percentage' => $this->input('coupon_percentage', $this->input('couponPercentage')),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'website_url' => ['required', 'url', 'max:2048'],
            'logo_url' => ['nullable', 'url', 'max:2048'],
            'ai_hint' => ['nullable', 'string', 'max:160'],
            'coupon_percentage' => ['nullable', 'integer', 'between:0,100'],
        ];
    }
}
