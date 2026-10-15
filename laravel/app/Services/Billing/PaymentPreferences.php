<?php

namespace App\Services\Billing;

use App\Models\User;

/**
 * A user's preferred way to pay and which payment notifications they want,
 * kept in user_preferences.extra['payment_preferences'].
 *
 * Non-custodial guarantee: CommunityHub does NOT store Apple Pay, Google Pay,
 * Samsung Pay, or raw card credentials. It only saves preference identifiers
 * and provider references (PaymentCustomer / PaymentMethod).
 */
class PaymentPreferences
{
    private const LABELS = [
        'apple_pay' => 'Apple Pay',
        'google_pay' => 'Google Pay',
        'samsung_pay' => 'Samsung Pay',
        'card' => 'Card',
        'bank_transfer' => 'Bank Transfer',
    ];

    private const WALLETS = ['apple_pay', 'google_pay', 'samsung_pay'];

    private const NOTIFICATIONS = ['payment_confirmation', 'receipt', 'failed_payment', 'refund'];

    /**
     * The saved preferences, with every notification on until turned off.
     *
     * @return array{preferred_payment: string, default_payment_method: string, notifications: array<string, bool>}
     */
    public function for(User $user): array
    {
        $saved = $user->preferences?->extra['payment_preferences'] ?? [];

        return [
            'preferred_payment' => $saved['preferred_payment'] ?? 'apple_pay',
            'default_payment_method' => $saved['default_payment_method'] ?? 'Apple Pay',
            'notifications' => $this->notifications($saved['notifications'] ?? []),
        ];
    }

    /**
     * Save the preferences and make the matching saved method the default.
     *
     * @param  array{preferred_payment: string, default_payment_method?: ?string, notifications?: array<string, mixed>}  $validated
     */
    public function save(User $user, array $validated): void
    {
        $preferred = $validated['preferred_payment'];

        $prefs = $user->preferences()->firstOrCreate(['user_id' => $user->id]);
        $extra = $prefs->extra ?? [];
        $extra['payment_preferences'] = [
            'preferred_payment' => $preferred,
            'default_payment_method' => ($validated['default_payment_method'] ?? null) ?: (self::LABELS[$preferred] ?? 'Apple Pay'),
            'notifications' => $this->notifications($validated['notifications'] ?? []),
            'updated_at' => now()->toIso8601String(),
        ];
        $prefs->extra = $extra;
        $prefs->save();

        $this->syncDefaultMethod($user, $preferred);
    }

    /**
     * @param  array<string, mixed>  $given
     * @return array<string, bool>
     */
    private function notifications(array $given): array
    {
        return collect(self::NOTIFICATIONS)
            ->mapWithKeys(fn (string $kind) => [$kind => (bool) ($given[$kind] ?? true)])
            ->all();
    }

    /** Flag the saved wallet or card that matches the preference, if there is one. */
    private function syncDefaultMethod(User $user, string $preferred): void
    {
        if (in_array($preferred, self::WALLETS, true)) {
            $user->paymentMethods()->update(['is_default' => false]);
            $user->paymentMethods()->where('wallet_type', $preferred)->update(['is_default' => true]);
        } elseif ($preferred === 'card') {
            $user->paymentMethods()->update(['is_default' => false]);
            $user->paymentMethods()->where('method_type', 'card')->whereNull('wallet_type')->first()?->update(['is_default' => true]);
        }
    }
}
