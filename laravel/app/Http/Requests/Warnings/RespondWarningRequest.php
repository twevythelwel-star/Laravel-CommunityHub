<?php

namespace App\Http\Requests\Warnings;

use Illuminate\Foundation\Http\FormRequest;

class RespondWarningRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'response' => ['required', 'in:confirmed,denied'],
        ];
    }
}
