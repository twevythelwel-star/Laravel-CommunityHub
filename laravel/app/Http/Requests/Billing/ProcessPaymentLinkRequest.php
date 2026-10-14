<?php

namespace App\Http\Requests\Billing;

use App\Models\PaymentLink;
use App\Services\Payments\PaymentOrchestratorService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A payment submitted on a public payment link, by someone not signed in.
 */
class ProcessPaymentLinkRequest extends FormRequest
{
    /**
     * Runs before validation, so a disabled feature or an unknown link is a
     * 404 rather than a 422 that would confirm the endpoint exists.
     */
    public function authorize(): bool
    {
        abort_unless(config('payments.public_links_enabled'), 404);
        abort_unless(
            PaymentLink::where('token', $this->route('token'))->where('active', true)->exists(),
            404,
        );

        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(PaymentOrchestratorService $orchestrator): array
    {
        return [
            // Office-confirmed channels only: a stranger has no Community
            // Wallet, and card needs a Stripe Checkout flow this page lacks.
            'channel' => ['required', 'string', Rule::in(array_values(array_filter(
                $orchestrator->channelKeys(),
                fn (string $key) => $orchestrator->requiresOfficeConfirmation($key),
            )))],
            'payer_name' => ['required', 'string', 'max:100'],
            'lot' => ['required', 'string', 'max:50'],
            'custom_amount' => ['nullable', 'numeric', 'min:1'],
        ];
    }
}
