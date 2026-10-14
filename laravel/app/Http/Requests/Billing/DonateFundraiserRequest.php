<?php

namespace App\Http\Requests\Billing;

use App\Services\Payments\PaymentOrchestratorService;
use Illuminate\Foundation\Http\FormRequest;

class DonateFundraiserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $orchestrator = app(PaymentOrchestratorService::class);

        return [
            'amount' => ['required', 'numeric', 'min:1', 'max:100000000'],
            'donor_name' => ['nullable', 'string', 'max:120'],
            'is_anonymous' => ['boolean'],
            'is_recurring' => ['nullable', 'boolean'],
            'frequency' => ['nullable', 'string', 'in:monthly,quarterly,annual'],
            'channel' => ['nullable', 'string', 'in:'.implode(',', $orchestrator->channelKeys())],
        ];
    }
}
