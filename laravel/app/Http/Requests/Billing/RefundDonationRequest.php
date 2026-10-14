<?php

namespace App\Http\Requests\Billing;

use Illuminate\Foundation\Http\FormRequest;

class RefundDonationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageFundraisers') ?? false;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
