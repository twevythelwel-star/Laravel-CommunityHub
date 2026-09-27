<?php

namespace App\Services\Payments;

use App\Enums\PaymentState;
use App\Exceptions\IllegalPaymentTransition;
use App\Models\BankReconciliation;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\PaymentChannelSetting;
use App\Models\PaymentLink;
use App\Models\PaymentMethod;
use App\Models\PaymentReceipt;
use App\Models\PaymentTerminal;
use App\Models\Transaction;
use App\Models\TransactionEvent;
use App\Models\User;
use App\Services\Ledger\LedgerService;
use App\Services\Payments\Drivers\BankTransferDriver;
use App\Services\Payments\Drivers\CashDeskDriver;
use App\Services\Payments\Drivers\CommunityWalletDriver;
use App\Services\Payments\Drivers\DigitalWalletDriver;
use App\Services\Payments\Drivers\NfcTerminalDriver;
use App\Services\Payments\Drivers\PaymentDriverInterface;
use App\Services\Payments\Drivers\PeerPaymentDriver;
use App\Services\Payments\Drivers\QrPaymentDriver;
use App\Services\Payments\Drivers\StripeCardDriver;
use App\Services\Payments\Providers\ProviderRegistry;
use App\Services\SmsService;
use App\Services\StripePaymentService;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class PaymentOrchestratorService
{
    /**
     * Channels whose money the app itself can see move.
     *
     * The wallet debits a balance held in this database. Card and device
     * wallets (Apple/Google/Samsung Pay) are taken by the card processor and
     * confirmed by it. Every other channel — bank wire, cash, QR, NFC, Zelle,
     * Cash App —
     * has a driver that cannot see the money, and used to report success for
     * any amount anyone typed. Those payments are recorded as pending and
     * count only once an administrator confirms the money arrived.
     */
    private const SELF_VERIFYING_CHANNELS = ['wallet', 'card', 'apple_pay', 'google_pay', 'samsung_wallet', 'nfc_pos'];

    /** @var array<string, PaymentDriverInterface> */
    protected array $drivers = [];

    public function __construct(
        protected StripePaymentService $stripeService
    ) {
        $this->registerDriver(new StripeCardDriver($this->stripeService));
        $this->registerDriver(new DigitalWalletDriver('apple_pay'));
        $this->registerDriver(new DigitalWalletDriver('google_pay'));
        $this->registerDriver(new DigitalWalletDriver('samsung_wallet'));
        $this->registerDriver(new BankTransferDriver);
        $this->registerDriver(new PeerPaymentDriver('zelle'));
        $this->registerDriver(new PeerPaymentDriver('cash_app'));
        $this->registerDriver(new QrPaymentDriver);
        $this->registerDriver(new NfcTerminalDriver);
        $this->registerDriver(new CashDeskDriver);
        $this->registerDriver(new CommunityWalletDriver);
    }

    public function registerDriver(PaymentDriverInterface $driver): self
    {
        $this->drivers[$driver->key()] = $driver;

        return $this;
    }

    /**
     * Keys of every registered driver, for validating a submitted channel.
     *
     * @return array<int, string>
     */
    public function channelKeys(): array
    {
        return array_keys($this->drivers);
    }

    public function getDriver(string $key): PaymentDriverInterface
    {
        if (! isset($this->drivers[$key])) {
            throw new InvalidArgumentException("Payment channel [{$key}] is not supported by the orchestrator.");
        }

        return $this->drivers[$key];
    }

    /** Whether a payment by this channel waits for an administrator to confirm it. */
    public function requiresOfficeConfirmation(string $channel): bool
    {
        return ! in_array($channel, self::SELF_VERIFYING_CHANNELS, true);
    }

    /**
     * Begin a payment, in Created unless told otherwise, with its CH- number.
     *
     * Nothing is written to the ledger: the ledger records money, and none
     * has moved. Recognised keys: user, applies_to, purpose, channel,
     * amount_minor, currency, invoice, fundraiser, donation, payment_link,
     * item_ids, device_identifier, metadata, state, source.
     *
     * @param  array<string, mixed>  $params
     */
    public function startPayment(array $params): Payment
    {
        $user = $params['user'] ?? null;
        $channel = $params['channel'] ?? 'card';
        $invoice = $params['invoice'] ?? null;
        $fundraiser = $params['fundraiser'] ?? null;

        $appliesTo = $params['applies_to'] ?? match (true) {
            $invoice !== null => 'invoice',
            $fundraiser !== null => 'donation',
            default => 'payment_link',
        };

        // The provider that will actually take this channel: the card
        // processor for card and device wallets, the office otherwise.
        $provider = app(ProviderRegistry::class)->forChannel($channel)?->key()
            ?? Transaction::resolveDefaultProvider($channel);

        return Payment::start($params['state'] ?? PaymentState::Created, [
            'applies_to' => $appliesTo,
            'purpose' => $params['purpose'] ?? ($appliesTo === 'donation' ? Transaction::PURPOSE_FUNDRAISING_DONATION : Transaction::PURPOSE_HOA_ASSESSMENT),
            'channel' => $channel,
            'provider' => $provider,
            'user_id' => $user?->id,
            'invoice_id' => $invoice?->id,
            'fundraiser_id' => $fundraiser?->id,
            'donation_id' => ($params['donation'] ?? null)?->id,
            'payment_link_id' => ($params['payment_link'] ?? null)?->id,
            'payment_method_id' => $user instanceof User ? $this->paymentMethodFor($user, $channel, $provider)->id : null,
            'amount_minor' => (int) $params['amount_minor'],
            'currency' => strtoupper($params['currency'] ?? $invoice?->currency ?? $fundraiser?->goal_currency ?? 'JMD'),
            'invoice_item_ids' => ($params['item_ids'] ?? []) ?: null,
            'device_identifier' => $params['device_identifier'] ?? null,
            'metadata' => $params['metadata'] ?? null,
        ], $user instanceof User ? $user : null, $params['source'] ?? 'resident');
    }

    /** The payer's saved method for a channel, from the registry. */
    private function paymentMethodFor(User $user, string $channel, string $provider): PaymentMethod
    {
        $methodType = match ($channel) {
            'card', 'stripe_card' => 'card',
            'apple_pay', 'google_pay', 'samsung_wallet' => 'wallet',
            'bank_wire' => 'bank_transfer',
            'nfc_pos' => 'card_present',
            'zelle', 'cash_app' => 'peer_transfer',
            'wallet' => 'community_wallet',
            default => 'other',
        };

        return PaymentMethod::resolveForUser($user, $methodType, $provider, [
            'wallet_type' => in_array($channel, ['apple_pay', 'google_pay', 'samsung_wallet'], true) ? $channel : null,
            'display_name' => Transaction::formatPaymentMethod($channel),
        ]);
    }

    /** An office channel was chosen: the payer now has to send the money. */
    public function awaitTransfer(Payment $payment, ?User $payer = null, ?string $payerReference = null): Payment
    {
        return $payment->transitionTo(PaymentState::AwaitingTransfer, $payer, 'resident', null, [
            'payer_reference' => $payerReference,
        ]);
    }

    /**
     * An administrator logs that an office payment's money has arrived.
     *
     * This does not settle anything. A second administrator verifies it in a
     * bank reconciliation (verifyReceived), and only then is it Paid.
     *
     * @throws IllegalPaymentTransition unless the payment is awaiting transfer
     */
    public function markReceived(Payment $payment, User $admin, ?string $bankReference = null, ?string $note = null): Payment
    {
        return DB::transaction(function () use ($payment, $admin, $bankReference, $note): Payment {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            $payment->transitionTo(PaymentState::Received, $admin, 'admin', $note, [
                'received_by' => $admin->id,
                'received_at' => now(),
                'bank_reference' => $bankReference,
            ]);

            $admin->recordActivity("Logged {$payment->channel} payment {$payment->transaction_id} as received");

            return $payment;
        });
    }

    /**
     * A second administrator verifies a received payment against a bank
     * statement, and it is applied.
     *
     * Separation of duties: whoever logged the money as received may not be
     * the one who verifies it, so no single administrator can settle a debt
     * on their own word.
     *
     * @throws DomainException when the verifier is the one who logged receipt
     * @throws IllegalPaymentTransition unless the payment is Received
     */
    public function verifyReceived(Payment $payment, User $admin, BankReconciliation $reconciliation): Payment
    {
        return DB::transaction(function () use ($payment, $admin, $reconciliation): Payment {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($payment->received_by === $admin->id) {
                throw new DomainException("Payment {$payment->transaction_id} was logged as received by you; a different administrator must verify it.");
            }

            $payment->transitionTo(PaymentState::Verified, $admin, 'admin', "Verified in bank reconciliation #{$reconciliation->id}", [
                'verified_by' => $admin->id,
                'verified_at' => now(),
                'bank_reconciliation_id' => $reconciliation->id,
            ]);

            $this->applyPayment($payment, [
                'reference' => $payment->payer_reference ?: $payment->transaction_id,
                // Dated when the money arrived, so the reconciliation counts it
                // against the statement it appears on.
                'settled_at' => $payment->received_at,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'notes' => "{$payment->channel} payment verified by {$admin->display_name}".($payment->bank_reference ? " (bank ref {$payment->bank_reference})" : ''),
            ], $admin, 'admin');

            $admin->recordActivity("Verified {$payment->channel} payment {$payment->transaction_id} in bank reconciliation #{$reconciliation->id}");

            return $payment;
        });
    }

    /**
     * An administrator records that an office payment never arrived.
     * Nothing it would have paid changes; a pending donation becomes rejected.
     *
     * @throws IllegalPaymentTransition unless awaiting transfer or received
     */
    public function rejectPayment(Payment $payment, User $admin, ?string $reason = null): Payment
    {
        return DB::transaction(function () use ($payment, $admin, $reason): Payment {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            $payment->transitionTo(PaymentState::Rejected, $admin, 'admin', $reason ?: 'Payment not received', [
                'failure_reason' => $reason ?: 'Payment not received',
            ]);

            // Placeholder ledger rows from before payments had their own table.
            $payment->ledgerEntries()->whereIn('status', ['pending', 'awaiting_bank_transfer', 'received', 'verified'])
                ->update(['status' => 'rejected']);

            $payment->donation?->update(['status' => 'rejected']);

            $admin->recordActivity("Rejected {$payment->channel} payment {$payment->transaction_id}".($reason ? ": {$reason}" : ''));

            return $payment;
        });
    }

    /**
     * Apply a payment whose money has arrived: write the ledger row, settle
     * what it was for, post the double-entry ledger, and move it to Paid.
     *
     * The payment must be Succeeded (a processor has the money) or Verified
     * (the office has checked it). Everything happens in one database
     * transaction; the SMS confirmation goes out only after it commits, so a
     * slow provider never holds the invoice lock and a rolled-back payment
     * is never announced.
     *
     * @param  array<string, mixed>  $ledger  attributes for the ledger row
     *
     * @throws IllegalPaymentTransition
     */
    public function applyPayment(Payment $payment, array $ledger = [], ?User $actor = null, string $source = 'system'): Transaction
    {
        return DB::transaction(function () use ($payment, $ledger, $actor, $source): Transaction {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($payment->state === PaymentState::Paid && $existing = $payment->ledgerPayment()) {
                return $existing;
            }

            if (! $payment->state->canTransitionTo(PaymentState::Paid)) {
                throw new IllegalPaymentTransition($payment, PaymentState::Paid);
            }

            $row = $this->recordLedgerPayment($payment, $ledger);

            if ($payment->invoice) {
                if ($payment->invoice_item_ids) {
                    InvoiceItem::query()
                        ->where('invoice_id', $payment->invoice_id)
                        ->whereKey($payment->invoice_item_ids)
                        ->update(['status' => 'Paid']);
                }

                $payment->invoice->settleFromLedger();
            }

            $payment->donation?->update(['status' => 'completed', 'donated_at' => now()]);

            $payment->transitionTo(PaymentState::Paid, $actor, $source, null, ['paid_at' => now()]);

            app(LedgerService::class)->postTransaction($row);

            $prefix = $payment->channel === 'cash_office' ? 'CR-' : 'REC-';
            $receiptNumber = $prefix.now()->format('Y').'-'.str_pad((string) $row->id, 5, '0', STR_PAD_LEFT);
            PaymentReceipt::query()->firstOrCreate(
                ['transaction_id' => $row->id],
                [
                    'receipt_number' => $receiptNumber,
                    'receipt_type' => $payment->channel === 'cash_office' ? 'cash_desk' : ($payment->channel === 'bank_wire' ? 'bank_transfer' : 'digital'),
                    'amount_minor' => $payment->amount_minor,
                    'currency' => $payment->currency,
                    'payer_name' => $payment->user?->name ?? 'Community Resident',
                    'payer_lot' => $payment->user?->lot ?? null,
                    'issued_by_name' => 'CommunityHub Automated Payment System',
                    'issued_at' => now(),
                ]
            );

            DB::afterCommit(fn () => $this->sendPaymentConfirmationNotification($row->fresh()));

            return $row;
        });
    }

    /**
     * The ledger row for a payment's money.
     *
     * A placeholder row written before payments had their own table is
     * promoted rather than duplicated; otherwise the row is created once,
     * keyed on its reference, so a retry cannot record the money twice.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function recordLedgerPayment(Payment $payment, array $attributes = []): Transaction
    {
        $idempotencyKey = $attributes['idempotency_key'] ?? $payment->idempotency_key ?? ('idem:'.$payment->transaction_id);
        $providerEventId = $attributes['provider_event_id'] ?? null;

        $values = [
            'payment_id' => $payment->id,
            'idempotency_key' => $idempotencyKey,
            'provider_event_id' => $providerEventId,
            'terminal_id' => $payment->terminal_id,
            'location_id' => $payment->location_id,
            'transaction_id' => $payment->transaction_id,
            'user_id' => $payment->user_id,
            'invoice_id' => $payment->invoice_id,
            'fundraiser_id' => $payment->fundraiser_id,
            'donation_id' => $payment->donation_id,
            'payment_link_id' => $payment->payment_link_id,
            'payment_method_id' => $payment->payment_method_id,
            'purpose' => $payment->purpose,
            'amount_minor' => $payment->amount_minor,
            'fee_minor' => 0,
            'net_amount_minor' => $payment->amount_minor,
            'currency' => $payment->currency,
            // `stripe_card` is the ledger channel Stripe refunds and payouts key
            // on; other card processors book as plain `card`.
            'payment_channel' => $payment->provider === 'stripe' ? StripePaymentService::CHANNEL : $payment->channel,
            'provider' => $payment->provider,
            'device_identifier' => $payment->device_identifier,
            'status' => Transaction::STATUS_COMPLETED,
            'settled_at' => now(),
            'captured_at' => now(),
            ...$attributes,
        ];

        // Idempotency check by provider_event_id or idempotency_key
        if ($providerEventId) {
            $existing = Transaction::query()->where('provider_event_id', $providerEventId)->first();
            if ($existing) {
                return $existing;
            }
        }

        if ($idempotencyKey) {
            $existing = Transaction::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }
        }

        $placeholder = $payment->ledgerEntries()
            ->whereIn('status', ['pending', 'payment_started', 'requires_action', 'awaiting_bank_transfer', 'received', 'verified'])
            ->first();

        if ($placeholder) {
            $placeholder->update($values);

            return $placeholder;
        }

        $reference = $values['reference'] ?? $payment->transaction_id;

        return Transaction::query()->firstOrCreate(['reference' => $reference], [...$values, 'reference' => $reference]);
    }

    /**
     * Send payment confirmation notification via Twilio when payment is confirmed/paid.
     */
    public function sendPaymentConfirmationNotification(Transaction $payment): void
    {
        if (! $payment->isPaid()) {
            return;
        }

        $amountFormatted = "{$payment->currency} ".number_format($payment->amount_minor / 100, 2);
        $txId = $payment->transaction_id ?? $payment->reference;
        $user = $payment->user;

        if (! $user || ! $user->phone) {
            return;
        }

        try {
            $sms = app(SmsService::class);
            if ($sms->isConfigured()) {
                if ($payment->fundraiser_id && $payment->fundraiser) {
                    $message = "CommunityHub donation received: {$amountFormatted} for {$payment->fundraiser->title}. Receipt {$txId}.";
                } else {
                    $purpose = $payment->purpose ?: 'Payment';
                    $message = "CommunityHub {$purpose} received: {$amountFormatted}. Receipt {$txId}.";
                }

                $messageSid = $sms->send($user->phone, $message);

                TransactionEvent::log(
                    $payment,
                    'notification_sent',
                    $payment->status,
                    $messageSid,
                    "Dispatched SMS confirmation for {$txId} via Twilio (SID: {$messageSid})"
                );
            }
        } catch (\Throwable $e) {
            // The payment is recorded either way; a failed text is not a failed payment.
            Log::warning("Payment confirmation SMS for {$txId} not sent: {$e->getMessage()}");
        }
    }

    /**
     * Get active channels configured for the estate.
     */
    public function getAvailableChannels(): array
    {
        $settings = PaymentChannelSetting::all()->keyBy('channel_key');

        $channels = [];
        foreach ($this->drivers as $key => $driver) {
            $setting = $settings->get($key);
            $enabled = $setting ? $setting->enabled : true;

            // Offered only when something can take it: card and device wallets
            // need a card processor that supports them (ProviderRegistry).
            $provider = app(ProviderRegistry::class)->forChannel($key);

            // Never point a payer at an account the estate has not entered.
            if (PaymentChannelSetting::requiresAccountDetails($key) && blank($setting?->account_identifier)) {
                continue;
            }

            // In-person card payments are taken by staff on a reader, never
            // chosen by a payer online.
            if ($enabled && $provider && $key !== 'nfc_pos') {
                $channels[] = [
                    'key' => $key,
                    'label' => $setting?->display_label ?? $driver->label(),
                    'instructions' => $setting?->instructions,
                    'account_identifier' => $setting?->account_identifier,
                    'fee_surcharge_percent' => (float) ($setting?->fee_surcharge_percent ?? 0),
                    'requires_confirmation' => $this->requiresOfficeConfirmation($key),
                    'provider' => $provider->label(),
                ];
            }
        }

        return $channels;
    }

    /**
     * Step 1 & 2 of Non-Custodial Pipeline: Generate payment intent.
     */
    public function createPaymentIntent(string $channel, array $params): array
    {
        $driver = $this->getDriver($channel);

        return $driver->initiate($params);
    }

    /**
     * Step 4 & 5 of Non-Custodial Pipeline: Verify/settle payment with provider.
     */
    public function settlePayment(string $channel, array $params): array
    {
        $driver = $this->getDriver($channel);

        return $driver->settle($params);
    }

    /**
     * Step 6 & 7 of Non-Custodial Pipeline: Post transaction to Master Ledger & issue receipt.
     */
    public function recordTransaction(
        User|int|null $user,
        int $amountMinor,
        string $channel,
        string $reference,
        ?Invoice $invoice = null,
        ?int $invoiceItemId = null,
        ?PaymentLink $paymentLink = null,
        ?int $fundraiserId = null,
        string $status = 'completed',
        ?string $proofUrl = null,
        ?string $notes = null,
        ?string $currency = null
    ): Transaction {
        $userId = $user instanceof User ? $user->id : $user;

        $tx = Transaction::create([
            'user_id' => $userId,
            'invoice_id' => $invoice?->id,
            'invoice_item_id' => $invoiceItemId,
            'payment_link_id' => $paymentLink?->id,
            'fundraiser_id' => $fundraiserId,
            'amount_minor' => $amountMinor,
            'currency' => $currency ?? ($invoice?->currency ?? ($paymentLink?->currency ?? 'JMD')),
            'payment_channel' => $channel,
            'reference' => $reference,
            'status' => $status,
            'proof_url' => $proofUrl,
            'notes' => $notes,
        ]);

        if ($paymentLink) {
            $paymentLink->increment('uses_count');
        }

        return $tx;
    }

    /**
     * Validate an individual payment channel's technical integration readiness.
     * Enforces strict pre-production validation before channel can be enabled.
     */
    public function validateChannelIntegration(string $channelKey): array
    {
        $setting = PaymentChannelSetting::where('channel_key', $channelKey)->first();
        $providers = app(ProviderRegistry::class);

        $checks = [];

        switch ($channelKey) {
            // Every check reads real configuration or data. Nothing is reported as
            // passed unless the code has confirmed it.
            case 'card':
                $cardProvider = $providers->cardProvider();
                $checks = [[
                    'name' => 'Card Processor',
                    'passed' => $cardProvider !== null,
                    'message' => $cardProvider ? "{$cardProvider->label()} is configured" : 'No card processor is configured. Set STRIPE_SECRET, or PAYMENT_CARD_PROVIDER with its credentials.',
                ]];
                if ($cardProvider?->key() === 'stripe') {
                    $acceptsWebhooks = app(StripePaymentService::class)->acceptsWebhooks();
                    $checks[] = [
                        'name' => 'Stripe Webhook Secret',
                        'passed' => $acceptsWebhooks,
                        'message' => $acceptsWebhooks ? 'STRIPE_WEBHOOK_SECRET is set' : 'STRIPE_WEBHOOK_SECRET is missing or not a whsec_ secret, so card payments cannot be confirmed.',
                    ];
                }
                break;

            case 'apple_pay':
            case 'google_pay':
            case 'samsung_wallet':
                $checks = [$this->walletCheck($channelKey, $providers)];
                break;

            case 'nfc_pos':
                $inPerson = $providers->inPersonProvider();
                $readers = PaymentTerminal::query()->usable()->count();
                $checks = [
                    [
                        'name' => 'In-Person Card Provider',
                        'passed' => $inPerson !== null,
                        'message' => $inPerson ? "{$inPerson->label()} is configured" : 'No in-person provider is configured. Set PAYMENT_IN_PERSON_PROVIDER with a live processor.',
                    ],
                    [
                        'name' => 'Registered Card Readers',
                        'passed' => $readers > 0,
                        'message' => $readers > 0 ? "{$readers} verified, active reader(s) registered" : 'No verified, active card reader is registered.',
                    ],
                ];
                break;

            case 'bank_wire':
                $checks = [$this->accountCheck('Designated Deposit Account', $setting)];
                break;

            case 'cash_office':
                // Nothing technical to set up: the check that matters is that cash
                // counts only once the office has confirmed it.
                $checks = [[
                    'name' => 'Office Confirmation',
                    'passed' => $this->requiresOfficeConfirmation('cash_office'),
                    'message' => 'Cash counts only after an administrator logs receipt and a second administrator verifies it.',
                ]];
                break;

            case 'cash_app':
                $checks = [$this->accountCheck('Business Cashtag', $setting)];
                break;

            case 'zelle':
                $checks = [$this->accountCheck('Zelle Recipient (email or phone)', $setting)];
                break;

            case 'qr_code':
                // Payment QR codes open /p/{token}, which is off unless public links are.
                $linksEnabled = (bool) config('payments.public_links_enabled');
                $checks = [[
                    'name' => 'Public Payment Links',
                    'passed' => $linksEnabled,
                    'message' => $linksEnabled ? 'PAYMENT_PUBLIC_LINKS_ENABLED is on, so payment QR codes open' : 'PAYMENT_PUBLIC_LINKS_ENABLED is off, so payment QR codes lead nowhere.',
                ]];
                break;

            default:
                $registered = isset($this->drivers[$channelKey]);
                $checks = [[
                    'name' => 'Payment Driver',
                    'passed' => $registered,
                    'message' => $registered ? 'A driver is registered for this channel' : 'No driver is registered for this channel.',
                ]];
                break;
        }

        // Ready only when every check passed; there is no separate override.
        $isReady = $checks !== [] && ! in_array(false, array_column($checks, 'passed'), true);

        return [
            'channel_key' => $channelKey,
            'label' => $setting?->display_label ?? ($this->drivers[$channelKey] ?? null)?->label() ?? ucfirst(str_replace('_', ' ', $channelKey)),
            'enabled' => (bool) ($setting?->enabled ?? PaymentChannelSetting::forChannel($channelKey)->enabled),
            'integration_mode' => $setting?->integration_mode ?? 'MANUAL_VERIFICATION',
            'is_ready' => $isReady,
            'status' => $isReady ? 'ready' : 'needs_configuration',
            'checks' => $checks,
            'account_identifier' => $setting?->account_identifier,
            'instructions' => $setting?->instructions,
            'validated_at' => now()->format('M d, Y h:i A'),
        ];
    }

    /**
     * Whether a card processor that offers this wallet is configured, and the
     * estate has listed the wallet in PAYMENT_WALLETS.
     */
    private function walletCheck(string $channelKey, ProviderRegistry $providers): array
    {
        $cardProvider = $providers->cardProvider();
        $walletProvider = $providers->walletProvider($channelKey);

        $message = match (true) {
            $walletProvider !== null => "Offered through {$walletProvider->label()}",
            $cardProvider === null => 'No card processor is configured.',
            ! in_array($channelKey, (array) config('payments.wallets', []), true) => 'Not listed in PAYMENT_WALLETS.',
            default => "{$cardProvider->label()} does not offer this wallet.",
        };

        return ['name' => 'Wallet Through Card Processor', 'passed' => $walletProvider !== null, 'message' => $message];
    }

    /**
     * Whether the estate has entered the account payers send money to. Only the
     * saved setting counts: there is no default account to fall back on.
     */
    private function accountCheck(string $name, ?PaymentChannelSetting $setting): array
    {
        $account = $setting?->account_identifier;

        return [
            'name' => $name,
            'passed' => filled($account),
            'message' => filled($account) ? "Payers are sent to: {$account}" : 'Not set. Enter the account payers should send money to.',
        ];
    }

    /**
     * Return comprehensive technical validation and readiness status for all 10 payment channels.
     */
    public function getChannelReadinessReport(): array
    {
        $keys = [
            'card',
            'apple_pay',
            'google_pay',
            'samsung_wallet',
            'nfc_pos',
            'bank_wire',
            'cash_office',
            'cash_app',
            'zelle',
            'qr_code',
        ];

        $report = [];
        foreach ($keys as $key) {
            $report[] = $this->validateChannelIntegration($key);
        }

        return $report;
    }
}
