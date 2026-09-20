<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session;
use Stripe\Stripe;

class StripePaymentService
{
    protected ?string $secretKey;

    public function __construct()
    {
        $this->secretKey = config('services.stripe.secret');
    }

    /**
     * Create a Stripe Checkout Session for an invoice.
     * Returns the redirect URL for the user to complete payment.
     */
    public function createCheckoutSession(Invoice $invoice, string $successUrl, string $cancelUrl): string
    {
        if ($this->secretKey && str_starts_with($this->secretKey, 'sk_')) {
            Stripe::setApiKey($this->secretKey);

            $session = Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => strtolower($invoice->currency ?: 'usd'),
                        'product_data' => [
                            'name' => "HOA Assessment — Ref: {$invoice->reference}",
                            'description' => "Community Hub Maintenance & Operations Assessment",
                        ],
                        'unit_amount' => $invoice->amount_minor,
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'customer_email' => $invoice->user->email,
                'client_reference_id' => (string) $invoice->id,
                'success_url' => $successUrl . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $cancelUrl,
            ]);

            $invoice->update(['stripe_session_id' => $session->id]);

            return $session->url;
        }

        // Demo / Sandbox mode: create a simulated session token and return direct success redirect
        $demoSessionId = 'cs_demo_' . bin2hex(random_bytes(12));
        $invoice->update(['stripe_session_id' => $demoSessionId]);

        return $successUrl . '?session_id=' . $demoSessionId;
    }

    /**
     * Complete payment verification and mark invoice as paid.
     */
    public function completePayment(Invoice $invoice, string $sessionId): bool
    {
        if ($invoice->status === 'Paid') {
            return true;
        }

        if ($this->secretKey && str_starts_with($this->secretKey, 'sk_')) {
            Stripe::setApiKey($this->secretKey);
            $session = Session::retrieve($sessionId);

            if ($session->payment_status !== 'paid') {
                return false;
            }

            $invoice->update([
                'status' => 'Paid',
                'paid_at' => now(),
                'stripe_session_id' => $session->id,
                'stripe_payment_intent' => $session->payment_intent,
            ]);

            Log::info("Invoice {$invoice->reference} paid via live Stripe ({$session->id})");
            return true;
        }

        // Complete demo payment
        $invoice->update([
            'status' => 'Paid',
            'paid_at' => now(),
            'stripe_session_id' => $sessionId,
            'stripe_payment_intent' => 'pi_demo_' . bin2hex(random_bytes(10)),
        ]);

        Log::info("Invoice {$invoice->reference} paid via simulated Stripe session ({$sessionId})");
        return true;
    }
}
