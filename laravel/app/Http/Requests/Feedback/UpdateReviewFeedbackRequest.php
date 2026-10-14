<?php

namespace App\Http\Requests\Feedback;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReviewFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reviewFeedback') ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'in:New,In Progress,Resolved'],
            'admin_response' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
