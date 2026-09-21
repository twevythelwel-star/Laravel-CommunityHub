<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Stripe\Checkout\Session;
use Stripe\Stripe;

/**
 * Stripe Checkout.
 *
 * Previously this class had a "demo / sandbox" branch that ran whenever no
 * secret key was configured — which was always, because config/services.php
 * had no `stripe` block at all. In that branch createCheckoutSession()
 * returned the success URL directly and completePayment() marked the invoice
 * Paid. Pressing "Pay" settled a debt with no money and no processor.
 *
 * The simulation is gone. Settlement now happens only when Stripe itself says
 * the session was paid, and the caller is expected to check the return value.
 */
class StripePaymentService
{
    protected ?string $secretKey;

    public function __construct()
    {
        $this->secretKey = config('services.stripe.secret');
    }

    /**
     * Whether a usable Stripe secret key is configured.
     *
     * Callers gate their routes on this: with no processor behind them, the
     * checkout endpoints must not be reachable at all rather than fall back to
     * something that looks like a payment.
     */
    public function isLive(): bool
    {
        return is_string($this->secretKey) && str_starts_with($this->secretKey, 'sk_');
    }

    /**
     * Create a Stripe Checkout Session for an invoice.
     * Returns the redirect URL for the user to complete payment.
     */
    public function createCheckoutSession(Invoice $invoice, string $successUrl, string $cancelUrl): string
    {
        if (! $this->isLive()) {
            throw new RuntimeException('Stripe is not configured; refusing to simulate a checkout session.');
        }

        Stripe::setApiKey($this->secretKey);

        $session = Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => strtolower($invoice->currency ?: 'usd'),
                    'product_data' => [
                        'name' => "HOA Assessment — Ref: {$invoice->reference}",
                        'description' => 'Community Hub Maintenance & Operations Assessment',
                    ],
                    'unit_amount' => $invoice->amount_minor,
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'customer_email' => $invoice->user->email,
            'client_reference_id' => (string) $invoice->id,
            'success_url' => $successUrl.'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $cancelUrl,
        ]);

        $invoice->update(['stripe_session_id' => $session->id]);

        return $session->url;
    }

    /**
     * Verify a completed checkout with Stripe and settle the invoice.
     *
     * Two checks matter here and neither existed:
     *
     *   1. `client_reference_id` must name *this* invoice. The session id
     *      arrives as a query parameter the browser controls, so without this
     *      a genuinely paid session for a small invoice could be replayed
     *      against a large one.
     *   2. `payment_status` must be `paid`.
     *
     * Returns false — leaving the invoice untouched — when either fails.
     */
    public function completePayment(Invoice $invoice, string $sessionId): bool
    {
        if ($invoice->status === 'Paid') {
            return true;
        }

        if (! $this->isLive()) {
            Log::warning("Refused to settle invoice {$invoice->reference}: Stripe is not configured.");

            return false;
        }

        Stripe::setApiKey($this->secretKey);
        $session = Session::retrieve($sessionId);

        if ((string) $session->client_reference_id !== (string) $invoice->id) {
            Log::channel('security')->warning('Stripe session does not belong to the invoice it was presented for', [
                'invoice_id' => $invoice->id,
                'session_id' => $sessionId,
                'session_client_reference_id' => $session->client_reference_id,
            ]);

            return false;
        }

        if ($session->payment_status !== 'paid') {
            return false;
        }

        $invoice->update([
            'status' => 'Paid',
            'paid_at' => now(),
            'stripe_session_id' => $session->id,
            'stripe_payment_intent' => $session->payment_intent,
        ]);

        Log::info("Invoice {$invoice->reference} paid via Stripe ({$session->id})");

        return true;
    }
}
