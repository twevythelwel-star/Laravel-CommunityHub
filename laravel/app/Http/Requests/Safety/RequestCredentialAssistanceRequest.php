<?php

namespace App\Http\Requests\Safety;

use App\Services\Safety\SilentAssistanceService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RequestCredentialAssistanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(SilentAssistanceService $safety): array
    {
        $token = (string) $this->route('token');
        $pass = $safety->passForVisitorToken($token);
        $context = $pass ? $safety->accessContextForPass($pass) : null;
        $options = $context['assistanceOptions'] ?? ['security'];

        return [
            'assistance' => ['required', Rule::in($options)],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
