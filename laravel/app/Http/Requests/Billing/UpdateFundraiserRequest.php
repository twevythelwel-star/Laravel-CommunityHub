<?php

namespace App\Http\Requests\Billing;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFundraiserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageFundraisers') ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'in:Active,Upcoming,Completed,Canceled'],
        ];
    }
}
