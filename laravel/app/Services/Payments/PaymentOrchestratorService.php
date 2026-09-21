<?php

namespace App\Services\Payments;

use App\Models\Invoice;
use App\Models\PaymentChannelSetting;
use App\Models\PaymentLink;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Payments\Drivers\BankTransferDriver;
use App\Services\Payments\Drivers\CashDeskDriver;
use App\Services\Payments\Drivers\CommunityWalletDriver;
use App\Services\Payments\Drivers\DigitalWalletDriver;
use App\Services\Payments\Drivers\NfcTerminalDriver;
use App\Services\Payments\Drivers\PaymentDriverInterface;
use App\Services\Payments\Drivers\PeerPaymentDriver;
use App\Services\Payments\Drivers\QrPaymentDriver;
use App\Services\Payments\Drivers\StripeCardDriver;
use App\Services\StripePaymentService;
use InvalidArgumentException;

class PaymentOrchestratorService
{
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

            if ($enabled) {
                $channels[] = [
                    'key' => $key,
                    'label' => $setting?->display_label ?? $driver->label(),
                    'instructions' => $setting?->instructions,
                    'account_identifier' => $setting?->account_identifier,
                    'fee_surcharge_percent' => (float) ($setting?->fee_surcharge_percent ?? 0),
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
}
