<?php

namespace App\Http\Requests\Access;

use Illuminate\Foundation\Http\FormRequest;

class ResolveIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageSecurity') ?? false;
    }

    public function rules(): array
    {
        return [
            'resolution_notes' => ['required', 'string', 'max:500'],
        ];
    }
}
