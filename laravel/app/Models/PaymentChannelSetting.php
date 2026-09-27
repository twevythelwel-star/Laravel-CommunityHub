<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentChannelSetting extends Model
{
    use HasFactory;

    public const MODE_API = 'API';

    public const MODE_HOSTED_CHECKOUT = 'HOSTED_CHECKOUT';

    public const MODE_WEBHOOK = 'WEBHOOK';

    public const MODE_BANK_RECONCILIATION = 'BANK_RECONCILIATION';

    public const MODE_MANUAL_VERIFICATION = 'MANUAL_VERIFICATION';

    /**
     * Channels where the payer sends money to an account the estate names:
     * a bank account, a Zelle identifier or a Cash App cashtag. They are only
     * offered once an administrator has entered that account.
     */
    public const ACCOUNT_CHANNELS = ['bank_wire', 'zelle', 'cash_app'];

    protected $fillable = [
        'channel_key',
        'enabled',
        'display_label',
        'instructions',
        'account_identifier',
        'integration_mode',
        'fee_surcharge_percent',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'fee_surcharge_percent' => 'float',
    ];

    public static function requiresAccountDetails(string $channelKey): bool
    {
        return in_array($channelKey, self::ACCOUNT_CHANNELS, true);
    }

    /** The account a payer is told to send money to, or null when none is set. */
    public static function accountFor(string $channelKey): ?string
    {
        $account = static::query()->where('channel_key', $channelKey)->value('account_identifier');

        return filled($account) ? trim($account) : null;
    }

    /**
     * The saved row for a channel, or an unsaved one filled from its defaults,
     * so a first save always has a label and a channel with no row reads as
     * its default state (account channels: off).
     */
    public static function forChannel(string $channelKey): self
    {
        $defaults = collect(self::defaultChannels())->firstWhere('channel_key', $channelKey)
            ?? ['channel_key' => $channelKey, 'display_label' => ucfirst(str_replace('_', ' ', $channelKey)), 'enabled' => true];

        return static::query()->firstOrNew(['channel_key' => $channelKey], $defaults);
    }

    public static function defaultChannels(): array
    {
        return [
            ['channel_key' => 'card', 'display_label' => 'Credit/Debit Card', 'enabled' => true, 'integration_mode' => self::MODE_HOSTED_CHECKOUT, 'instructions' => 'Instant checkout powered by Stripe.'],
            ['channel_key' => 'apple_pay', 'display_label' => 'Apple Pay', 'enabled' => true, 'integration_mode' => self::MODE_WEBHOOK, 'instructions' => '1-tap biometric payment on Apple devices.'],
            ['channel_key' => 'google_pay', 'display_label' => 'Google Pay', 'enabled' => true, 'integration_mode' => self::MODE_WEBHOOK, 'instructions' => 'Fast checkout via Google Pay.'],
            ['channel_key' => 'samsung_wallet', 'display_label' => 'Samsung Wallet', 'enabled' => true, 'integration_mode' => self::MODE_WEBHOOK, 'instructions' => 'Pay seamlessly with Samsung Wallet.'],
            // Account channels start disabled with no account: payers must never be sent
            // to an account the estate has not entered itself.
            ['channel_key' => 'bank_wire', 'display_label' => 'Direct Bank Transfer', 'enabled' => false, 'integration_mode' => self::MODE_BANK_RECONCILIATION, 'instructions' => 'Use your transaction ID as the transfer reference.', 'account_identifier' => null],
            ['channel_key' => 'zelle', 'display_label' => 'Zelle', 'enabled' => false, 'integration_mode' => self::MODE_BANK_RECONCILIATION, 'instructions' => 'Put your transaction ID in the memo.', 'account_identifier' => null],
            ['channel_key' => 'cash_app', 'display_label' => 'Cash App', 'enabled' => false, 'integration_mode' => self::MODE_BANK_RECONCILIATION, 'instructions' => 'Put your transaction ID in the note.', 'account_identifier' => null],
            ['channel_key' => 'qr_code', 'display_label' => 'QR Code Scan', 'enabled' => true, 'integration_mode' => self::MODE_HOSTED_CHECKOUT, 'instructions' => 'Scan with mobile camera to pay instantly.'],
            ['channel_key' => 'nfc_pos', 'display_label' => 'Tap to Pay / Contactless', 'enabled' => true, 'integration_mode' => self::MODE_API, 'instructions' => 'Tap physical card on supported card-present POS terminal.'],
            ['channel_key' => 'cash_office', 'display_label' => 'Cash at Office', 'enabled' => true, 'integration_mode' => self::MODE_MANUAL_VERIFICATION, 'instructions' => 'Pay in-person at the administration desk. Receipt provided.'],
            ['channel_key' => 'wallet', 'display_label' => 'Community Wallet', 'enabled' => true, 'integration_mode' => self::MODE_API, 'instructions' => 'Instant deduction from your community credit balance.'],
        ];
    }
}
