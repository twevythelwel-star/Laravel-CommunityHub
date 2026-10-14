<?php

namespace App\Http\Requests\Blocklist;

use Illuminate\Foundation\Http\FormRequest;

class ReviewBlocklistRemovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageBlocklist') ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'in:Approved,Declined'],
            'reviewer_note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
