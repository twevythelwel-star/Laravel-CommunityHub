<?php

namespace App\Http\Requests\Guidelines;

use Illuminate\Foundation\Http\FormRequest;

class StoreGuidelineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('broadcastNotices') ?? false;
    }

    public function rules(): array
    {
        return [
            'category' => ['required', 'string', 'min:3', 'max:80'],
            'title' => ['required', 'string', 'min:3', 'max:160'],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }
}
