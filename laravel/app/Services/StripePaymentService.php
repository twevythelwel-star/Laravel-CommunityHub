<?php

namespace App\Services;

use App\Enums\PaymentState;
use App\Models\Donation;
use App\Models\Fundraiser;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\StripeEvent;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Ledger\LedgerService;
use App\Services\Payments\PaymentOrchestratorService;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Stripe\BalanceTransaction;
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

    /** "stripe:evt_…" while a webhook is applied; recorded in payment history. */
    private ?string $eventSource = null;

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
     * Charges what is still owed — or `$amountMinor` of it, for a part
     * payment — rather than the invoice's face value, which charged a
     * household that had already paid part of a statement for all of it again.
     *
     * A still-open session for the same amount is reused rather than
     * replaced, and creation carries an idempotency key, so a double click or
     * two open tabs lead to one session instead of two the resident could both pay.
     *
     * @throws DomainException when nothing is owed or the amount is out of range
     */
    public function createCheckoutSession(Invoice $invoice, string $successUrl, string $cancelUrl, ?int $amountMinor = null, ?Payment $payment = null): string
    {
        if (! $this->isLive()) {
            throw new RuntimeException('Stripe is not configured; refusing to simulate a checkout session.');
        }

        $balanceMinor = $invoice->balanceRemainingMinor();
        $amountMinor ??= $balanceMinor;

        if ($balanceMinor < 1) {
            throw new DomainException("Invoice {$invoice->reference} has nothing left to pay.");
        }

        if ($amountMinor < 1 || $amountMinor > $balanceMinor) {
            throw new DomainException('The card payment must be between 0.01 and the balance still owed.');
        }

        if ($payment && ($payment->state !== PaymentState::Created || $payment->invoice_id !== $invoice->id)) {
            throw new DomainException("Payment {$payment->transaction_id} cannot be sent to card checkout.");
        }

        $payment ??= $this->orchestrator()->startPayment([
            'user' => $invoice->user,
            'channel' => 'card',
            'invoice' => $invoice,
            'amount_minor' => $amountMinor,
            'currency' => $invoice->currency,
        ]);

        $payment->update(['channel' => 'card', 'provider' => 'stripe', 'amount_minor' => $amountMinor]);

        Stripe::setApiKey($this->secretKey);

        if ($payment->provider_session_id) {
            try {
                $existing = Session::retrieve($payment->provider_session_id);

                if ($existing->status === 'open' && $existing->url && (int) $existing->amount_total === $amountMinor) {
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
                    'currency' => strtolower($invoice->currency),
                    'product_data' => [
                        'name' => "{$payment->purpose} — Ref: {$invoice->reference}",
                        'description' => "CommunityHub payment {$payment->transaction_id}",
                    ],
                    'unit_amount' => $amountMinor,
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'customer_email' => $invoice->user->email,
            'client_reference_id' => (string) $invoice->id,
            'metadata' => [
                'purpose' => 'invoice',
                'invoice_id' => (string) $invoice->id,
                'payment_id' => (string) $payment->id,
                'communityhub_transaction_id' => $payment->transaction_id,
            ],
            'payment_intent_data' => ['metadata' => [
                'invoice_id' => (string) $invoice->id,
                'payment_id' => (string) $payment->id,
                'communityhub_transaction_id' => $payment->transaction_id,
            ]],
            'success_url' => $successUrl.'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $cancelUrl,
        ], [
            // Keyed on the invoice, the session being replaced and the amount,
            // so a double click shares a key and Stripe returns one session.
            'idempotency_key' => "invoice-{$invoice->id}-checkout-".($invoice->stripe_session_id ?? 'initial')."-{$amountMinor}",
        ]);

        $this->attachSession($payment, $session->id);
        $invoice->update(['stripe_session_id' => $session->id]);

        return $session->url;
    }

    /**
     * Record which Checkout Session a payment went to.
     *
     * A double click reaches here twice with two payments but, through the
     * idempotency key, one session. The second payment then closes as
     * Expired instead of claiming a session that already has an owner.
     */
    private function attachSession(Payment $payment, string $sessionId): void
    {
        $owner = Payment::query()->where('provider_session_id', $sessionId)->first();

        if ($owner && ! $owner->is($payment)) {
            $payment->advanceTo(PaymentState::Expired, 'stripe:checkout', "Duplicate request; Checkout Session {$sessionId} belongs to {$owner->transaction_id}");

            return;
        }

        $payment->update(['provider_session_id' => $sessionId]);
    }

    /**
     * Create a Stripe Checkout Session for a donation.
     *
     * No Donation row exists until Stripe confirms the money: everything
     * needed to write it travels in the session metadata, and
     * settleDonationSession() creates it. So an abandoned checkout leaves
     * nothing behind to be counted towards a campaign's total. The payment
     * attempt itself is on record from the start, in Created.
     *
     * @param  array{donor_name: string, is_anonymous: bool, is_recurring: bool, frequency: ?string}  $details
     */
    public function createDonationCheckoutSession(
        Fundraiser $fundraiser,
        User $donor,
        int $amountMinor,
        array $details,
        string $successUrl,
        string $cancelUrl,
    ): string {
        if (! $this->isLive()) {
            throw new RuntimeException('Stripe is not configured; refusing to simulate a checkout session.');
        }

        $payment = $this->orchestrator()->startPayment([
            'user' => $donor,
            'channel' => 'card',
            'fundraiser' => $fundraiser,
            'amount_minor' => $amountMinor,
            'currency' => $fundraiser->goal_currency,
        ]);

        Stripe::setApiKey($this->secretKey);

        $session = Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => strtolower($fundraiser->goal_currency),
                    'product_data' => [
                        'name' => "Donation — {$fundraiser->title}",
                        'description' => "CommunityHub payment {$payment->transaction_id}",
                    ],
                    'unit_amount' => $amountMinor,
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'customer_email' => $donor->email,
            'client_reference_id' => "donation-{$fundraiser->id}-{$donor->id}",
            'metadata' => [
                'purpose' => 'donation',
                'payment_id' => (string) $payment->id,
                'communityhub_transaction_id' => $payment->transaction_id,
                'fundraiser_id' => (string) $fundraiser->id,
                'user_id' => (string) $donor->id,
                'donor_name' => Str::limit($details['donor_name'], 120, ''),
                'is_anonymous' => $details['is_anonymous'] ? '1' : '0',
                'is_recurring' => $details['is_recurring'] ? '1' : '0',
                'frequency' => (string) ($details['frequency'] ?? ''),
            ],
            'payment_intent_data' => ['metadata' => [
                'purpose' => 'donation',
                'payment_id' => (string) $payment->id,
                'fundraiser_id' => (string) $fundraiser->id,
            ]],
            'success_url' => $successUrl.'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $cancelUrl,
        ]);

        $this->attachSession($payment, $session->id);

        return $session->url;
    }

    /**
     * Verify a returning donor's session with Stripe and record the donation.
     *
     * Like completePayment(), the session id comes from the browser, so the
     * session must be a donation by this user to this campaign.
     */
    public function completeDonation(Fundraiser $fundraiser, User $donor, string $sessionId): bool
    {
        if (! $this->isLive()) {
            return false;
        }

        Stripe::setApiKey($this->secretKey);
        $session = Session::retrieve($sessionId);

        $metadata = $session->metadata;

        if (($metadata->purpose ?? null) !== 'donation'
            || (string) ($metadata->fundraiser_id ?? '') !== (string) $fundraiser->id
            || (string) ($metadata->user_id ?? '') !== (string) $donor->id) {
            Log::channel('security')->warning('Stripe session presented for a donation it does not describe', [
                'fundraiser_id' => $fundraiser->id,
                'user_id' => $donor->id,
                'session_id' => $sessionId,
            ]);

            return false;
        }

        return in_array($this->settleSession($session), ['settled', 'duplicate'], true);
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
        $this->eventSource = "stripe:{$event->id}";

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
                'payment_intent.requires_action' => $this->handleIntentProgress($object, PaymentState::RequiresAction),
                'payment_intent.processing' => $this->handleIntentProgress($object, PaymentState::Processing),
                'payment_intent.payment_failed',
                'checkout.session.async_payment_failed',
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
            // Delayed methods complete the session before the money arrives.
            $this->paymentFor($session)?->advanceTo(PaymentState::Processing, $this->source(), 'Checkout completed; payment still processing');

            return 'unpaid';
        }

        if (($session->metadata->purpose ?? null) === 'donation') {
            return $this->settleDonationSession($session);
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

        return $this->settleInvoicePayment(
            $invoice,
            $this->idOf($session->payment_intent),
            (int) $session->amount_total,
            0,
            $session,
        );
    }

    /**
     * Stripe has an invoice payment's money: record it, and apply it unless
     * the invoice was already settled some other way. Idempotent.
     *
     * The payment becomes Succeeded, then Paid once applied. Money for an
     * invoice already Paid or Waived is still recorded on the ledger — it was
     * received — but the payment stays Succeeded, unapplied, for a refund.
     */
    private function settleInvoicePayment(Invoice $invoice, string $paymentIntent, int $amountMinor, int $feeMinor, ?StripeObject $session = null): string
    {
        return DB::transaction(function () use ($invoice, $paymentIntent, $amountMinor, $feeMinor, $session): string {
            // Serialises concurrent settlement of the same invoice — the
            // redirect and the webhook routinely arrive within a second.
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if (Transaction::query()->where('reference', $this->paymentReference($paymentIntent))->exists()) {
                return 'duplicate';
            }

            $payment = $this->paymentFor($session ?? $paymentIntent)
                ?? $this->orchestrator()->startPayment([
                    'user' => $invoice->user,
                    'channel' => 'card',
                    'invoice' => $invoice,
                    'amount_minor' => $amountMinor,
                    'currency' => $invoice->currency,
                    'source' => $this->source(),
                ]);

            $payment->update(array_filter([
                'provider_session_id' => $session?->id ?? $payment->provider_session_id,
                'provider_payment_id' => $paymentIntent,
                'amount_minor' => $amountMinor,
            ]));

            $accepted = $payment->advanceTo(PaymentState::Succeeded, $this->source(), 'Stripe confirmed the payment');

            $ledger = [
                'reference' => $this->paymentReference($paymentIntent),
                'provider_reference' => $paymentIntent,
                'provider_status' => 'succeeded',
                'amount_minor' => $amountMinor,
                'fee_minor' => $feeMinor,
                'net_amount_minor' => $amountMinor - $feeMinor,
                'notes' => $session ? "Stripe Checkout {$session->id}" : "Stripe PaymentIntent {$paymentIntent}",
            ];

            // Money arrived that cannot be applied: for an invoice already
            // settled, or for a payment already closed. It is recorded, because
            // it was received, and left unapplied for an administrator to
            // refund; throwing here would only make Stripe retry for days.
            if (! $accepted || in_array($invoice->status, ['Paid', 'Waived'], true)) {
                $row = $this->orchestrator()->recordLedgerPayment($payment, $ledger);
                app(LedgerService::class)->postTransaction($row);

                Log::warning('Stripe payment received that cannot be applied; refund it from the ledger', [
                    'invoice' => $invoice->reference,
                    'transaction_id' => $payment->transaction_id,
                    'payment_state' => $payment->state->value,
                    'payment_intent' => $paymentIntent,
                    'amount_minor' => $amountMinor,
                ]);

                return $accepted ? 'overpaid' : 'unapplied';
            }

            $invoice->forceFill(array_filter([
                'stripe_session_id' => $session?->id,
                'stripe_payment_intent' => $paymentIntent,
            ]))->save();

            $this->orchestrator()->applyPayment($payment, $ledger, null, $this->source());

            Log::info("Invoice {$invoice->reference} paid via Stripe ({$paymentIntent}), payment {$payment->transaction_id}");

            return 'settled';
        });
    }

    /**
     * Record a paid donation Checkout Session. Idempotent.
     *
     * The Donation and its ledger row are written together, keyed on the
     * PaymentIntent's ledger reference, so the success redirect and the
     * webhook — whichever arrives second — cannot record the gift twice.
     */
    private function settleDonationSession(StripeObject $session): string
    {
        $metadata = $session->metadata;
        $fundraiser = Fundraiser::find((int) ($metadata->fundraiser_id ?? 0));

        if (! $fundraiser) {
            Log::warning('Stripe donation checkout completed for a campaign that does not exist', [
                'session_id' => $session->id,
                'fundraiser_id' => $metadata->fundraiser_id ?? null,
            ]);

            return 'unknown_fundraiser';
        }

        if (strtoupper((string) $session->currency) !== strtoupper($fundraiser->goal_currency)) {
            Log::channel('security')->warning('Stripe donation currency does not match its campaign', [
                'fundraiser_id' => $fundraiser->id,
                'session_id' => $session->id,
                'session_currency' => $session->currency,
            ]);

            return 'mismatch';
        }

        $paymentIntent = $this->idOf($session->payment_intent);
        $amountMinor = (int) $session->amount_total;
        $donor = User::find((int) ($metadata->user_id ?? 0));
        $isAnonymous = ($metadata->is_anonymous ?? '0') === '1';
        $isRecurring = ($metadata->is_recurring ?? '0') === '1';
        $donorName = (string) ($metadata->donor_name ?? '');

        return DB::transaction(function () use ($fundraiser, $session, $paymentIntent, $amountMinor, $donor, $isAnonymous, $isRecurring, $donorName, $metadata): string {
            if (Transaction::query()->where('reference', $this->paymentReference($paymentIntent))->exists()) {
                return 'duplicate';
            }

            $payment = $this->paymentFor($session)
                ?? $this->orchestrator()->startPayment([
                    'user' => $donor,
                    'channel' => 'card',
                    'fundraiser' => $fundraiser,
                    'amount_minor' => $amountMinor,
                    'currency' => $fundraiser->goal_currency,
                    'source' => $this->source(),
                ]);

            $donation = $fundraiser->donations()->create([
                'user_id' => $donor?->id,
                'amount_minor' => $amountMinor,
                'currency' => strtoupper($fundraiser->goal_currency),
                'donor_name' => $donorName ?: null,
                'is_anonymous' => $isAnonymous,
                'is_recurring' => $isRecurring,
                'frequency' => $isRecurring ? (($metadata->frequency ?? '') ?: 'monthly') : null,
                // Completed by applyPayment(), in the same transaction.
                'status' => 'pending',
                'receipt_number' => 'DON-REC-'.date('Ymd').'-'.strtoupper(Str::random(5)),
                'payment_channel' => self::CHANNEL,
                'donated_at' => now(),
                'notes' => "Stripe PaymentIntent {$paymentIntent}; payment {$payment->transaction_id}",
            ]);

            $payment->update([
                'provider_session_id' => $session->id,
                'provider_payment_id' => $paymentIntent,
                'amount_minor' => $amountMinor,
                'donation_id' => $donation->id,
            ]);

            $payment->advanceTo(PaymentState::Succeeded, $this->source(), 'Stripe confirmed the donation');

            $this->orchestrator()->applyPayment($payment, [
                'reference' => $this->paymentReference($paymentIntent),
                'provider_reference' => $paymentIntent,
                'provider_status' => 'succeeded',
                'notes' => "Donation to {$fundraiser->title} by ".($isAnonymous ? 'Anonymous' : $donorName)." (Stripe Checkout {$session->id})",
            ], null, $this->source());

            Log::info("Donation to fundraiser {$fundraiser->id} received via Stripe ({$session->id}), payment {$payment->transaction_id}");

            return 'settled';
        });
    }

    /**
     * Settle an invoice when a PaymentIntent succeeds directly. Idempotent.
     */
    public function settlePaymentIntent(StripeObject $paymentIntent): string
    {
        $piId = $paymentIntent->id;

        // Donations settle from their Checkout Session, which carries the
        // donor details; the PaymentIntent only moves the payment along.
        if (($paymentIntent->metadata->purpose ?? null) === 'donation') {
            if ($paymentIntent->status === 'succeeded') {
                $this->paymentFor($paymentIntent)?->advanceTo(PaymentState::Succeeded, $this->source(), 'Stripe confirmed the donation', ['provider_payment_id' => $piId]);
            }

            return 'ignored';
        }

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

        return $this->settleInvoicePayment(
            $invoice,
            $piId,
            (int) $paymentIntent->amount,
            (int) ($paymentIntent->application_fee_amount ?? 0),
        );
    }

    /**
     * A payment needs the cardholder (3-D Secure) or is processing.
     * Only moves the payment; nothing is recorded on the ledger.
     */
    public function handleIntentProgress(StripeObject $paymentIntent, PaymentState $state): string
    {
        $payment = $this->paymentFor($paymentIntent);

        if (! $payment) {
            return 'ignored';
        }

        return $payment->advanceTo($state, $this->source(), null, ['provider_payment_id' => $paymentIntent->id])
            ? $state->value
            : 'stale';
    }

    /**
     * Record a payment failure event on the ledger and audit trail. Idempotent.
     *
     * The payment becomes Failed — from which a card declined on the Checkout
     * page may still be retried and succeed — or Expired when the session
     * itself closed. The `stripe-failed:` ledger row is an audit note, not
     * money; no total counts it.
     */
    public function handlePaymentFailure(StripeObject $object, string $eventType): string
    {
        $id = $object->id;
        $invoiceId = $object->metadata->invoice_id ?? ($object->client_reference_id ?? null);

        $invoice = $invoiceId && ctype_digit((string) $invoiceId) ? Invoice::find((int) $invoiceId) : null;
        if (! $invoice && isset($object->payment_intent)) {
            $piId = $this->idOf($object->payment_intent);
            if ($piId) {
                $invoice = Invoice::where('stripe_payment_intent', $piId)->first();
            }
        }

        $errorMessage = $object->last_payment_error->message
            ?? ($object->cancellation_reason ?? 'Payment failed or expired at processor.');

        $payment = $this->paymentFor($object);
        $payment?->advanceTo(
            $eventType === 'checkout.session.expired' ? PaymentState::Expired : PaymentState::Failed,
            $this->source(),
            $errorMessage,
            ['failure_reason' => $errorMessage],
        );

        $amountMinor = (int) ($object->amount ?? ($object->amount_total ?? ($invoice?->amount_minor ?? 0)));

        if ($invoice) {
            Transaction::query()->firstOrCreate(
                ['reference' => "stripe-failed:{$id}"],
                [
                    'payment_id' => $payment?->id,
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
     * The payment a Stripe object belongs to: by the payment id we put in its
     * metadata, then by its Checkout Session, then by its PaymentIntent.
     * Accepts a Checkout Session, a PaymentIntent or a PaymentIntent id.
     */
    private function paymentFor(StripeObject|string|null $object): ?Payment
    {
        if ($object === null) {
            return null;
        }

        if (is_string($object)) {
            return Payment::query()->where('provider_payment_id', $object)->first();
        }

        $paymentId = $object->metadata->payment_id ?? null;
        if ($paymentId && $payment = Payment::find((int) $paymentId)) {
            return $payment;
        }

        $id = (string) $object->id;

        if (str_starts_with($id, 'cs_') && $payment = Payment::query()->where('provider_session_id', $id)->first()) {
            return $payment;
        }

        $paymentIntent = str_starts_with($id, 'pi_') ? $id : $this->idOf($object->payment_intent ?? null);

        return $paymentIntent ? Payment::query()->where('provider_payment_id', $paymentIntent)->first() : null;
    }

    /** What caused a state change, for the payment's history. */
    private function source(): string
    {
        return $this->eventSource ?? 'stripe:return';
    }

    private function orchestrator(): PaymentOrchestratorService
    {
        return app(PaymentOrchestratorService::class);
    }

    /**
     * Process chargebacks and dispute lifecycle events. Idempotent.
     *
     * The dispute is tied to its payment through the PaymentIntent, which is
     * what the ledger reference is built from. It used to be matched with
     * `notes LIKE %charge_id%`; no note ever held a charge id, and when the
     * dispute's charge was absent the empty filter matched the first Stripe
     * payment in the table, flagging an unrelated resident's invoice.
     *
     * Ledger rows record what the money did, without disturbing the sums the
     * dashboards draw from `completed` and `refunded` rows:
     *
     *   - opened:  a `disputed` marker, net negative, while the funds are held;
     *   - won:     a `reinstated` marker that offsets it. It is not `completed`,
     *              because the original payment already is, and counting it
     *              again doubled the amount collected;
     *   - lost:    a `refunded` row, because the cardholder has the money back,
     *              so the invoice's received total and the refund totals agree.
     */
    public function handleDispute(StripeObject $dispute, string $eventType): string
    {
        $disputeId = $dispute->id;
        $amountMinor = (int) $dispute->amount;
        $currency = strtoupper((string) ($dispute->currency ?? ''));
        $invoice = $this->invoiceForDispute($dispute);

        if (! $invoice) {
            Log::warning('Stripe dispute for a payment this application did not record', [
                'dispute' => $disputeId,
                'payment_intent' => $this->idOf($dispute->payment_intent ?? null),
            ]);

            return 'unknown_invoice';
        }

        $record = fn (string $step, string $status, int $netMinor, string $notes): Transaction => Transaction::query()->firstOrCreate(
            ['reference' => "stripe-dispute:{$disputeId}:{$step}"],
            [
                'user_id' => $invoice->user_id,
                'invoice_id' => $invoice->id,
                'amount_minor' => $amountMinor,
                'fee_minor' => 0,
                'net_amount_minor' => $netMinor,
                'currency' => $currency ?: strtoupper($invoice->currency),
                'payment_channel' => self::CHANNEL,
                'provider' => 'stripe',
                'status' => $status,
                'dispute_reason' => $dispute->reason ?? 'general',
                'notes' => $notes,
            ],
        );

        $ledgerPayment = $this->ledgerPaymentFor($this->idOf($dispute->payment_intent ?? null));
        $payment = $ledgerPayment?->payment;

        if (in_array($eventType, ['charge.dispute.created', 'charge.dispute.funds_withdrawn'], true)) {
            $record('created', 'disputed', -$amountMinor, "Chargeback/dispute opened ({$dispute->reason}). Status: {$dispute->status}");
            $payment?->advanceTo(PaymentState::Disputed, $this->source(), "Dispute {$disputeId} opened ({$dispute->reason})");

            $invoice->update(['status' => 'Disputed']);

            Log::channel('security')->alert('Stripe chargeback/dispute created for invoice', [
                'invoice' => $invoice->reference,
                'dispute_id' => $disputeId,
                'amount' => $amountMinor,
                'reason' => $dispute->reason ?? 'unknown',
            ]);

            return 'disputed';
        }

        if ($eventType !== 'charge.dispute.closed') {
            return 'disputed';
        }

        if ($dispute->status === 'won') {
            $record('won', 'reinstated', $amountMinor, 'Dispute won in estate favor. Funds reinstated.');
            $payment?->advanceTo(PaymentState::Paid, $this->source(), "Dispute {$disputeId} won");

            if ($invoice->status === 'Disputed') {
                $invoice->markPaid();
            }

            return 'dispute_won';
        }

        $chargeback = $record('lost', 'refunded', -$amountMinor, 'Dispute lost. Funds returned to cardholder.');
        $payment?->advanceTo(PaymentState::ChargedBack, $this->source(), "Dispute {$disputeId} lost");

        if ($ledgerPayment) {
            $chargeback->update(['payment_id' => $ledgerPayment->payment_id]);
            app(LedgerService::class)->postReversal($ledgerPayment, $chargeback);
        }

        $invoice->update(['status' => 'Unpaid', 'paid_at' => null]);

        return 'dispute_lost';
    }

    /** The invoice a dispute's payment settled, found through its PaymentIntent. */
    private function invoiceForDispute(StripeObject $dispute): ?Invoice
    {
        $paymentIntent = $this->idOf($dispute->payment_intent ?? null);

        if ($paymentIntent) {
            $invoiceId = Transaction::query()
                ->where('reference', $this->paymentReference($paymentIntent))
                ->value('invoice_id')
                ?? Invoice::query()->where('stripe_payment_intent', $paymentIntent)->value('id');

            if ($invoiceId) {
                return Invoice::find($invoiceId);
            }
        }

        $invoiceId = $dispute->metadata->invoice_id ?? null;

        return $invoiceId ? Invoice::find((int) $invoiceId) : null;
    }

    /**
     * Mark the payments a payout carried to the bank. Idempotent.
     *
     * This used to stamp the payout id on every unmarked Stripe payment in the
     * ledger, for `payout.failed` as well as `payout.paid`, so a failed payout
     * recorded money as banked and one payout claimed payments it never held.
     *
     * A paid payout now asks Stripe which charges it contained and marks only
     * those, recording Stripe's fee where it is in the payment's own currency.
     * A failed payout changes nothing and raises an alert: the funds are back
     * in the Stripe balance and go out with a later payout.
     */
    public function handlePayout(StripeObject $payout, string $eventType): string
    {
        if ($eventType === 'payout.failed') {
            Log::channel('security')->alert('Stripe payout failed; the ledger was left unchanged', [
                'payout' => $payout->id,
                'amount' => $payout->amount ?? null,
                'failure_code' => $payout->failure_code ?? null,
                'failure_message' => $payout->failure_message ?? null,
            ]);

            return 'payout_failed';
        }

        foreach ($this->payoutCharges($payout->id) as $paymentIntent => $charge) {
            $payment = Transaction::query()
                ->where('reference', $this->paymentReference($paymentIntent))
                ->whereNull('payout_reference')
                ->first();

            if (! $payment) {
                continue;
            }

            $feeMinor = strtoupper($charge['currency']) === strtoupper($payment->currency)
                ? $charge['fee_minor']
                : (int) $payment->fee_minor;

            $payment->update([
                'payout_reference' => $payout->id,
                'fee_minor' => $feeMinor,
                'net_amount_minor' => $payment->amount_minor - $feeMinor,
                'settled_at' => now(),
            ]);
        }

        return 'payout_settled';
    }

    /**
     * The card payments a payout contained, from its balance transactions.
     *
     * Stripe lists these only for automatic payouts; a manual payout returns
     * none and leaves the ledger as it was.
     *
     * @return array<string, array{fee_minor: int, currency: string}> keyed by PaymentIntent id
     */
    protected function payoutCharges(string $payoutId): array
    {
        Stripe::setApiKey($this->secretKey);

        $charges = [];

        $balanceTransactions = BalanceTransaction::all([
            'payout' => $payoutId,
            'limit' => 100,
            'expand' => ['data.source'],
        ]);

        foreach ($balanceTransactions->autoPagingIterator() as $balanceTransaction) {
            if (! in_array($balanceTransaction->type, ['charge', 'payment'], true)
                || ! $balanceTransaction->source instanceof StripeObject) {
                continue;
            }

            $paymentIntent = $this->idOf($balanceTransaction->source->payment_intent ?? null);

            if ($paymentIntent) {
                $charges[$paymentIntent] = [
                    'fee_minor' => (int) $balanceTransaction->fee,
                    'currency' => (string) $balanceTransaction->currency,
                ];
            }
        }

        return $charges;
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

        $ledgerPayment = $this->ledgerPaymentFor($paymentIntent);

        if (! $ledgerPayment) {
            Log::warning('Stripe refund for a payment this application did not record', [
                'charge' => $charge->id,
                'payment_intent' => $paymentIntent,
            ]);

            return 'unknown_invoice';
        }

        return DB::transaction(function () use ($charge, $paymentIntent, $ledgerPayment): string {
            $invoice = $ledgerPayment->invoice_id ? Invoice::query()->lockForUpdate()->find($ledgerPayment->invoice_id) : null;

            $totalRefunded = (int) $charge->amount_refunded;
            $delta = $totalRefunded - $this->refundedMinor($paymentIntent);

            if ($delta <= 0) {
                return 'duplicate';
            }

            $refundRow = Transaction::create([
                'payment_id' => $ledgerPayment->payment_id,
                'user_id' => $ledgerPayment->user_id,
                'invoice_id' => $ledgerPayment->invoice_id,
                'fundraiser_id' => $ledgerPayment->fundraiser_id,
                'donation_id' => $ledgerPayment->donation_id,
                'purpose' => $ledgerPayment->purpose,
                'amount_minor' => $delta,
                'fee_minor' => 0,
                'net_amount_minor' => $delta * -1,
                'settled_at' => now(),
                'refunded_at' => now(),
                'currency' => strtoupper((string) $charge->currency),
                'payment_channel' => self::CHANNEL,
                'provider' => 'stripe',
                'provider_reference' => $paymentIntent,
                // One row per step in the cumulative total, so the same step
                // can never be written twice.
                'reference' => $this->refundReference($paymentIntent, $totalRefunded),
                'status' => 'refunded',
                'notes' => "Stripe refund on charge {$charge->id}",
            ]);

            app(LedgerService::class)->postReversal($ledgerPayment, $refundRow);

            $fullyRefunded = $totalRefunded >= $ledgerPayment->amount_minor;

            $ledgerPayment->payment?->advanceTo(
                $fullyRefunded ? PaymentState::Refunded : PaymentState::PartiallyRefunded,
                $this->source(),
                "Stripe refunded {$totalRefunded} of {$ledgerPayment->amount_minor} minor units",
            );

            if ($fullyRefunded && $ledgerPayment->donation_id) {
                $ledgerPayment->donation?->update(['status' => 'refunded', 'refunded_at' => now()]);
            }

            if ($invoice
                && $invoice->stripe_payment_intent === $paymentIntent
                && in_array($invoice->status, ['Paid', 'Partially Paid'], true)) {
                $netMinor = (int) $charge->amount - $totalRefunded;

                if ($netMinor <= 0) {
                    $invoice->update(['status' => 'Unpaid', 'paid_at' => null]);
                    $invoice->items()->update(['status' => 'Unpaid']);
                } elseif ($netMinor < $invoice->amount_minor) {
                    $invoice->update(['status' => 'Partially Paid']);
                }
            }

            Log::info("Stripe refund of {$delta} minor units recorded against {$ledgerPayment->transaction_id}");

            return 'refunded';
        });
    }

    /** The completed ledger row a PaymentIntent's money was recorded on. */
    private function ledgerPaymentFor(?string $paymentIntent): ?Transaction
    {
        return $paymentIntent
            ? Transaction::query()->where('reference', $this->paymentReference($paymentIntent))->first()
            : null;
    }

    /**
     * How much of a recorded Stripe payment can still be refunded, in minor
     * units. Zero for anything that is not a completed Stripe payment.
     */
    public function refundableMinor(Transaction $payment): int
    {
        $paymentIntent = $this->paymentIntentOf($payment);

        if ($paymentIntent === null || $payment->status !== 'completed') {
            return 0;
        }

        return max(0, $payment->amount_minor - $this->refundedMinor($paymentIntent));
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
    private function refundedMinor(string $paymentIntent): int
    {
        return (int) Transaction::query()
            ->where('status', 'refunded')
            ->where('reference', 'like', $this->refundReference($paymentIntent, '').'%')
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
