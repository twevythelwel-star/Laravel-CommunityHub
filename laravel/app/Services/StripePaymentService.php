<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\StripeEvent;
use App\Models\Transaction;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Stripe\BillingPortal\Session as BillingPortalSession;
use Stripe\Charge;
use Stripe\Checkout\Session;
use Stripe\Customer;
use Stripe\Exception\ApiErrorException;
use Stripe\Refund;
use Stripe\Stripe;
use Stripe\StripeObject;
use Stripe\Webhook;

/**
 * Stripe Checkout, webhooks and refunds.
 *
 * Previously this class had a "demo / sandbox" branch that ran whenever no
 * secret key was configured — which was always, because config/services.php
 * had no `stripe` block at all. In that branch createCheckoutSession()
 * returned the success URL directly and completePayment() marked the invoice
 * Paid. Pressing "Pay" settled a debt with no money and no processor.
 *
 * The simulation is gone. Settlement happens only when Stripe itself says the
 * session was paid, by either of two routes that share one idempotent code
 * path (settleSession):
 *
 *   - the resident's browser returning to the success URL, which is checked
 *     against Stripe's API before anything changes; and
 *   - the `checkout.session.completed` webhook, signed by Stripe, which also
 *     covers the resident who pays and closes the tab before the redirect.
 *
 * Every Stripe payment and refund is written to the transactions ledger with a
 * reference derived from Stripe's own ids, and that reference is unique, so a
 * retried webhook or a double redirect cannot record a payment twice.
 */
class StripePaymentService
{
    /** The ledger channel for card payments taken through Stripe Checkout. */
    public const CHANNEL = 'stripe_card';

    protected ?string $secretKey;

    protected ?string $webhookSecret;

    public function __construct()
    {
        $this->secretKey = config('services.stripe.secret');
        $this->webhookSecret = config('services.stripe.webhook_secret');
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

    /** Whether webhooks can be verified. Without a signing secret, none are accepted. */
    public function acceptsWebhooks(): bool
    {
        return $this->isLive() && is_string($this->webhookSecret) && str_starts_with($this->webhookSecret, 'whsec_');
    }

    /**
     * Create a Stripe Checkout Session for an invoice.
     * Returns the redirect URL for the user to complete payment.
     *
     * A still-open session for the invoice is reused rather than replaced, and
     * creation carries an idempotency key, so a double click or two open tabs
     * lead to one session instead of two sessions the resident could both pay.
     */
    public function createCheckoutSession(Invoice $invoice, string $successUrl, string $cancelUrl): string
    {
        if (! $this->isLive()) {
            throw new RuntimeException('Stripe is not configured; refusing to simulate a checkout session.');
        }

        Stripe::setApiKey($this->secretKey);

        if ($invoice->stripe_session_id) {
            try {
                $existing = Session::retrieve($invoice->stripe_session_id);

                if ($existing->status === 'open' && $existing->url) {
                    return $existing->url;
                }
            } catch (ApiErrorException) {
                // Unknown or from another account: fall through and make a new one.
            }
        }

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
            'metadata' => ['invoice_id' => (string) $invoice->id],
            'payment_intent_data' => ['metadata' => ['invoice_id' => (string) $invoice->id]],
            'success_url' => $successUrl.'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $cancelUrl,
        ], [
            // Keyed on the session being replaced, so concurrent requests for
            // the same invoice share a key and get the same session back.
            'idempotency_key' => "invoice-{$invoice->id}-checkout-".($invoice->stripe_session_id ?? 'initial'),
        ]);

        $invoice->update(['stripe_session_id' => $session->id]);

        return $session->url;
    }

    /**
     * Create a Stripe Billing Portal session for self-service payment management.
     */
    public function createCustomerPortalSession(User $user, string $returnUrl): string
    {
        if (! $this->isLive()) {
            throw new RuntimeException('Stripe is not configured; billing portal is unavailable.');
        }

        Stripe::setApiKey($this->secretKey);

        $customerId = null;
        try {
            $existing = Customer::all(['email' => $user->email, 'limit' => 1]);
            if (! empty($existing->data)) {
                $customerId = $existing->data[0]->id;
            } else {
                $created = Customer::create([
                    'email' => $user->email,
                    'name' => $user->display_name,
                    'metadata' => [
                        'user_id' => (string) $user->id,
                        'lot' => $user->lot ?? 'unassigned',
                    ],
                ]);
                $customerId = $created->id;
            }
        } catch (ApiErrorException $e) {
            throw new RuntimeException("Unable to resolve Stripe customer: {$e->getMessage()}", 0, $e);
        }

        $session = BillingPortalSession::create([
            'customer' => $customerId,
            'return_url' => $returnUrl,
        ]);

        return $session->url;
    }

    /**
     * Verify a completed checkout with Stripe and settle the invoice.
     *
     * Used by the success URL. The session id arrives as a query parameter the
     * browser controls, so it is fetched from Stripe and must name *this*
     * invoice; otherwise a genuinely paid session for a small invoice could be
     * replayed against a large one.
     *
     * Returns false — leaving the invoice untouched — when Stripe does not
     * confirm payment.
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

        return in_array($this->settleSession($session), ['settled', 'duplicate'], true);
    }

    /**
     * Verify and apply one webhook delivery.
     *
     * Returns a short outcome for the response body and logs. Throws
     * \Stripe\Exception\SignatureVerificationException or
     * \UnexpectedValueException for a delivery that is not genuinely from
     * Stripe; the controller answers those with 400.
     *
     * The event id is recorded in the same database transaction as its
     * effects. A duplicate delivery finds the row and does nothing; a failure
     * part-way rolls the row back with everything else, so Stripe's retry
     * applies the event from scratch.
     */
    public function handleWebhook(string $payload, ?string $signature): string
    {
        if (! $this->acceptsWebhooks()) {
            throw new RuntimeException('Stripe webhooks are not configured.');
        }

        $event = Webhook::constructEvent($payload, (string) $signature, $this->webhookSecret);

        return DB::transaction(function () use ($event): string {
            $now = now();

            $inserted = StripeEvent::query()->insertOrIgnore([
                'event_id' => $event->id,
                'type' => $event->type,
                'status' => 'pending',
                'payload' => json_encode($event->data->object),
                'processed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($inserted === 0) {
                return 'duplicate';
            }

            $object = $event->data->object;

            $outcome = match ($event->type) {
                'checkout.session.completed',
                'checkout.session.async_payment_succeeded' => $this->settleSession($object),
                'payment_intent.succeeded' => $this->settlePaymentIntent($object),
                'payment_intent.payment_failed',
                'checkout.session.expired' => $this->handlePaymentFailure($object, $event->type),
                'charge.refunded' => $this->syncRefunds($object),
                'charge.dispute.created',
                'charge.dispute.closed',
                'charge.dispute.funds_withdrawn',
                'charge.dispute.funds_reinstated' => $this->handleDispute($object, $event->type),
                'payout.paid',
                'payout.failed' => $this->handlePayout($object, $event->type),
                default => 'ignored',
            };

            StripeEvent::where('event_id', $event->id)->update([
                'status' => $outcome,
            ]);

            return $outcome;
        });
    }

    /**
     * Record a paid Checkout Session and settle its invoice. Idempotent.
     *
     * Outcomes: `settled`, `duplicate` (this payment is already on the
     * ledger), `overpaid` (money arrived for an invoice already settled some
     * other way; recorded so it can be refunded), `unpaid`, `unknown_invoice`
     * and `mismatch`.
     */
    public function settleSession(StripeObject $session): string
    {
        if ($session->payment_status !== 'paid') {
            return 'unpaid';
        }

        $invoice = Invoice::find((int) $session->client_reference_id);

        if (! $invoice) {
            Log::warning('Stripe checkout completed for an invoice that does not exist', [
                'session_id' => $session->id,
                'client_reference_id' => $session->client_reference_id,
            ]);

            return 'unknown_invoice';
        }

        if (strtoupper((string) $session->currency) !== strtoupper($invoice->currency)) {
            Log::channel('security')->warning('Stripe checkout currency does not match its invoice', [
                'invoice_id' => $invoice->id,
                'session_id' => $session->id,
                'session_currency' => $session->currency,
            ]);

            return 'mismatch';
        }

        $paymentIntent = $this->idOf($session->payment_intent);
        $amountMinor = (int) $session->amount_total;

        return DB::transaction(function () use ($invoice, $session, $paymentIntent, $amountMinor): string {
            // Serialises concurrent settlement of the same invoice — the
            // redirect and the webhook routinely arrive within a second.
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            $payment = Transaction::query()->firstOrCreate(
                ['reference' => $this->paymentReference($paymentIntent)],
                [
                    'user_id' => $invoice->user_id,
                    'invoice_id' => $invoice->id,
                    'amount_minor' => $amountMinor,
                    'fee_minor' => 0,
                    'net_amount_minor' => $amountMinor,
                    'settled_at' => now(),
                    'currency' => strtoupper($invoice->currency),
                    'payment_channel' => self::CHANNEL,
                    'status' => 'completed',
                    'notes' => "Stripe Checkout {$session->id}",
                ],
            );

            if (! $payment->wasRecentlyCreated) {
                return 'duplicate';
            }

            if (in_array($invoice->status, ['Paid', 'Waived'], true)) {
                Log::warning('Stripe payment received for an invoice that was already settled; refund it from the ledger', [
                    'invoice' => $invoice->reference,
                    'payment_intent' => $paymentIntent,
                    'amount_minor' => $amountMinor,
                ]);

                return 'overpaid';
            }

            $invoice->forceFill([
                'stripe_session_id' => $session->id,
                'stripe_payment_intent' => $paymentIntent,
            ]);

            if ($amountMinor >= $invoice->amount_minor) {
                $invoice->markPaid();
            } else {
                $invoice->update(['status' => 'Partially Paid']);
            }

            Log::info("Invoice {$invoice->reference} paid via Stripe ({$session->id})");

            return 'settled';
        });
    }

    /**
     * Settle an invoice when a PaymentIntent succeeds directly. Idempotent.
     */
    public function settlePaymentIntent(StripeObject $paymentIntent): string
    {
        $piId = $paymentIntent->id;
        $invoiceId = $paymentIntent->metadata->invoice_id ?? null;

        $invoice = $invoiceId
            ? Invoice::find((int) $invoiceId)
            : Invoice::where('stripe_payment_intent', $piId)->first();

        if (! $invoice) {
            return 'unknown_invoice';
        }

        if ($paymentIntent->status !== 'succeeded') {
            return 'unpaid';
        }

        $amountMinor = (int) $paymentIntent->amount;
        $feeMinor = (int) ($paymentIntent->application_fee_amount ?? 0);

        return DB::transaction(function () use ($invoice, $piId, $amountMinor, $feeMinor): string {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            $payment = Transaction::query()->firstOrCreate(
                ['reference' => $this->paymentReference($piId)],
                [
                    'user_id' => $invoice->user_id,
                    'invoice_id' => $invoice->id,
                    'amount_minor' => $amountMinor,
                    'fee_minor' => $feeMinor,
                    'net_amount_minor' => $amountMinor - $feeMinor,
                    'settled_at' => now(),
                    'currency' => strtoupper($invoice->currency),
                    'payment_channel' => self::CHANNEL,
                    'status' => 'completed',
                    'notes' => "Stripe PaymentIntent {$piId}",
                ],
            );

            if (! $payment->wasRecentlyCreated) {
                return 'duplicate';
            }

            if (in_array($invoice->status, ['Paid', 'Waived'], true)) {
                return 'overpaid';
            }

            $invoice->forceFill([
                'stripe_payment_intent' => $piId,
            ]);

            if ($amountMinor >= $invoice->amount_minor) {
                $invoice->markPaid();
            } else {
                $invoice->update(['status' => 'Partially Paid']);
            }

            return 'settled';
        });
    }

    /**
     * Record a payment failure event on the ledger and audit trail. Idempotent.
     */
    public function handlePaymentFailure(StripeObject $object, string $eventType): string
    {
        $id = $object->id;
        $invoiceId = $object->metadata->invoice_id ?? ($object->client_reference_id ?? null);

        $invoice = $invoiceId ? Invoice::find((int) $invoiceId) : null;
        if (! $invoice && isset($object->payment_intent)) {
            $piId = $this->idOf($object->payment_intent);
            if ($piId) {
                $invoice = Invoice::where('stripe_payment_intent', $piId)->first();
            }
        }

        $errorMessage = $object->last_payment_error->message
            ?? ($object->cancellation_reason ?? 'Payment failed or expired at processor.');

        $amountMinor = (int) ($object->amount ?? ($object->amount_total ?? ($invoice?->amount_minor ?? 0)));

        if ($invoice) {
            Transaction::query()->firstOrCreate(
                ['reference' => "stripe-failed:{$id}"],
                [
                    'user_id' => $invoice->user_id,
                    'invoice_id' => $invoice->id,
                    'amount_minor' => $amountMinor,
                    'fee_minor' => 0,
                    'net_amount_minor' => 0,
                    'currency' => strtoupper($invoice->currency),
                    'payment_channel' => self::CHANNEL,
                    'status' => 'failed',
                    'notes' => "Payment failed ({$eventType}): {$errorMessage}",
                ]
            );

            if ($invoice->status === 'Pending') {
                $invoice->update(['status' => 'Unpaid']);
            }

            Log::channel('security')->warning('Stripe payment failure recorded', [
                'invoice' => $invoice->reference,
                'event_type' => $eventType,
                'error' => $errorMessage,
            ]);
        }

        return 'failed';
    }

    /**
     * Process chargebacks and dispute lifecycle events. Idempotent.
     */
    public function handleDispute(StripeObject $dispute, string $eventType): string
    {
        $disputeId = $dispute->id;
        $chargeId = isset($dispute->charge) ? $this->idOf($dispute->charge) : null;
        $amountMinor = (int) $dispute->amount;
        $currency = strtoupper((string) ($dispute->currency ?? 'USD'));

        // Locate transaction or invoice
        $payment = Transaction::where('payment_channel', self::CHANNEL)
            ->where(function ($q) use ($chargeId) {
                if ($chargeId) {
                    $q->where('notes', 'like', "%{$chargeId}%");
                }
            })->first();

        $invoice = $payment?->invoice;
        if (! $invoice && isset($dispute->metadata->invoice_id)) {
            $invoice = Invoice::find((int) $dispute->metadata->invoice_id);
        }

        if ($eventType === 'charge.dispute.created' || $eventType === 'charge.dispute.funds_withdrawn') {
            if ($invoice) {
                Transaction::query()->firstOrCreate(
                    ['reference' => "stripe-dispute:{$disputeId}:created"],
                    [
                        'user_id' => $invoice->user_id,
                        'invoice_id' => $invoice->id,
                        'amount_minor' => $amountMinor,
                        'fee_minor' => 0,
                        'net_amount_minor' => $amountMinor * -1,
                        'currency' => $currency,
                        'payment_channel' => self::CHANNEL,
                        'status' => 'disputed',
                        'dispute_reason' => $dispute->reason ?? 'general',
                        'notes' => "Chargeback/dispute opened ({$dispute->reason}). Status: {$dispute->status}",
                    ]
                );

                $invoice->update(['status' => 'Disputed']);

                Log::channel('security')->alert('Stripe chargeback/dispute created for invoice', [
                    'invoice' => $invoice->reference,
                    'dispute_id' => $disputeId,
                    'amount' => $amountMinor,
                    'reason' => $dispute->reason ?? 'unknown',
                ]);
            }

            return 'disputed';
        }

        if ($eventType === 'charge.dispute.closed') {
            $status = $dispute->status; // won, lost
            if ($status === 'won') {
                if ($invoice) {
                    $invoice->markPaid();
                    Transaction::query()->firstOrCreate(
                        ['reference' => "stripe-dispute:{$disputeId}:won"],
                        [
                            'user_id' => $invoice->user_id,
                            'invoice_id' => $invoice->id,
                            'amount_minor' => $amountMinor,
                            'fee_minor' => 0,
                            'net_amount_minor' => $amountMinor,
                            'currency' => $currency,
                            'payment_channel' => self::CHANNEL,
                            'status' => 'completed',
                            'notes' => 'Dispute won in estate favor. Funds reinstated.',
                        ]
                    );
                }

                return 'dispute_won';
            } else {
                if ($invoice) {
                    $invoice->update(['status' => 'Unpaid']);
                    Transaction::query()->firstOrCreate(
                        ['reference' => "stripe-dispute:{$disputeId}:lost"],
                        [
                            'user_id' => $invoice->user_id,
                            'invoice_id' => $invoice->id,
                            'amount_minor' => $amountMinor,
                            'fee_minor' => 0,
                            'net_amount_minor' => 0,
                            'currency' => $currency,
                            'payment_channel' => self::CHANNEL,
                            'status' => 'failed',
                            'notes' => 'Dispute lost. Funds returned to cardholder.',
                        ]
                    );
                }

                return 'dispute_lost';
            }
        }

        return 'disputed';
    }

    /**
     * Record payout settlement against open transactions. Idempotent.
     */
    public function handlePayout(StripeObject $payout, string $eventType): string
    {
        $payoutId = $payout->id;

        Transaction::where('payment_channel', self::CHANNEL)
            ->whereNull('payout_reference')
            ->where('status', 'completed')
            ->update([
                'payout_reference' => $payoutId,
                'settled_at' => now(),
            ]);

        return 'payout_settled';
    }

    /**
     * Bring the ledger into line with a charge's refunds. Idempotent.
     *
     * Works from the charge's cumulative `amount_refunded` rather than from
     * individual refund events, so it is correct however many times it runs,
     * in whatever order: it records only the difference between what Stripe
     * says has been refunded and what the ledger already holds.
     *
     * If the refunded payment is the one that settled the invoice, the invoice
     * goes back to Partially Paid or Unpaid: the money that paid it has been
     * returned. A refund of a duplicate payment leaves the invoice alone.
     */
    public function syncRefunds(StripeObject $charge): string
    {
        $paymentIntent = $this->idOf($charge->payment_intent);

        if (! $paymentIntent) {
            return 'ignored';
        }

        $payment = Transaction::query()->where('reference', $this->paymentReference($paymentIntent))->first();
        $invoiceId = $payment?->invoice_id
            ?? Invoice::query()->where('stripe_payment_intent', $paymentIntent)->value('id');

        if (! $invoiceId) {
            Log::warning('Stripe refund for a payment this application did not record', [
                'charge' => $charge->id,
                'payment_intent' => $paymentIntent,
            ]);

            return 'unknown_invoice';
        }

        return DB::transaction(function () use ($charge, $paymentIntent, $invoiceId): string {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoiceId);

            $totalRefunded = (int) $charge->amount_refunded;
            $delta = $totalRefunded - $this->refundedMinor($paymentIntent, $invoice->id);

            if ($delta <= 0) {
                return 'duplicate';
            }

            Transaction::create([
                'user_id' => $invoice->user_id,
                'invoice_id' => $invoice->id,
                'amount_minor' => $delta,
                'fee_minor' => 0,
                'net_amount_minor' => $delta * -1,
                'settled_at' => now(),
                'currency' => strtoupper((string) $charge->currency),
                'payment_channel' => self::CHANNEL,
                // One row per step in the cumulative total, so the same step
                // can never be written twice.
                'reference' => $this->refundReference($paymentIntent, $totalRefunded),
                'status' => 'refunded',
                'notes' => "Stripe refund on charge {$charge->id}",
            ]);

            if ($invoice->stripe_payment_intent === $paymentIntent
                && in_array($invoice->status, ['Paid', 'Partially Paid'], true)) {
                $netMinor = (int) $charge->amount - $totalRefunded;

                if ($netMinor <= 0) {
                    $invoice->update(['status' => 'Unpaid', 'paid_at' => null]);
                    $invoice->items()->update(['status' => 'Unpaid']);
                } elseif ($netMinor < $invoice->amount_minor) {
                    $invoice->update(['status' => 'Partially Paid']);
                }
            }

            Log::info("Stripe refund of {$delta} minor units recorded against invoice {$invoice->reference}");

            return 'refunded';
        });
    }

    /**
     * How much of a recorded Stripe payment can still be refunded, in minor
     * units. Zero for anything that is not a completed Stripe payment.
     */
    public function refundableMinor(Transaction $payment): int
    {
        $paymentIntent = $this->paymentIntentOf($payment);

        if ($paymentIntent === null || $payment->status !== 'completed' || ! $payment->invoice_id) {
            return 0;
        }

        return max(0, $payment->amount_minor - $this->refundedMinor($paymentIntent, $payment->invoice_id));
    }

    /**
     * Refund some or all of a Stripe payment.
     *
     * The idempotency key is built from the payment, the amount and how much
     * was refunded before, so a double-submitted form sends one refund, while
     * a deliberate second partial refund (after the first has been recorded)
     * gets a new key. The charge is then re-read and synced, which is the same
     * path the `charge.refunded` webhook takes, so whichever arrives second
     * records nothing.
     *
     * @throws DomainException when the payment cannot be refunded by that amount
     */
    public function refund(Transaction $payment, int $amountMinor, User $actor, ?string $note = null): void
    {
        if (! $this->isLive()) {
            throw new RuntimeException('Stripe is not configured.');
        }

        $paymentIntent = $this->paymentIntentOf($payment);
        $refundable = $this->refundableMinor($payment);

        if ($paymentIntent === null || $refundable === 0) {
            throw new DomainException('This payment has nothing left to refund.');
        }

        if ($amountMinor < 1 || $amountMinor > $refundable) {
            throw new DomainException('The refund must be between 0.01 and the amount still refundable.');
        }

        $alreadyRefunded = $payment->amount_minor - $refundable;

        Stripe::setApiKey($this->secretKey);

        $refund = Refund::create([
            'payment_intent' => $paymentIntent,
            'amount' => $amountMinor,
            'metadata' => array_filter([
                'invoice_id' => (string) $payment->invoice_id,
                'refunded_by' => (string) $actor->id,
                'note' => $note,
            ]),
        ], [
            'idempotency_key' => "refund-{$paymentIntent}-after-{$alreadyRefunded}-amount-{$amountMinor}",
        ]);

        $this->syncRefunds(Charge::retrieve($this->idOf($refund->charge)));

        $actor->recordActivity("Refunded {$amountMinor} minor units of Stripe payment {$payment->reference}");
    }

    /** Refunds already on the ledger for a PaymentIntent, in minor units. */
    private function refundedMinor(string $paymentIntent, int $invoiceId): int
    {
        $prefix = $this->refundReference($paymentIntent, '');

        return (int) Transaction::query()
            ->where('invoice_id', $invoiceId)
            ->where('status', 'refunded')
            ->where('payment_channel', self::CHANNEL)
            ->get(['reference', 'amount_minor'])
            ->filter(fn (Transaction $t) => str_starts_with($t->reference, $prefix))
            ->sum('amount_minor');
    }

    private function paymentReference(string $paymentIntent): string
    {
        return "stripe:{$paymentIntent}";
    }

    private function refundReference(string $paymentIntent, int|string $cumulativeMinor): string
    {
        return "stripe-refund:{$paymentIntent}:{$cumulativeMinor}";
    }

    private function paymentIntentOf(Transaction $payment): ?string
    {
        return str_starts_with($payment->reference, 'stripe:')
            ? substr($payment->reference, strlen('stripe:'))
            : null;
    }

    /** Stripe fields are either an id string or an expanded object. */
    private function idOf(mixed $value): ?string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }

        return $value instanceof StripeObject ? $value->id : null;
    }
}
