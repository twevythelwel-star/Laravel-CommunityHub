<?php

namespace App\Http\Requests\Visitors;

use Illuminate\Foundation\Http\FormRequest;

class RegisterVisitorRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'contact' => ['nullable', 'string', 'max:120'],
            'vehicle' => ['nullable', 'string', 'max:120'],
            'id_type' => ['nullable', 'string', 'max:60'],
            'id_number' => ['nullable', 'string', 'max:60'],
            'type' => ['required', 'in:One-time,Recurring'],
            'expected_at' => ['required', 'date', 'after:-1 hour'],
            'date_range' => ['nullable', 'string', 'max:120'],
            'id_image_url' => ['nullable', 'url', 'max:2048'],
            'notify_email' => ['nullable', 'boolean'],
            'notify_sms' => ['nullable', 'boolean'],
            'notify_whatsapp' => ['nullable', 'boolean'],
            'pass_category' => ['nullable', 'in:VISITOR,CONTRACTOR'],
        ];
    }
}
