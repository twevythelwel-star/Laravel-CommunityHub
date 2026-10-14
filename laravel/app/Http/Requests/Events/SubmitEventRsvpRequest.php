<?php

namespace App\Http\Requests\Events;

use Illuminate\Foundation\Http\FormRequest;

class SubmitEventRsvpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'contact' => ['required', 'string', 'max:50'],
            'vehicle' => ['nullable', 'string', 'max:20'],
        ];
    }
}
