<?php

namespace App\Http\Requests\Safety;

use Illuminate\Foundation\Http\FormRequest;

class ResolveDistressAlertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageSecurity') ?? false;
    }

    public function rules(): array
    {
        return [
            'outcome' => ['required', 'in:resolved,false_alarm'],
            'notes' => ['required', 'string', 'max:1000'],
        ];
    }
}
