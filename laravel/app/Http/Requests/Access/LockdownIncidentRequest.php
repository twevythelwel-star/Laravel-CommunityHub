<?php

namespace App\Http\Requests\Access;

use Illuminate\Foundation\Http\FormRequest;

class LockdownIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageSecurity') ?? false;
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
