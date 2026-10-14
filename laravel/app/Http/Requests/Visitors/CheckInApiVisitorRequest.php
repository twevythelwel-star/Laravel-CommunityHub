<?php

namespace App\Http\Requests\Visitors;

use Illuminate\Foundation\Http\FormRequest;

class CheckInApiVisitorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('scanPasses') ?? false;
    }

    public function rules(): array
    {
        return [
            'gate' => ['nullable', 'string', 'max:100'],
        ];
    }
}
