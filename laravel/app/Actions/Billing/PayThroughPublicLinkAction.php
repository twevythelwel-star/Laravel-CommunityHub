<?php

namespace App\Actions\Billing;

use App\Models\Payment;
use App\Models\PaymentLink;
use App\Models\Transaction;
use App\Services\Payments\PaymentOrchestratorService;

/**
 * A payment someone makes through a public payment link, without signing in.
 *
 * No user is attached from what the payer types. This used to be
 * `User::where('name', 'like', '%'.$payer_name.'%')->first()`, so a payer
 * name typed on a public form credited whichever resident's name happened to
 * match it first — a stranger could post a payment against someone else's
 * account, and a common surname could do it by accident. The payer's name
 * and lot are kept with the payment instead; the account is the link's (or
 * its invoice's) own.
 *
 * The payment waits for the office like any other office channel: it counts
 * only once one administrator logs it received and another verifies it in a
 * bank reconciliation.
 */
class PayThroughPublicLinkAction
{
    public function __construct(private readonly PaymentOrchestratorService $orchestrator) {}

    /**
     * @param  array{channel: string, payer_name: string, lot: ?string, custom_amount?: mixed}  $validated
     */
    public function execute(PaymentLink $link, array $validated): Payment
    {
        $payment = $this->orchestrator->startPayment([
            'channel' => $validated['channel'],
            'payment_link' => $link,
            'invoice' => $link->invoice,
            'user' => $link->user ?? $link->invoice?->user,
            'applies_to' => $link->invoice_id ? 'invoice' : 'payment_link',
            'purpose' => $link->invoice_id ? Transaction::PURPOSE_HOA_ASSESSMENT : Transaction::PURPOSE_COMMUNITY_PROJECT,
            // A fixed-amount link charges its amount; an open one what the payer entered.
            'amount_minor' => $link->amount_minor ?? (int) (round((float) $validated['custom_amount'], 2) * 100),
            'currency' => $link->currency,
            'metadata' => ['payer_name' => $validated['payer_name'], 'lot' => $validated['lot'] ?? null],
            'source' => 'public_link',
        ]);

        $this->orchestrator->awaitTransfer($payment);
        $link->increment('uses_count');

        return $payment;
    }
}
