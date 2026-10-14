<?php

namespace App\Http\Requests\Deals;

use Illuminate\Foundation\Http\FormRequest;

class StoreVoucherDealRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageUsers') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'business_id' => $this->input('business_id', $this->input('businessId')),
            'expires_at' => $this->input('expires_at', $this->input('expiresAt')),
        ]);
    }

    public function rules(): array
    {
        return [
            'business_id' => ['required', 'exists:businesses,id'],
            'title' => ['required', 'string', 'min:3', 'max:160'],
            'description' => ['required', 'string', 'min:5', 'max:1000'],
            'expires_at' => ['nullable', 'date'],
        ];
    }
}
