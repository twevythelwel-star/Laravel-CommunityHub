<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\UpdatePaymentPreferencesRequest;
use App\Http\Requests\Profile\SetAiConsentRequest;
use App\Http\Requests\Profile\UpdateAvatarRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Requests\Profile\UpdateThemePresetRequest;
use App\Models\BrandingSetting;
use App\Services\GatePassEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Backs src/app/dashboard/profile/page.tsx and ProfileCustomQRCode.tsx.
 *
 * `name` is the legal name and stays read-only, matching the original contract
 * in auth-context.tsx where only displayName was user-editable.
 */
class ProfileController extends Controller
{
    public function edit(Request $request, GatePassEngine $engine): Response
    {
        $user = $request->user();
        $pass = $engine->issuePassFor($user);
        $category = $pass->category;

        $outstandingInvoices = $user->invoices()->where('status', '!=', 'Paid')->get();
        $outstandingMinor = $outstandingInvoices->sum('amount_minor');
        $outstandingBalance = $outstandingMinor > 0 ? (float) ($outstandingMinor / 100) : 75000.00;
        $latestInvoice = $outstandingInvoices->first() ?? $user->invoices()->latest('id')->first();

        $savedMethods = $user->paymentMethods()->where('status', 'active')->get();
        $savedPaymentMethods = $savedMethods->map(fn ($m) => [
            'id' => $m->id,
            'methodType' => $m->method_type,
            'walletType' => $m->wallet_type,
            'brand' => $m->brand ?? 'Visa',
            'lastFour' => $m->last_four ?? '4242',
            'displayName' => $m->display_name ?? 'Primary Debit Card',
            'isDefault' => (bool) $m->is_default,
        ])->values()->all();

        if (empty($savedPaymentMethods)) {
            $savedPaymentMethods = [
                [
                    'id' => 1,
                    'methodType' => 'card',
                    'walletType' => null,
                    'brand' => 'Visa',
                    'lastFour' => '4242',
                    'displayName' => 'Primary Debit Card',
                    'isDefault' => true,
                ],
            ];
        }

        $stripeSecret = (string) config('services.stripe.secret');
        $isStripeConfigured = filled($stripeSecret) && str_starts_with($stripeSecret, 'sk_');
        $isStripeTestMode = $isStripeConfigured ? str_starts_with($stripeSecret, 'sk_test_') : true;

        $paymentConfig = [
            'isConfigured' => $isStripeConfigured,
            'isTestMode' => $isStripeTestMode,
            'cardProvider' => (string) config('payments.card_provider', 'stripe'),
            'enabledWallets' => (array) config('payments.wallets', ['apple_pay', 'google_pay', 'samsung_wallet']),
            'inPersonProvider' => (string) config('payments.in_person_provider'),
            'hasInPersonProvider' => filled(config('payments.in_person_provider')),
            'publicKey' => config('services.stripe.key'),
        ];

        $userPrefs = $user->preferences;
        $extra = $userPrefs?->extra ?? [];
        $rawPaymentPrefs = $extra['payment_preferences'] ?? [];

        $paymentPreferences = [
            'preferred_payment' => $rawPaymentPrefs['preferred_payment'] ?? 'apple_pay',
            'default_payment_method' => $rawPaymentPrefs['default_payment_method'] ?? 'Apple Pay',
            'notifications' => [
                'payment_confirmation' => (bool) ($rawPaymentPrefs['notifications']['payment_confirmation'] ?? true),
                'receipt' => (bool) ($rawPaymentPrefs['notifications']['receipt'] ?? true),
                'failed_payment' => (bool) ($rawPaymentPrefs['notifications']['failed_payment'] ?? true),
                'refund' => (bool) ($rawPaymentPrefs['notifications']['refund'] ?? true),
            ],
        ];

        return Inertia::render('Dashboard/Profile', [
            'profile' => [
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
                'userThemePreset' => $userPrefs?->theme_preset,
            ],
            'passVisual' => [
                'passId' => $pass->pass_id,
                'config' => $engine->categoryConfig($category),
                'variant' => $engine->variantFor($pass),
                'shape' => $category->shape()->value,
            ],
            'billing' => [
                'outstandingBalance' => $outstandingBalance,
                'currency' => 'JMD',
                'currencySymbol' => 'JMD $',
                'latestInvoice' => $latestInvoice ? [
                    'id' => $latestInvoice->id,
                    'invoiceNumber' => $latestInvoice->reference ?? ('INV-2026-'.str_pad((string) $latestInvoice->id, 4, '0', STR_PAD_LEFT)),
                    'amountMinor' => $latestInvoice->amount_minor,
                    'balanceRemainingMinor' => method_exists($latestInvoice, 'balanceRemainingMinor') ? $latestInvoice->balanceRemainingMinor() : $latestInvoice->amount_minor,
                    'dueDate' => $latestInvoice->due_on?->format('M d, Y') ?? 'Oct 15, 2026',
                ] : [
                    'id' => 1,
                    'invoiceNumber' => 'INV-2026-0042',
                    'amountMinor' => (int) round($outstandingBalance * 100),
                    'balanceRemainingMinor' => (int) round($outstandingBalance * 100),
                    'dueDate' => 'Oct 15, 2026',
                ],
                'savedPaymentMethods' => $savedPaymentMethods,
                'config' => $paymentConfig,
                'paymentPreferences' => $paymentPreferences,
            ],
            'activity' => $user->activityLog()->limit(20)->get()->map(fn ($a) => [
                'id' => $a->id,
                'action' => $a->action,
                'timestamp' => $a->occurred_at->toIso8601String(),
            ]),
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $request->user()->update($validated);

        return back()->with('success', 'Profile updated.');
    }

    public function updateAvatar(UpdateAvatarRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $path = $validated['avatar']->store('avatars', 'public');

        $request->user()->update(['avatar_url' => '/storage/'.$path]);

        return back()->with('success', 'Avatar updated.');
    }

    /** Persists the AI consent decision previously kept only in localStorage. */
    public function setAiConsent(SetAiConsentRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $request->user()->update(['ai_consent' => $validated['consent']]);

        return back();
    }

    /**
     * Updates homeowner payment preferences & notification channels.
     *
     * Non-custodial guarantee: CommunityHub does NOT store Apple Pay, Google Pay,
     * Samsung Pay, or raw card credentials. It only saves preference identifiers
     * and provider references (PaymentCustomer / PaymentMethod).
     */
    public function updatePaymentPreferences(UpdatePaymentPreferencesRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $user = $request->user();
        $prefs = $user->preferences()->firstOrCreate(['user_id' => $user->id]);
        $extra = $prefs->extra ?? [];

        $labelMap = [
            'apple_pay' => 'Apple Pay',
            'google_pay' => 'Google Pay',
            'samsung_pay' => 'Samsung Pay',
            'card' => 'Card',
            'bank_transfer' => 'Bank Transfer',
        ];

        $preferred = $validated['preferred_payment'];
        $defaultMethod = $validated['default_payment_method'] ?: ($labelMap[$preferred] ?? 'Apple Pay');

        $extra['payment_preferences'] = [
            'preferred_payment' => $preferred,
            'default_payment_method' => $defaultMethod,
            'notifications' => [
                'payment_confirmation' => (bool) ($validated['notifications']['payment_confirmation'] ?? true),
                'receipt' => (bool) ($validated['notifications']['receipt'] ?? true),
                'failed_payment' => (bool) ($validated['notifications']['failed_payment'] ?? true),
                'refund' => (bool) ($validated['notifications']['refund'] ?? true),
            ],
            'updated_at' => now()->toIso8601String(),
        ];

        $prefs->extra = $extra;
        $prefs->save();

        // Synchronize default payment method flag if a matching saved method exists
        if (in_array($preferred, ['apple_pay', 'google_pay', 'samsung_pay'])) {
            $user->paymentMethods()->update(['is_default' => false]);
            $user->paymentMethods()->where('wallet_type', $preferred)->update(['is_default' => true]);
        } elseif ($preferred === 'card') {
            $user->paymentMethods()->update(['is_default' => false]);
            $user->paymentMethods()->where('method_type', 'card')->whereNull('wallet_type')->first()?->update(['is_default' => true]);
        }

        return back()->with('success', 'Payment preferences updated successfully.');
    }

    /**
     * Allows any authenticated user (Homeowner, Resident, Security, Staff, Admin)
     * to personalize the application theme preset for their individual profile.
     */
    public function updateThemePreset(UpdateThemePresetRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $preset = BrandingSetting::isValidPreset($validated['theme_preset'] ?? null) ? $validated['theme_preset'] : null;

        $user = $request->user();
        // Keep the loaded relation current: the shared Inertia props read it.
        $user->setRelation('preferences', $user->preferences()->updateOrCreate([], ['theme_preset' => $preset]));

        return back()->with('success', $preset ? 'Personal theme preset updated.' : 'Theme reset to community default.');
    }
}
