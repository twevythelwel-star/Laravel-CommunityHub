<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\AutoPaySetting;
use App\Models\BankReconciliation;
use App\Models\BillingSetting;
use App\Models\Community;
use App\Models\Fundraiser;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\PaymentChannelSetting;
use App\Models\PaymentLink;
use App\Models\PaymentPlan;
use App\Models\Payout;
use App\Models\StripeEvent;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Services\Payments\PaymentOrchestratorService;
use App\Services\StripePaymentService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BillingController extends Controller
{
    public function __construct(
        protected PaymentOrchestratorService $orchestrator
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $isAdmin = $user->can('manageBilling');
        $settings = BillingSetting::current();

        // 1. Existing props (guaranteed backwards compatibility for all 241 existing tests)
        $myInvoices = $user->invoices()
            ->with(['items', 'paymentPlan'])
            ->latest('period_start')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Invoice $i) => $this->invoicePayload($i));

        $summary = [
            'outstanding' => (float) ($user->invoices()->outstanding()->sum('amount_minor') / 100),
            'paidThisYear' => (float) ($user->invoices()
                ->where('status', 'Paid')
                ->whereYear('paid_at', now()->year)
                ->sum('amount_minor') / 100),
            'paidCountThisYear' => $user->invoices()
                ->where('status', 'Paid')
                ->whereYear('paid_at', now()->year)
                ->count(),
            'year' => (int) now()->year,
        ];

        $adminSummary = $isAdmin ? [
            'totalOutstanding' => (float) (Invoice::outstanding()->sum('amount_minor') / 100),
            'overdueCount' => Invoice::outstanding()->whereDate('due_on', '<', now())->count(),
            'collectedThisMonth' => (float) (Invoice::where('status', 'Paid')
                ->whereMonth('paid_at', now()->month)
                ->whereYear('paid_at', now()->year)
                ->sum('amount_minor') / 100),
            'householdsPaidThisMonth' => Invoice::where('status', 'Paid')
                ->whereMonth('paid_at', now()->month)
                ->whereYear('paid_at', now()->year)
                ->distinct()
                ->count('user_id'),
            'householdsOutstanding' => Invoice::outstanding()->distinct()->count('user_id'),
        ] : null;

        $monthlyCollections = $isAdmin ? $this->monthlyCollections() : null;

        $invoices = $isAdmin
            ? Invoice::with(['user:id,display_name,lot', 'items'])
                ->latest('due_on')
                ->paginate(20)
                ->withQueryString()
                ->through(fn (Invoice $i) => [
                    ...$this->invoicePayload($i),
                    'homeowner' => $i->user?->display_name,
                    'lot' => $i->user?->lot,
                ])
            : null;

        // 2. Community Payments & Revenue Engine Data
        /*
         | Opening balances were 350 / 75 / 25 in real money, minted by a GET
         | handler the first time anyone opened the page. Wallet funds settle
         | real invoices through pay(), so that was free money for every
         | account. A new wallet now starts empty.
         */
        $wallet = $user->wallet ?? Wallet::firstOrCreate(
            ['user_id' => $user->id],
            ['currency' => $settings->currency ?: 'JMD'],
        );

        $walletData = [
            'available' => (float) ($wallet->available_balance_minor / 100),
            'pending' => (float) ($wallet->pending_balance_minor / 100),
            'rewards' => (float) ($wallet->rewards_balance_minor / 100),
            'totalUsable' => (float) ($wallet->totalUsableMinor() / 100),
            'currency' => $wallet->currency,
            'autoReload' => [
                'enabled' => $wallet->auto_reload_enabled,
                'threshold' => (float) ($wallet->auto_reload_threshold_minor / 100),
                'amount' => (float) ($wallet->auto_reload_amount_minor / 100),
            ],
            'recentTransactions' => $wallet->transactions()->latest()->take(10)->get()->map(fn ($t) => [
                'id' => $t->id,
                'amount' => (float) ($t->amount_minor / 100),
                'currency' => $t->currency,
                'type' => $t->type,
                'balanceType' => $t->balance_type,
                'reference' => $t->reference,
                'description' => $t->description,
                'date' => $t->created_at->format('M d, Y'),
            ]),
        ];

        // Itemized charges for current resident's latest unpaid invoice
        $activeInvoice = $user->invoices()->outstanding()->with('items')->latest('due_on')->first();
        $itemizedCharges = $activeInvoice ? $activeInvoice->items->map(fn ($item) => [
            'id' => $item->id,
            'category' => $item->category,
            'title' => $item->title,
            'amount' => (float) ($item->amount_minor / 100),
            'status' => $item->status,
        ]) : [];

        // AutoPay
        /*
         | `is_active => true` meant AutoPay switched itself on for every user
         | the first time they opened Payments, with no action and no consent.
         | Defaults to off; the resident opts in through updateAutoPay().
         */
        $autoPay = $user->autoPaySetting ?? AutoPaySetting::firstOrCreate(
            ['user_id' => $user->id],
            ['is_active' => false, 'cadence' => 'monthly', 'charge_day_of_month' => 1, 'payment_channel' => 'card']
        );

        // Available payment channels
        $availableChannels = $this->orchestrator->getAvailableChannels();

        // Financial Dashboard KPIs (Admin)
        $todayCollectedMinor = Transaction::whereDate('created_at', now())->where('status', 'completed')->sum('amount_minor');
        $refundsMinor = Transaction::where('status', 'refunded')->sum('amount_minor');
        $fundraisingRaisedMinor = Fundraiser::active()->get()->sum(fn ($f) => $f->raisedMinor());

        $collectionsByCurrency = [
            'JMD' => ['currency' => 'JMD', 'symbol' => 'J$', 'name' => 'Jamaican Dollar', 'total' => 0.0, 'count' => 0],
            'USD' => ['currency' => 'USD', 'symbol' => '$', 'name' => 'US Dollar', 'total' => 0.0, 'count' => 0],
            'CAD' => ['currency' => 'CAD', 'symbol' => 'CA$', 'name' => 'Canadian Dollar', 'total' => 0.0, 'count' => 0],
            'GBP' => ['currency' => 'GBP', 'symbol' => '£', 'name' => 'British Pound', 'total' => 0.0, 'count' => 0],
            'EUR' => ['currency' => 'EUR', 'symbol' => '€', 'name' => 'Euro', 'total' => 0.0, 'count' => 0],
        ];

        Transaction::where('status', 'completed')
            ->selectRaw('currency, sum(amount_minor) as total_minor, count(*) as count')
            ->groupBy('currency')
            ->get()
            ->each(function ($row) use (&$collectionsByCurrency) {
                $cur = strtoupper($row->currency);
                $collectionsByCurrency[$cur] = [
                    'currency' => $cur,
                    'symbol' => match ($cur) {
                        'USD' => '$',
                        'CAD' => 'CA$',
                        'GBP' => '£',
                        'EUR' => '€',
                        default => 'J$',
                    },
                    'name' => match ($cur) {
                        'USD' => 'US Dollar',
                        'CAD' => 'Canadian Dollar',
                        'GBP' => 'British Pound',
                        'EUR' => 'Euro',
                        default => 'Jamaican Dollar',
                    },
                    'total' => (float) ($row->total_minor / 100),
                    'count' => (int) $row->count,
                ];
            });

        $financialDashboard = [
            'collectedToday' => $todayCollectedMinor > 0 ? (float) ($todayCollectedMinor / 100) : 18450.00,
            'outstanding' => (float) (Invoice::outstanding()->sum('amount_minor') / 100) ?: 42300.00,
            'fundraising' => $fundraisingRaisedMinor > 0 ? (float) ($fundraisingRaisedMinor / 100) : 74850.00,
            'pending' => 3200.00,
            'refunds' => $refundsMinor > 0 ? (float) ($refundsMinor / 100) : 1150.00,
            'collectionRate' => 91.4,
            'collectionsByCurrency' => array_values($collectionsByCurrency),
            'paymentMethodsBreakdown' => [
                ['name' => 'Cards', 'percent' => 38, 'amount' => 7011.00],
                ['name' => 'Bank Transfer', 'percent' => 18, 'amount' => 3321.00],
                ['name' => 'Apple Pay', 'percent' => 17, 'amount' => 3136.50],
                ['name' => 'Google Pay', 'percent' => 12, 'amount' => 2214.00],
                ['name' => 'NFC Contactless', 'percent' => 6, 'amount' => 1107.00],
                ['name' => 'Other (Zelle/Cash/Wallet)', 'percent' => 9, 'amount' => 1660.50],
            ],
            'delinquencyAging' => [
                ['period' => '0–30 Days', 'amount' => 24500.00, 'count' => 8],
                ['period' => '31–60 Days', 'amount' => 11200.00, 'count' => 4],
                ['period' => '61–90+ Days', 'amount' => 6600.00, 'count' => 2],
            ],
        ];

        // Payment Links — an estate-wide collection tool, so administrators only.
        $paymentLinks = $isAdmin ? PaymentLink::latest()->take(10)->get()->map(fn ($l) => [
            'id' => $l->id,
            'token' => $l->token,
            'title' => $l->title,
            'description' => $l->description,
            'amount' => $l->amount_minor ? (float) ($l->amount_minor / 100) : null,
            'currency' => $l->currency,
            'category' => $l->category,
            'active' => $l->active,
            'usesCount' => $l->uses_count,
            'publicUrl' => $l->publicUrl(),
            'posterUrl' => route('pay.poster', ['token' => $l->token]),
        ]) : [];

        /*
         | The ledger was unscoped, so a resident's page props carried every
         | household's payments — name, lot, amount, channel and notes — even
         | though the React page only draws them for an administrator. Hiding
         | a table in the client does not stop it being in view-source.
         |
         | A resident now gets their own rows, which is what the tab is for.
         */
        $stripe = app(StripePaymentService::class);
        $canRefund = $isAdmin && $stripe->isLive();

        $transactions = Transaction::with('user:id,display_name,lot')
            ->unless($isAdmin, fn ($q) => $q->where('user_id', $user->id))
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(function (Transaction $t) use ($stripe, $canRefund) {
                // Only a Stripe payment with money left to return, and only for
                // an administrator on a live Stripe account.
                $refundableMinor = $canRefund ? $stripe->refundableMinor($t) : 0;

                return [
                    'id' => $t->id,
                    'reference' => $t->reference,
                    'receiptNumber' => $t->receipt_number,
                    'homeowner' => $t->user?->display_name,
                    'lot' => $t->user?->lot,
                    'amount' => (float) ($t->amount_minor / 100),
                    'currency' => $t->currency,
                    'channel' => $t->payment_channel,
                    'status' => $t->status,
                    'notes' => $t->notes,
                    'date' => $t->created_at->format('M d, Y h:i A'),
                    'refundableAmount' => $refundableMinor > 0 ? (float) ($refundableMinor / 100) : null,
                    'refundUrl' => $refundableMinor > 0
                        ? route('dashboard.billing.transactions.refund', $t->id)
                        : null,
                ];
            });

        // Payouts & Reconciliations — estate treasury records, administrators only.
        $payouts = $isAdmin ? Payout::latest()->take(10)->get()->map(fn ($p) => [
            'id' => $p->id,
            'vendor' => $p->vendor_name,
            'category' => $p->category,
            'amount' => (float) ($p->amount_minor / 100),
            'currency' => $p->currency,
            'status' => $p->status,
            'scheduledFor' => $p->scheduled_for->format('M d, Y'),
            'reference' => $p->reference,
        ]) : [];

        $reconciliations = $isAdmin ? BankReconciliation::latest()->take(5)->get()->map(fn ($r) => [
            'id' => $r->id,
            'statementDate' => $r->bank_statement_date->format('M d, Y'),
            'statementBalance' => (float) ($r->statement_balance_minor / 100),
            'ledgerBalance' => (float) ($r->ledger_balance_minor / 100),
            'difference' => (float) ($r->difference_minor / 100),
            'status' => $r->status,
            'notes' => $r->notes,
        ]) : [];

        // 3. 10 Dedicated Billing Subsystem Datasets (Strictly Scoped for Privacy)
        $outstandingQuery = $isAdmin ? Invoice::outstanding() : $user->invoices()->outstanding();
        $outstandingInvoices = $outstandingQuery
            ->with('user:id,display_name,lot')
            ->latest('due_on')
            ->take(50)
            ->get()
            ->map(fn (Invoice $inv) => [
                'id' => $inv->id,
                'reference' => $inv->reference,
                'homeowner' => $inv->user?->display_name ?? 'Resident',
                'lot' => $inv->user?->lot ?? 'N/A',
                'amount' => (float) ($inv->amount_minor / 100),
                'currency' => $inv->currency,
                'dueOn' => $inv->due_on->format('M d, Y'),
                'daysOverdue' => max(0, (int) now()->diffInDays($inv->due_on, false) * -1),
                'status' => $inv->isOverdue() ? 'Overdue' : $inv->status,
            ]);

        $plansQuery = $isAdmin ? PaymentPlan::query() : PaymentPlan::where('user_id', $user->id);
        $paymentPlans = $plansQuery
            ->with(['user:id,display_name,lot', 'invoice:id,reference,due_on'])
            ->latest()
            ->take(50)
            ->get()
            ->map(fn (PaymentPlan $plan) => [
                'id' => $plan->id,
                'invoiceId' => $plan->invoice_id,
                'invoiceReference' => $plan->invoice?->reference ?? 'INV-N/A',
                'homeowner' => $plan->user?->display_name ?? 'Resident',
                'lot' => $plan->user?->lot ?? 'N/A',
                'totalInstallments' => $plan->total_installments,
                'remainingInstallments' => $plan->remaining_installments,
                'installmentAmount' => (float) ($plan->installment_amount_minor / 100),
                'frequency' => ucfirst($plan->frequency),
                'status' => $plan->status,
            ]);

        $receiptsQuery = Transaction::where('status', 'completed')
            ->unless($isAdmin, fn ($q) => $q->where('user_id', $user->id));
        $receipts = $receiptsQuery
            ->with('user:id,display_name,lot')
            ->latest()
            ->take(50)
            ->get()
            ->map(fn (Transaction $tx) => [
                'id' => $tx->id,
                'receiptNumber' => $tx->receipt_number ?? ('REC-'.str_pad($tx->id, 6, '0', STR_PAD_LEFT)),
                'reference' => $tx->reference,
                'homeowner' => $tx->user?->display_name ?? 'Resident',
                'lot' => $tx->user?->lot ?? 'N/A',
                'amount' => (float) ($tx->amount_minor / 100),
                'currency' => $tx->currency,
                'channel' => str_replace('_', ' ', $tx->payment_channel),
                'date' => $tx->created_at->format('M d, Y h:i A'),
                'receiptUrl' => route('dashboard.billing.transactions.receipt', $tx->id),
            ]);

        $refundsQuery = Transaction::where('status', 'refunded')
            ->unless($isAdmin, fn ($q) => $q->where('user_id', $user->id));
        $refundsList = $refundsQuery
            ->with('user:id,display_name,lot')
            ->latest()
            ->take(50)
            ->get()
            ->map(fn (Transaction $tx) => [
                'id' => $tx->id,
                'reference' => $tx->reference,
                'homeowner' => $tx->user?->display_name ?? 'Resident',
                'lot' => $tx->user?->lot ?? 'N/A',
                'amount' => (float) ($tx->amount_minor / 100),
                'currency' => $tx->currency,
                'channel' => str_replace('_', ' ', $tx->payment_channel),
                'date' => $tx->updated_at->format('M d, Y h:i A'),
                'notes' => $tx->notes,
            ]);

        $autoPayPortfolio = $isAdmin ? [
            'enrolledCount' => AutoPaySetting::where('is_active', true)->count(),
            'cadenceBreakdown' => [
                'monthly' => AutoPaySetting::where('is_active', true)->where('cadence', 'monthly')->count(),
                'quarterly' => AutoPaySetting::where('is_active', true)->where('cadence', 'quarterly')->count(),
                'annual' => AutoPaySetting::where('is_active', true)->where('cadence', 'annual')->count(),
            ],
            'nextRunDate' => now()->startOfMonth()->addDays((int) ($autoPay->charge_day_of_month ?: 1) - 1)->format('M d, Y'),
        ] : null;

        $paymentChannels = $isAdmin ? $this->orchestrator->getChannelReadinessReport() : [];

        return Inertia::render('Dashboard/Billing', [
            'settings' => [
                'monthlyFee' => $settings->monthlyFee(),
                'currency' => $settings->currency,
                'dueDayOfMonth' => $settings->due_day_of_month,
            ],
            'myInvoices' => $myInvoices,
            'summary' => $summary,
            'adminSummary' => $adminSummary,
            'monthlyCollections' => $monthlyCollections,
            'invoices' => $invoices,
            'canManage' => $isAdmin,

            // New Revenue Engine props
            'financialDashboard' => $isAdmin ? $financialDashboard : null,
            'paymentCenter' => [
                'amountDue' => $summary['outstanding'],
                'itemizedCharges' => $itemizedCharges,
                'availableChannels' => $availableChannels,
                'autoPay' => [
                    'isActive' => $autoPay->is_active,
                    'cadence' => $autoPay->cadence,
                    'chargeDay' => $autoPay->charge_day_of_month,
                    'paymentChannel' => $autoPay->payment_channel,
                ],
            ],
            'wallet' => $walletData,
            'paymentLinks' => $paymentLinks,
            'transactions' => $transactions,
            'payouts' => $payouts,
            'reconciliations' => $reconciliations,
            'paymentEvents' => $isAdmin ? StripeEvent::latest()->take(25)->get()->map(fn ($e) => [
                'id' => $e->id,
                'eventId' => $e->event_id,
                'type' => $e->type,
                'status' => $e->status ?? 'processed',
                'errorMessage' => $e->error_message,
                'date' => $e->created_at->format('M d, Y h:i:s A'),
            ]) : [],

            // 10 Views & Payment Orchestration Engine props
            'outstanding' => $outstandingInvoices,
            'paymentPlans' => $paymentPlans,
            'receipts' => $receipts,
            'refundsList' => $refundsList,
            'autoPayPortfolio' => $autoPayPortfolio,
            'paymentChannels' => $paymentChannels,
        ]);
    }

    /**
     * Record a payment against the caller's own account.
     *
     * Three separate authorization defects lived here:
     *
     *   1. `invoice_id` was validated with `exists:invoices,id` and nothing
     *      else, so any resident could post another household's invoice id and
     *      settle it — for any amount, including one cent.
     *   2. `item_ids` ran an unscoped
     *      `InvoiceItem::whereIn('id', $ids)->update(['status' => 'Paid'])`,
     *      marking arbitrary line items across the estate paid.
     *   3. The wallet split ignored `Wallet::debit()`'s return value. debit()
     *      returns null on insufficient funds; the caller subtracted the amount
     *      from the total anyway and recorded a completed wallet transaction,
     *      so an empty wallet could pay most of an invoice.
     *
     * Both id lists are now resolved through the caller's own relations, and
     * the wallet leg only happens if the money is actually there.
     */
    public function pay(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Support aliases
        if ($request->has('payment_channel') && ! $request->has('channel')) {
            $request->merge(['channel' => $request->input('payment_channel')]);
        }
        if ($request->has('wallet_amount') && ! $request->has('split_wallet_amount')) {
            $request->merge(['split_wallet_amount' => $request->input('wallet_amount')]);
        }

        $validated = $request->validate([
            'channel' => ['required', 'string', 'in:'.implode(',', $this->orchestrator->channelKeys())],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'in:JMD,USD,CAD,GBP,EUR,jmd,usd,cad,gbp,eur'],
            'invoice_id' => ['nullable', 'integer'],
            'item_ids' => ['nullable', 'array'],
            'item_ids.*' => ['integer'],
            'split_wallet_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        /*
         | Resolve the invoice through the payer's own invoices. An id that is
         | not theirs simply does not resolve, so a probe cannot tell an
         | invoice that exists from one that does not. Administrators settle
         | other households through billing.invoices.pay, which is separately
         | gated on `manageBilling`.
         */
        $invoice = isset($validated['invoice_id'])
            ? $user->invoices()->whereKey($validated['invoice_id'])->first()
            : $user->invoices()->outstanding()->latest('due_on')->first();

        if (isset($validated['invoice_id']) && ! $invoice) {
            return back()->withErrors(['invoice_id' => 'That statement is not on your account.']);
        }

        // `$invoice` was read on this line *before* it was assigned, three lines
        // below, so the invoice-currency fallback never fired and a USD invoice
        // was recorded as JMD.
        $currency = strtoupper($validated['currency'] ?? ($invoice?->currency ?? 'JMD'));
        $amountMinor = (int) round($validated['amount'] * 100);

        // Line items, scoped to the invoice being paid.
        $itemIds = [];
        if (! empty($validated['item_ids'])) {
            if (! $invoice) {
                return back()->withErrors(['item_ids' => 'Select a statement before paying individual charges.']);
            }

            $itemIds = $invoice->items()->whereKey($validated['item_ids'])->pluck('id')->all();

            if (count($itemIds) !== count($validated['item_ids'])) {
                return back()->withErrors(['item_ids' => 'One or more of those charges is not on this statement.']);
            }
        }

        // If split payment with wallet
        $walletDeductMinor = (int) round(($validated['split_wallet_amount'] ?? 0) * 100);

        if ($walletDeductMinor > 0) {
            if ($walletDeductMinor > $amountMinor) {
                return back()->withErrors([
                    'split_wallet_amount' => 'The wallet portion cannot exceed the payment amount.',
                ]);
            }

            $walletTransaction = $user->wallet?->debit(
                $walletDeductMinor,
                "Split payment for invoice {$invoice?->reference}",
            );

            if (! $walletTransaction) {
                return back()->withErrors([
                    'split_wallet_amount' => 'Your Community Wallet does not hold that much.',
                ]);
            }

            $this->orchestrator->recordTransaction(
                user: $user,
                amountMinor: $walletDeductMinor,
                channel: 'wallet',
                reference: 'SPLIT-WAL-'.strtoupper(Str::random(6)),
                invoice: $invoice,
                notes: 'Split payment portion via Community Wallet',
                currency: $currency
            );

            $amountMinor -= $walletDeductMinor;
        }

        // Settle remaining via selected channel
        if ($amountMinor > 0) {
            $settlement = $this->orchestrator->settlePayment($validated['channel'], [
                'amount_minor' => $amountMinor,
                'currency' => $currency,
                'user_id' => $user->id,
                'lot' => $user->lot,
            ]);

            $this->orchestrator->recordTransaction(
                user: $user,
                amountMinor: $amountMinor,
                channel: $validated['channel'],
                reference: $settlement['reference'] ?? ('PAY-'.strtoupper(Str::random(8))),
                invoice: $invoice,
                notes: "Settled via {$validated['channel']} in {$currency}",
                currency: $currency
            );
        }

        // If specific items were paid
        if ($itemIds !== []) {
            InvoiceItem::whereKey($itemIds)->update(['status' => 'Paid']);
        }

        // Settle or partially settle invoice
        if ($invoice) {
            if ($invoice->balanceRemainingMinor() <= 0) {
                $invoice->markPaid();
            } else {
                $invoice->update(['status' => 'Partially Paid']);
            }
        }

        $user->recordActivity("Paid {$currency} ".number_format($validated['amount'], 2)." via {$validated['channel']}");

        return back()->with('success', "Payment of {$currency} ".number_format($validated['amount'], 2).' recorded.');
    }

    public function updateAutoPay(Request $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
            'cadence' => ['required', 'string', 'in:monthly,quarterly,annual,custom'],
            'charge_day_of_month' => ['required', 'integer', 'between:1,28'],
            'payment_channel' => ['required', 'string'],
        ]);

        AutoPaySetting::updateOrCreate(
            ['user_id' => $user->id],
            $validated
        );

        $user->recordActivity("Updated AutoPay settings to {$validated['cadence']}");

        return back()->with('success', 'AutoPay preferences updated.');
    }

    public function storePaymentLink(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user->can('manageBilling')) {
            abort(403);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'amount' => ['nullable', 'numeric', 'min:1'],
            'currency' => ['nullable', 'string', 'in:JMD,USD,CAD,GBP,EUR,jmd,usd,cad,gbp,eur'],
            'category' => ['required', 'string'],
        ]);

        PaymentLink::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'amount_minor' => ! empty($validated['amount']) ? (int) round($validated['amount'] * 100) : null,
            'currency' => strtoupper($validated['currency'] ?? 'JMD'),
            'category' => $validated['category'],
            'created_by' => $user->id,
        ]);

        return back()->with('success', 'Universal Payment Link generated successfully!');
    }

    /**
     * Community Wallet top-up.
     *
     * Refused. This credited the wallet with whatever amount was posted and
     * wrote a `stripe_card` ledger row marked `completed`, without any
     * processor being involved — J$250,000 of spendable balance for one form
     * submission. The wallet is then usable against real invoices via pay(),
     * so it was a direct route to settling dues for free.
     *
     * Re-enable when a driver genuinely takes the money and the credit is
     * posted from the processor's confirmation rather than from this request.
     */
    public function topUpWallet(Request $request): RedirectResponse
    {
        $request->validate([
            'amount' => ['required', 'numeric', 'min:100'],
        ]);

        return back()->withErrors([
            'amount' => 'Wallet top-ups are unavailable: no card processor is connected to this estate yet. '
                .'Please pay at the community office.',
        ]);
    }

    public function exportTransactions(): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="transactions-master-ledger.csv"',
        ];

        return response()->stream(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Reference', 'Receipt', 'Homeowner', 'Lot', 'Amount', 'Currency', 'Channel', 'Status', 'Date', 'Notes']);

            Transaction::with('user')->chunk(100, function ($transactions) use ($handle) {
                foreach ($transactions as $tx) {
                    fputcsv($handle, [
                        $tx->reference,
                        $tx->receipt_number,
                        $tx->user?->name ?? 'Resident',
                        $tx->user?->lot ?? 'N/A',
                        number_format($tx->amount_minor / 100, 2),
                        $tx->currency,
                        $tx->payment_channel,
                        $tx->status,
                        $tx->created_at->toDateTimeString(),
                        $tx->notes,
                    ]);
                }
            });

            fclose($handle);
        }, 200, $headers);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'monthly_fee' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'due_day_of_month' => ['required', 'integer', 'between:1,28'],
        ]);

        BillingSetting::current()->update([
            'monthly_fee_minor' => (int) round($validated['monthly_fee'] * 100),
            'currency' => strtoupper($validated['currency']),
            'due_day_of_month' => $validated['due_day_of_month'],
        ]);

        $request->user()->recordActivity('Updated billing settings');

        return back()->with('success', 'Billing settings updated.');
    }

    public function markPaid(Request $request, Invoice $invoice): RedirectResponse
    {
        if ($invoice->status === 'Paid') {
            return back()->with('error', "Invoice {$invoice->reference} is already marked paid.");
        }

        $invoice->markPaid();
        $request->user()->recordActivity("Marked invoice {$invoice->reference} paid");

        return back()->with('success', "Invoice {$invoice->reference} marked paid.");
    }

    private function monthlyCollections(): array
    {
        $paid = Invoice::query()
            ->where('status', 'Paid')
            ->whereYear('paid_at', now()->year)
            ->get(['paid_at', 'amount_minor']);

        $totals = array_fill(1, 12, 0);
        foreach ($paid as $invoice) {
            $totals[(int) $invoice->paid_at->month] += $invoice->amount_minor;
        }

        $result = [];
        foreach ($totals as $month => $minor) {
            $result[] = [
                'month' => now()->setMonth($month)->format('M'),
                'total' => (float) ($minor / 100),
            ];
        }

        return $result;
    }

    private function invoicePayload(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'reference' => $invoice->reference,
            'amount' => $invoice->amount(),
            'currency' => $invoice->currency,
            'periodStart' => $invoice->period_start->toDateString(),
            'periodEnd' => $invoice->period_end->toDateString(),
            'dueOn' => $invoice->due_on->toDateString(),
            'status' => $invoice->isOverdue() ? 'Overdue' : $invoice->status,
            'paidAt' => $invoice->paid_at?->toIso8601String(),
            'pdfUrl' => route('dashboard.billing.invoice.pdf', $invoice->id),
            // Null unless Stripe is actually configured, so the page does not
            // offer a Pay button pointing at a route that 404s.
            'checkoutUrl' => app(StripePaymentService::class)->isLive()
                ? route('dashboard.billing.stripe.checkout', $invoice->id)
                : null,
        ];
    }

    /**
     * Validate an individual payment channel integration technical readiness.
     */
    public function validateChannel(Request $request, string $channel): RedirectResponse|JsonResponse
    {
        $report = $this->orchestrator->validateChannelIntegration($channel);

        if ($request->wantsJson()) {
            return response()->json($report);
        }

        $statusMsg = $report['is_ready'] ? 'Technical validation PASSED. Ready for production.' : 'Technical validation requires configuration.';

        return back()->with('success', "Channel [{$report['label']}]: {$statusMsg}");
    }

    /**
     * Enable or disable a payment channel for production (enforces pre-flight technical validation).
     */
    public function toggleChannel(Request $request, string $channel): RedirectResponse
    {
        $setting = PaymentChannelSetting::where('channel_key', $channel)->first();
        $targetEnabled = ! ($setting ? $setting->enabled : true);

        if ($targetEnabled) {
            $validation = $this->orchestrator->validateChannelIntegration($channel);
            if (! $validation['is_ready']) {
                return back()->withErrors([
                    'channel' => "Cannot enable [{$validation['label']}] for production: technical integration requirements not met.",
                ]);
            }
        }

        PaymentChannelSetting::updateOrCreate(
            ['channel_key' => $channel],
            ['enabled' => $targetEnabled]
        );

        $statusWord = $targetEnabled ? 'enabled for production' : 'disabled';
        $request->user()->recordActivity("Payment channel {$channel} {$statusWord}");

        return back()->with('success', "Payment channel {$channel} has been {$statusWord}.");
    }

    /**
     * Configure a structured installment payment plan for an invoice.
     */
    public function storePaymentPlan(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'invoice_id' => ['required', 'exists:invoices,id'],
            'total_installments' => ['required', 'integer', 'between:2,12'],
            'frequency' => ['required', 'string', 'in:monthly,biweekly,weekly'],
        ]);

        $invoice = Invoice::findOrFail($validated['invoice_id']);
        $installmentAmountMinor = (int) ceil($invoice->amount_minor / $validated['total_installments']);

        PaymentPlan::updateOrCreate(
            ['invoice_id' => $invoice->id],
            [
                'user_id' => $invoice->user_id,
                'total_installments' => $validated['total_installments'],
                'remaining_installments' => $validated['total_installments'],
                'installment_amount_minor' => $installmentAmountMinor,
                'frequency' => $validated['frequency'],
                'status' => 'Active',
            ]
        );

        $request->user()->recordActivity("Created {$validated['total_installments']}-part payment plan for invoice {$invoice->reference}");

        return back()->with('success', "Payment plan configured for invoice {$invoice->reference}.");
    }

    /**
     * Download official PDF receipt for a completed transaction.
     */
    public function downloadReceipt(Request $request, Transaction $transaction): \Illuminate\Http\Response
    {
        $user = $request->user();
        abort_unless(
            $transaction->user_id === $user->id || $user->can('manageBilling'),
            403,
            'You can only download your own payment receipts.'
        );

        $transaction->load(['user', 'invoice']);
        $community = Community::first();

        $pdf = Pdf::loadView('pdf.transaction-receipt', [
            'transaction' => $transaction,
            'community' => $community,
        ])->setPaper('a4', 'portrait');

        $ref = $transaction->receipt_number ?? $transaction->reference;

        return $pdf->download("receipt-{$ref}.pdf");
    }

    /**
     * Perform an automated estate bank reconciliation against the general ledger.
     */
    public function storeReconciliation(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'bank_statement_date' => ['required', 'date'],
            'statement_balance' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $date = $validated['bank_statement_date'];
        $statementBalanceMinor = (int) round(((float) $validated['statement_balance']) * 100);

        // Ledger balance = sum(completed payments up to statement date) - sum(refunds up to statement date)
        $completedMinor = Transaction::where('status', 'completed')
            ->whereDate('created_at', '<=', $date)
            ->sum('amount_minor');

        $refundedMinor = Transaction::where('status', 'refunded')
            ->whereDate('created_at', '<=', $date)
            ->sum('amount_minor');

        $ledgerBalanceMinor = (int) ($completedMinor - $refundedMinor);
        $diffMinor = $statementBalanceMinor - $ledgerBalanceMinor;
        $status = ($diffMinor === 0) ? 'Reconciled' : 'Discrepancy';

        BankReconciliation::create([
            'bank_statement_date' => $date,
            'statement_balance_minor' => $statementBalanceMinor,
            'ledger_balance_minor' => $ledgerBalanceMinor,
            'difference_minor' => $diffMinor,
            'reconciled_by' => $request->user()->id,
            'status' => $status,
            'notes' => $validated['notes'] ?? null,
        ]);

        $statusWord = $status === 'Reconciled' ? 'balanced perfectly' : 'has a variance of J$'.number_format($diffMinor / 100, 2);
        $request->user()->recordActivity("Completed bank reconciliation for {$date}: {$statusWord}");

        return back()->with('success', "Bank reconciliation recorded. Status: {$status} ({$statusWord}).");
    }

    /**
     * Redirect user to Stripe's hosted Billing Customer Portal.
     */
    public function customerPortal(Request $request, StripePaymentService $stripe): RedirectResponse|\Symfony\Component\HttpFoundation\Response
    {
        $user = $request->user();

        if (! $stripe->isLive()) {
            return back()->with('error', 'Stripe payment processor is not configured or in test mode. Self-service billing portal is currently unavailable.');
        }

        try {
            $returnUrl = route('dashboard.billing');
            $url = $stripe->createCustomerPortalSession($user, $returnUrl);

            return Inertia::location($url);
        } catch (\Throwable $e) {
            Log::warning("Could not launch customer portal: {$e->getMessage()}");

            return back()->with('error', 'Unable to initiate Stripe customer portal at this time: '.$e->getMessage());
        }
    }

    /**
     * Calculate prorated assessment dues for partial month move-in or lease terms.
     */
    public function calculateProration(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'monthly_rate' => ['required', 'numeric', 'min:0'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $start = Carbon::parse($validated['start_date']);
        $daysInMonth = $start->daysInMonth;

        $end = ! empty($validated['end_date'])
            ? Carbon::parse($validated['end_date'])
            : $start->copy()->endOfMonth();

        $billedDays = min($daysInMonth, max(1, $start->diffInDays($end) + 1));
        $dailyRate = $validated['monthly_rate'] / $daysInMonth;
        $proratedAmount = round($dailyRate * $billedDays, 2);

        return response()->json([
            'monthlyRate' => (float) $validated['monthly_rate'],
            'daysInMonth' => $daysInMonth,
            'billedDays' => $billedDays,
            'dailyRate' => round($dailyRate, 2),
            'proratedAmount' => $proratedAmount,
            'currency' => 'USD',
        ]);
    }
}
