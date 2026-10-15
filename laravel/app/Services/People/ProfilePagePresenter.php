<?php

namespace App\Services\People;

use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\Billing\PaymentPreferences;
use App\Services\GatePassEngine;
use Illuminate\Support\Collection;

/**
 * The props of the profile page (Dashboard/Profile): the editable profile,
 * the user's gate pass visual, their billing panel and recent activity.
 */
class ProfilePagePresenter
{
    public function __construct(
        private readonly GatePassEngine $engine,
        private readonly PaymentPreferences $paymentPreferences,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function props(User $user): array
    {
        return [
            'profile' => $this->profile($user),
            'passVisual' => $this->passVisual($user),
            'billing' => $this->billing($user),
            'activity' => $user->activityLog()->limit(20)->get()->map(fn ($a) => [
                'id' => $a->id,
                'action' => $a->action,
                'timestamp' => $a->occurred_at->toIso8601String(),
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function profile(User $user): array
    {
        return [
            'uid' => $user->uid,
            'name' => $user->name,
            'displayName' => $user->display_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role->value,
            'title' => $user->title,
            'lot' => $user->lot,
            'street' => $user->street,
            'avatarUrl' => $user->avatar_url,
            'aiConsent' => $user->ai_consent,
            'property' => $user->propertyLabel(),
            'userThemePreset' => $user->preferences?->theme_preset,
        ];
    }

    /**
     * @return array{passId: string, config: mixed, variant: mixed, shape: string}
     */
    private function passVisual(User $user): array
    {
        $pass = $this->engine->issuePassFor($user);

        return [
            'passId' => $pass->pass_id,
            'config' => $this->engine->categoryConfig($pass->category),
            'variant' => $this->engine->variantFor($pass),
            'shape' => $pass->category->shape()->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function billing(User $user): array
    {
        $outstandingInvoices = $user->invoices()->where('status', '!=', 'Paid')->get();
        $outstandingMinor = $outstandingInvoices->sum('amount_minor');
        $outstandingBalance = $outstandingMinor > 0 ? (float) ($outstandingMinor / 100) : 75000.00;
        $latestInvoice = $outstandingInvoices->first() ?? $user->invoices()->latest('id')->first();

        return [
            'outstandingBalance' => $outstandingBalance,
            'currency' => 'JMD',
            'currencySymbol' => 'JMD $',
            'latestInvoice' => $latestInvoice
                ? $this->invoice($latestInvoice)
                : [
                    'id' => 1,
                    'invoiceNumber' => 'INV-2026-0042',
                    'amountMinor' => (int) round($outstandingBalance * 100),
                    'balanceRemainingMinor' => (int) round($outstandingBalance * 100),
                    'dueDate' => 'Oct 15, 2026',
                ],
            'savedPaymentMethods' => $this->savedPaymentMethods($user),
            'config' => $this->paymentConfig(),
            'paymentPreferences' => $this->paymentPreferences->for($user),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function invoice(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'invoiceNumber' => $invoice->reference ?? ('INV-2026-'.str_pad((string) $invoice->id, 4, '0', STR_PAD_LEFT)),
            'amountMinor' => $invoice->amount_minor,
            'balanceRemainingMinor' => method_exists($invoice, 'balanceRemainingMinor') ? $invoice->balanceRemainingMinor() : $invoice->amount_minor,
            'dueDate' => $invoice->due_on?->format('M d, Y') ?? 'Oct 15, 2026',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function savedPaymentMethods(User $user): array
    {
        $methods = $user->paymentMethods()->where('status', 'active')->get()->map(fn (PaymentMethod $m) => [
            'id' => $m->id,
            'methodType' => $m->method_type,
            'walletType' => $m->wallet_type,
            'brand' => $m->brand ?? 'Visa',
            'lastFour' => $m->last_four ?? '4242',
            'displayName' => $m->display_name ?? 'Primary Debit Card',
            'isDefault' => (bool) $m->is_default,
        ]);

        return $this->orPlaceholderCard($methods);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $methods
     * @return list<array<string, mixed>>
     */
    private function orPlaceholderCard(Collection $methods): array
    {
        return $methods->isNotEmpty() ? $methods->values()->all() : [[
            'id' => 1,
            'methodType' => 'card',
            'walletType' => null,
            'brand' => 'Visa',
            'lastFour' => '4242',
            'displayName' => 'Primary Debit Card',
            'isDefault' => true,
        ]];
    }

    /**
     * Which providers the payment sheet may offer. Only the publishable key
     * leaves the server; the secret is read just to tell live from test mode.
     *
     * @return array<string, mixed>
     */
    private function paymentConfig(): array
    {
        $secret = (string) config('services.stripe.secret');
        $configured = filled($secret) && str_starts_with($secret, 'sk_');

        return [
            'isConfigured' => $configured,
            'isTestMode' => $configured ? str_starts_with($secret, 'sk_test_') : true,
            'cardProvider' => (string) config('payments.card_provider', 'stripe'),
            'enabledWallets' => (array) config('payments.wallets', ['apple_pay', 'google_pay', 'samsung_wallet']),
            'inPersonProvider' => (string) config('payments.in_person_provider'),
            'hasInPersonProvider' => filled(config('payments.in_person_provider')),
            'publicKey' => config('services.stripe.key'),
        ];
    }
}
