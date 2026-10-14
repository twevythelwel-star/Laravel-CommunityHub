<?php

namespace App\Http\Requests\Billing;

use Illuminate\Foundation\Http\FormRequest;

class AddFundraiserUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageFundraisers') ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'content' => ['required', 'string', 'max:2000'],
            'image_url' => ['nullable', 'url', 'max:500'],
        ];
    }
}
