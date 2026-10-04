<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between pb-6 border-b border-slate-700/60 gap-4">
        <div>
            <div class="flex items-center gap-3">
                <div class="p-2.5 bg-emerald-500/10 text-emerald-400 rounded-xl border border-emerald-500/20">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-white tracking-tight">Modular Payments & Subscriptions</h1>
                    <p class="text-sm text-slate-400">Decoupled payment gateway modules: Laravel Cashier, Stripe, PayPal, Square, Adyen, Braintree, Flutterwave, Paystack, Mollie, and Authorize.Net</p>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="flex items-center bg-slate-800/80 p-1 rounded-xl border border-slate-700/60 shadow-inner">
            <button wire:click="selectTab('catalog')" class="px-4 py-2 text-xs font-semibold rounded-lg transition-all {{ $activeTab === 'catalog' ? 'bg-emerald-600 text-white shadow' : 'text-slate-400 hover:text-slate-200' }}">
                Gateway Catalog
            </button>
            <button wire:click="selectTab('checkout')" class="px-4 py-2 text-xs font-semibold rounded-lg transition-all {{ $activeTab === 'checkout' ? 'bg-emerald-600 text-white shadow' : 'text-slate-400 hover:text-slate-200' }}">
                Hosted Checkout
            </button>
            <button wire:click="selectTab('subscriptions')" class="px-4 py-2 text-xs font-semibold rounded-lg transition-all {{ $activeTab === 'subscriptions' ? 'bg-emerald-600 text-white shadow' : 'text-slate-400 hover:text-slate-200' }}">
                Recurring & Trials
            </button>
            <button wire:click="selectTab('coupons')" class="px-4 py-2 text-xs font-semibold rounded-lg transition-all {{ $activeTab === 'coupons' ? 'bg-emerald-600 text-white shadow' : 'text-slate-400 hover:text-slate-200' }}">
                Coupons & Portal
            </button>
        </div>
    </div>

    <!-- Active Tab: Gateway Catalog -->
    @if($activeTab === 'catalog')
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($catalog as $module)
        <div class="bg-slate-800/60 border border-slate-700/70 rounded-2xl p-5 hover:border-slate-600 transition-all shadow-lg flex flex-col justify-between">
            <div>
                <div class="flex items-start justify-between gap-3 mb-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-white text-base">{{ $module['label'] }}</span>
                            @if($module['is_default'])
                                <span class="px-2 py-0.5 text-[10px] uppercase font-bold tracking-wider rounded-full bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">Default</span>
                            @endif
                        </div>
                        <span class="text-xs font-mono text-slate-400">driver: {{ $module['key'] }}</span>
                    </div>

                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $module['is_configured'] ? 'bg-emerald-900/40 text-emerald-300 border border-emerald-700/50' : 'bg-slate-700/50 text-slate-400' }}">
                        {{ $module['is_configured'] ? 'Configured' : 'Optional' }}
                    </span>
                </div>

                <div class="mt-3">
                    <h4 class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Supported Capabilities:</h4>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($module['capabilities'] as $cap)
                        <span class="px-2 py-0.5 rounded text-[11px] bg-slate-900/60 text-slate-300 border border-slate-700/50 font-mono">
                            {{ str_replace('_', ' ', $cap) }}
                        </span>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="mt-5 pt-4 border-t border-slate-700/60 flex items-center justify-between text-xs text-slate-400">
                <span>{{ $module['supports_subscriptions'] ? '✓ Subscriptions' : '✗ One-time only' }}</span>
                <button wire:click="selectDriver('{{ $module['key'] }}'); selectTab('checkout');" class="text-emerald-400 hover:text-emerald-300 font-semibold hover:underline">
                    Test Driver &rarr;
                </button>
            </div>
        </div>
        @endforeach
    </div>
    @endif

    <!-- Active Tab: Hosted Checkout Testing -->
    @if($activeTab === 'checkout')
    <div class="bg-slate-800/60 border border-slate-700/70 rounded-2xl p-6 shadow-xl max-w-2xl mx-auto">
        <h3 class="text-lg font-bold text-white mb-2">Simulate Direct SDK Checkout Session</h3>
        <p class="text-xs text-slate-400 mb-6">Tests initializing a hosted payment session with any configured provider.</p>

        <form wire:submit.prevent="testCheckoutSession" class="space-y-4">
            <div>
                <label class="block text-xs font-medium text-slate-300 mb-1">Select Gateway Driver</label>
                <select wire:model="selectedDriver" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-sm text-white focus:outline-none focus:border-emerald-500">
                    @foreach($catalog as $m)
                        <option value="{{ $m['key'] }}">{{ $m['label'] }} ({{ $m['key'] }})</option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Amount</label>
                    <input type="number" step="0.01" wire:model="testAmount" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-sm text-white focus:outline-none focus:border-emerald-500" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Currency</label>
                    <input type="text" wire:model="testCurrency" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-sm text-white focus:outline-none focus:border-emerald-500" />
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-300 mb-1">Customer Email</label>
                <input type="email" wire:model="testEmail" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-sm text-white focus:outline-none focus:border-emerald-500" />
            </div>

            <button type="submit" class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-semibold rounded-xl shadow-lg transition-colors">
                Generate Checkout Session
            </button>
        </form>

        @if($checkoutSessionResult)
        <div class="mt-6 p-4 rounded-xl bg-slate-900/80 border border-emerald-500/30 text-emerald-400 font-mono text-xs break-all">
            {{ $checkoutSessionResult }}
        </div>
        @endif
    </div>
    @endif

    <!-- Active Tab: Subscriptions & Recurring -->
    @if($activeTab === 'subscriptions')
    <div class="bg-slate-800/60 border border-slate-700/70 rounded-2xl p-6 shadow-xl max-w-2xl mx-auto">
        <h3 class="text-lg font-bold text-white mb-2">Simulate Subscription & Trial Setup</h3>
        <p class="text-xs text-slate-400 mb-6">Tests recurring payment scheduling with trial periods and optional promo codes.</p>

        <form wire:submit.prevent="testSubscription" class="space-y-4">
            <div>
                <label class="block text-xs font-medium text-slate-300 mb-1">Select Subscription Driver</label>
                <select wire:model="selectedDriver" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-sm text-white focus:outline-none focus:border-emerald-500">
                    <option value="cashier">Laravel Cashier (Stripe Subscriptions)</option>
                    <option value="stripe">Stripe Direct SDK</option>
                    <option value="paypal">PayPal Subscriptions</option>
                    <option value="square">Square Subscriptions</option>
                    <option value="authorizenet">Authorize.Net ARB</option>
                    <option value="paystack">Paystack Plans</option>
                    <option value="flutterwave">Flutterwave Plans</option>
                    <option value="mollie">Mollie Recurring</option>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Plan Identifier</label>
                    <input type="text" wire:model="testPlanId" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-sm text-white focus:outline-none focus:border-emerald-500" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Billing Cadence</label>
                    <select wire:model="testCadence" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-sm text-white focus:outline-none focus:border-emerald-500">
                        <option value="monthly">Monthly</option>
                        <option value="yearly">Yearly</option>
                        <option value="weekly">Weekly</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Trial Period (Days)</label>
                    <input type="number" wire:model="testTrialDays" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-sm text-white focus:outline-none focus:border-emerald-500" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Coupon / Promo Code</label>
                    <input type="text" wire:model="testCoupon" placeholder="e.g. WELCOME20" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-sm text-white focus:outline-none focus:border-emerald-500" />
                </div>
            </div>

            <button type="submit" class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-semibold rounded-xl shadow-lg transition-colors">
                Schedule Subscription
            </button>
        </form>

        @if($subscriptionResult)
        <div class="mt-6 p-4 rounded-xl bg-slate-900/80 border border-emerald-500/30 text-emerald-400 font-mono text-xs break-all">
            {{ $subscriptionResult }}
        </div>
        @endif
    </div>
    @endif

    <!-- Active Tab: Coupons & Billing Portal -->
    @if($activeTab === 'coupons')
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 max-w-4xl mx-auto">
        <!-- Coupon Validation -->
        <div class="bg-slate-800/60 border border-slate-700/70 rounded-2xl p-6 shadow-xl">
            <h3 class="text-lg font-bold text-white mb-2">Validate Promo Coupon</h3>
            <p class="text-xs text-slate-400 mb-4">Test discount verification (supports WELCOME20, COMMUNITY50, EARLYBIRD).</p>

            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Coupon Code</label>
                    <input type="text" wire:model="couponInput" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-sm text-white uppercase focus:outline-none focus:border-emerald-500" />
                </div>

                <button wire:click="testCouponValidation" class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-semibold rounded-xl shadow-lg transition-colors">
                    Validate Coupon
                </button>

                @if($couponResult)
                <div class="p-4 rounded-xl bg-emerald-950/40 border border-emerald-500/40 text-emerald-300 text-xs">
                    <p class="font-bold text-sm">✓ Valid Coupon: {{ $couponResult['code'] }}</p>
                    <p class="mt-1">Discount: {{ $couponResult['value'] }}{{ $couponResult['type'] === 'percent' ? '%' : ' '.$couponResult['type'] }}</p>
                    <p class="mt-1 text-slate-300">{{ $couponResult['desc'] }}</p>
                </div>
                @endif

                @if($couponError)
                <div class="p-4 rounded-xl bg-rose-950/40 border border-rose-500/40 text-rose-300 text-xs">
                    ✗ {{ $couponError }}
                </div>
                @endif
            </div>
        </div>

        <!-- Customer Billing Portal -->
        <div class="bg-slate-800/60 border border-slate-700/70 rounded-2xl p-6 shadow-xl flex flex-col justify-between">
            <div>
                <h3 class="text-lg font-bold text-white mb-2">Customer Billing Portal</h3>
                <p class="text-xs text-slate-400 mb-4">Provides customers with self-service invoice downloads, payment method updates, and plan management.</p>
                <div class="p-4 rounded-xl bg-slate-900/60 border border-slate-700/50 space-y-2 text-xs text-slate-300">
                    <p class="flex items-center gap-2"><span class="text-emerald-400">✓</span> Update default credit card or billing details</p>
                    <p class="flex items-center gap-2"><span class="text-emerald-400">✓</span> Download past PDF receipts and tax invoices</p>
                    <p class="flex items-center gap-2"><span class="text-emerald-400">✓</span> Cancel or pause recurring subscriptions</p>
                    <p class="flex items-center gap-2"><span class="text-emerald-400">✓</span> Switch membership / resident assessment tiers</p>
                </div>
            </div>

            <div class="mt-6">
                <a href="/api/v1/payments/billing-portal?return_url={{ urlencode(url('/dashboard/payments')) }}" target="_blank" class="block text-center py-2.5 bg-slate-700 hover:bg-slate-600 text-white text-sm font-semibold rounded-xl shadow transition-colors">
                    Test Portal Endpoint API &rarr;
                </a>
            </div>
        </div>
    </div>
    @endif
</div>
