<?php

namespace App\Http\Requests\Visitors;

use Illuminate\Foundation\Http\FormRequest;

class StoreBulkVisitorsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('registerVisitors') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'expected_at' => ['required', 'date', 'after:-1 hour'],
            'pass_category' => ['nullable', 'in:VISITOR,CONTRACTOR'],
            'visitors' => ['required', 'array', 'min:1', 'max:50'],
            'visitors.*.name' => ['required', 'string', 'max:120'],
            'visitors.*.contact' => ['nullable', 'string', 'max:120'],
            'visitors.*.vehicle' => ['nullable', 'string', 'max:120'],
            'notify_email' => ['nullable', 'boolean'],
            'notify_sms' => ['nullable', 'boolean'],
            'notify_whatsapp' => ['nullable', 'boolean'],
        ];
    }
}
