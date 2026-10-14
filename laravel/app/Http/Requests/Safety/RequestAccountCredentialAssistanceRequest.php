<?php

namespace App\Http\Requests\Safety;

use App\Services\Safety\SilentAssistanceService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RequestAccountCredentialAssistanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(SilentAssistanceService $safety): array
    {
        $user = $this->user();
        $options = $user ? $safety->optionsForUser($user) : [];

        return [
            'assistance' => ['required', Rule::in($options)],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
