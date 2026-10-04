<?php

declare(strict_types=1);

namespace App\Services\Payments\Modular\Contracts;

use Illuminate\Http\Request;

/**
 * Capability contract for payment integrations that verify incoming
 * server-to-server webhook signatures and map events canonically.
 */
interface WebhookVerifiableContract extends PaymentModuleContract
{
    /**
     * Authenticate and verify the incoming webhook signature.
     */
    public function verifyWebhookSignature(Request $request): bool;

    /**
     * Parse raw webhook request into canonical event data:
     * ['event' => string, 'reference' => string, 'amount' => float, 'currency' => string, 'status' => string]
     *
     * @return array<string, mixed>
     */
    public function parseWebhookEvent(Request $request): array;
}
