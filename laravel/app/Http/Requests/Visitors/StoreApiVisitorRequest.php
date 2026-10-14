<?php

namespace App\Http\Requests\Visitors;

use Illuminate\Foundation\Http\FormRequest;

class StoreApiVisitorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('registerVisitors') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'contact' => ['nullable', 'string', 'max:120'],
            'type' => ['required', 'in:One-time,Recurring'],
            'expected_at' => ['required', 'date'],
            'date_range' => ['nullable', 'string', 'max:120'],
            'notify_email' => ['nullable', 'boolean'],
            'notify_sms' => ['nullable', 'boolean'],
            'notify_whatsapp' => ['nullable', 'boolean'],
            'pass_category' => ['nullable', 'in:VISITOR,CONTRACTOR'],
        ];
    }
}
