<?php

namespace App\Http\Requests\Billing;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'preferred_payment' => ['required', 'string', 'in:apple_pay,google_pay,samsung_pay,card,bank_transfer'],
            'default_payment_method' => ['nullable', 'string', 'max:100'],
            'notifications' => ['nullable', 'array'],
            'notifications.payment_confirmation' => ['nullable', 'boolean'],
            'notifications.receipt' => ['nullable', 'boolean'],
            'notifications.failed_payment' => ['nullable', 'boolean'],
            'notifications.refund' => ['nullable', 'boolean'],
        ];
    }
}
