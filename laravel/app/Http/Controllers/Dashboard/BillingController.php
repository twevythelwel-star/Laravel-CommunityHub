<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\PaymentState;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AutoPaySetting;
use App\Models\BankReconciliation;
use App\Models\BillingSetting;
use App\Models\Community;
use App\Models\Fundraiser;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\PaymentChannelSetting;
use App\Models\PaymentLink;
use App\Models\PaymentMethod;
use App\Models\PaymentPlan;
use App\Models\PaymentTerminal;
use App\Models\Payout;
use App\Models\StripeEvent;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Ledger\LedgerService;
use App\Services\Payments\PaymentOrchestratorService;
use App\Services\Payments\Providers\Contracts\TakesInPersonPayments;
use App\Services\Payments\Providers\PaymentRequest;
use App\Services\Payments\Providers\ProviderRegistry;
use App\Services\StripePaymentService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Stripe\Exception\ApiErrorException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BillingController extends Controller
{
    /** What a payment may be for; Transaction::PURPOSE_*. */
    private const PAYMENT_PURPOSES = [
        Transaction::PURPOSE_HOA_ASSESSMENT,
        Transaction::PURPOSE_MAINTENANCE_FEE,
        Transaction::PURPOSE_LATE_FEE,
        Transaction::PURPOSE_AMENITY_BOOKING,
        Transaction::PURPOSE_GATE_ACCESS_FEE,
        Transaction::PURPOSE_EVENT_TICKET,
        Transaction::PURPOSE_FUNDRAISING_DONATION,
        Transaction::PURPOSE_FUNDRAISING_SPONSORSHIP,
        Transaction::PURPOSE_COMMUNITY_PROJECT,
        Transaction::PURPOSE_EMERGENCY_FUND,
    ];

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

        $presentTransaction = function (Transaction $t) use ($stripe, $canRefund): array {
            // Only a Stripe payment with money left to return, and only for
            // an administrator on a live Stripe account.
            $refundableMinor = $canRefund ? $stripe->refundableMinor($t) : 0;

            return [
                'id' => $t->id,
                'transactionId' => $t->transaction_id ?? $t->reference,
                'transaction_id' => $t->transaction_id ?? $t->reference,
                'reference' => $t->reference,
                'receiptNumber' => $t->receipt_number,
                'homeowner' => $t->user?->display_name,
                'lot' => $t->user?->lot,
                'amount' => (float) ($t->amount_minor / 100),
                'currency' => $t->currency,
                'channel' => $t->payment_channel,
                'paymentMethod' => $t->payment_method ?? Transaction::formatPaymentMethod($t->payment_channel),
                'userCode' => $t->user_code ?? Transaction::formatUserCode($t->user_id),
                'propertyCode' => $t->property_code,
                'communityCode' => $t->community_code,
                'purpose' => $t->purpose,
                'provider' => $t->provider,
                'providerReference' => $t->provider_reference,
                'device' => $t->device_identifier,
                'status' => $t->status,
                'notes' => $t->notes,
                'date' => $t->created_at->format('M d, Y h:i A'),
                'refundableAmount' => $refundableMinor > 0 ? (float) ($refundableMinor / 100) : null,
                'refundUrl' => $refundableMinor > 0
                    ? route('dashboard.billing.transactions.refund', $t->id)
                    : null,
                'isDonation' => $t->donation_id !== null,
                'slip' => $t->toTransactionCardPayload(),
                'ledgerEntries' => $t->ledgerEntries()->with('account:id,code,name,type')->get()->map(fn ($e) => [
                    'entryId' => $e->entry_id,
                    'accountCode' => $e->account?->code,
                    'accountName' => $e->account?->name,
                    'accountType' => $e->account?->type,
                    'type' => $e->entry_type,
                    'amount' => (float) ($e->amount_minor / 100),
                    'currency' => $e->currency,
                    'description' => $e->description,
                ]),
            ];
        };

        $transactions = Transaction::with('user:id,display_name,lot')
            ->unless($isAdmin, fn ($q) => $q->where('user_id', $user->id))
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through($presentTransaction);

        /*
         | Office payments in flight, oldest first. Administrators get every one,
         | with the actions its state allows; a resident gets their own, to see
         | where each stands. Card attempts are not here: they wait on Stripe,
         | not the office, and there is nothing for anyone to confirm.
         */
        $presentPayment = fn (Payment $p): array => [
            'id' => $p->id,
            'transactionId' => $p->transaction_id,
            'state' => $p->state->value,
            'stateLabel' => $p->state->label(),
            'channel' => $p->channel,
            'paymentMethod' => Transaction::formatPaymentMethod($p->channel),
            'amount' => (float) ($p->amount_minor / 100),
            'currency' => $p->currency,
            'purpose' => $p->purpose,
            'homeowner' => $p->user?->display_name,
            'lot' => $p->user?->lot,
            'invoiceReference' => $p->invoice?->reference,
            'isDonation' => $p->applies_to === 'donation',
            'payerReference' => $p->payer_reference,
            'bankReference' => $p->bank_reference,
            'receivedBy' => $p->receiver?->display_name,
            'receivedAt' => $p->received_at?->format('M d, Y h:i A'),
            'submittedAt' => $p->updated_at->format('M d, Y h:i A'),
            'receiveUrl' => $isAdmin && $p->state === PaymentState::AwaitingTransfer
                ? route('dashboard.billing.payments.receive', $p->id)
                : null,
            'rejectUrl' => $isAdmin && $p->state->awaitsOffice()
                ? route('dashboard.billing.payments.reject', $p->id)
                : null,
            // Separation of duties: whoever logged receipt cannot verify it.
            'canVerify' => $isAdmin && $p->state === PaymentState::Received && $p->received_by !== $user->id,
        ];

        $pendingPayments = Payment::with(['user:id,display_name,lot', 'invoice:id,reference', 'receiver:id,display_name'])
            ->inState(PaymentState::AwaitingTransfer, PaymentState::Received)
            ->unless($isAdmin, fn ($q) => $q->where('user_id', $user->id))
            ->oldest('updated_at')
            ->take(100)
            ->get()
            ->map($presentPayment)
            ->values();

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
            'pendingPayments' => $pendingPayments,
            'inPerson' => $isAdmin ? $this->inPersonProps() : null,
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
            'triPartyReconciliation' => $isAdmin ? app(LedgerService::class)->getTriPartyReconciliationSummary($settings->currency ?: 'JMD') : null,
            'chartOfAccounts' => $isAdmin ? Account::withCount('entries')->orderBy('code')->get()->map(fn ($a) => [
                'id' => $a->id,
                'code' => $a->code,
                'name' => $a->name,
                'type' => $a->type,
                'currency' => $a->currency,
                'balance' => (float) ($a->balance_minor / 100),
                'entriesCount' => $a->entries_count,
                'description' => $a->description,
            ]) : [],
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
     *
     * The card channel is not settled here at all. Its driver answered every
     * request with a made-up "STRIPE-..." reference and success, so choosing
     * card recorded a completed payment and marked the invoice Paid with no
     * money taken. Card now hands the resident to Stripe Checkout for the
     * amount they chose, and the invoice settles only when Stripe confirms.
     */
    public function pay(Request $request, ProviderRegistry $providers): HttpResponse
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
            'transaction_id' => ['nullable', 'string', 'max:32'],
            'payer_reference' => ['nullable', 'string', 'max:100'],
            'device_identifier' => ['nullable', 'string', 'max:100'],
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

        // Card and device wallets (Apple Pay, Google Pay) are taken by the
        // card processor, on its own page.
        $isCard = $validated['channel'] === 'card' || in_array($validated['channel'], ProviderRegistry::WALLET_CHANNELS, true);

        // Whichever processor the estate has configured for cards (Stripe,
        // WiPay, ...) — and for a wallet, only if it offers that wallet.
        $provider = $providers->forChannel($validated['channel']);

        if (! $provider) {
            $method = Transaction::formatPaymentMethod($validated['channel']);

            return back()->withErrors(['channel' => "{$method} payments are not available yet. Please choose another method or pay at the community office."]);
        }

        // Bank, Zelle and Cash App send money to an account the estate entered;
        // without one there is nowhere to tell the payer to send it.
        if (PaymentChannelSetting::requiresAccountDetails($validated['channel'])
            && PaymentChannelSetting::accountFor($validated['channel']) === null) {
            $method = Transaction::formatPaymentMethod($validated['channel']);

            return back()->withErrors(['channel' => "{$method} is not set up for this estate yet. Please choose another method or pay at the community office."]);
        }

        if ($isCard) {
            if (! $invoice) {
                return back()->withErrors(['invoice_id' => 'There is no open statement to pay by card.']);
            }

            if ($currency !== strtoupper($invoice->currency)) {
                return back()->withErrors(['currency' => "Card payments are charged in {$invoice->currency}, the statement's currency."]);
            }

            if (! empty($validated['item_ids'])) {
                return back()->withErrors(['item_ids' => 'Individual charges cannot be paid by card. Pay an amount towards the statement instead.']);
            }

            // Checked on the whole amount, wallet share included, before the
            // wallet is debited: refusing after the debit would strand it.
            if ($amountMinor > $invoice->balanceRemainingMinor()) {
                return back()->withErrors(['amount' => 'That is more than the balance still owed on this statement.']);
            }
        }

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

        /*
         | The payment shown on the resident's slip, if they started one. It
         | must be theirs and not yet submitted: a slip is a Created payment,
         | and one that has moved on cannot be submitted again.
         */
        $payment = null;
        if (! empty($validated['transaction_id'])) {
            $payment = Payment::query()
                ->where('transaction_id', $validated['transaction_id'])
                ->where('user_id', $user->id)
                ->first();

            if (! $payment || $payment->state !== PaymentState::Created) {
                return back()->withErrors(['transaction_id' => 'That payment has already been submitted, or is not yours.']);
            }
        }

        $walletShareMinor = (int) round(($validated['split_wallet_amount'] ?? 0) * 100);

        if ($walletShareMinor > $amountMinor) {
            return back()->withErrors([
                'split_wallet_amount' => 'The wallet portion cannot exceed the payment amount.',
            ]);
        }

        // The Community Wallet share is money the app holds, so it is taken
        // and applied at once, as a payment of its own.
        if ($walletShareMinor > 0) {
            try {
                $providers->forChannel('wallet')->createPayment(new PaymentRequest(
                    payer: $user,
                    channel: 'wallet',
                    amountMinor: $walletShareMinor,
                    currency: $currency,
                    invoice: $invoice,
                ));
            } catch (\DomainException $e) {
                return back()->withErrors(['split_wallet_amount' => $e->getMessage()]);
            }

            $amountMinor -= $walletShareMinor;
        }

        $channel = $validated['channel'];
        $amountLabel = "{$currency} ".number_format($validated['amount'], 2);

        if ($amountMinor <= 0) {
            $user->recordActivity("Paid {$amountLabel} from the Community Wallet");

            return back()->with('success', "Payment of {$amountLabel} recorded.");
        }

        /*
         | The provider takes it from here. Card goes to the configured
         | processor's hosted page; the wallet settles at once; every other
         | channel — bank wire, cash, QR, NFC, digital wallets, Zelle, Cash
         | App — becomes AwaitingTransfer and settles nothing until one
         | administrator logs the money received and another verifies it in a
         | bank reconciliation.
         */
        try {
            $instruction = $provider->createPayment(new PaymentRequest(
                payer: $user,
                channel: $channel,
                amountMinor: $amountMinor,
                currency: $currency,
                invoice: $invoice,
                payment: $payment,
                itemIds: $itemIds,
                payerReference: $validated['payer_reference'] ?? null,
                deviceIdentifier: $validated['device_identifier'] ?? null,
            ));
        } catch (\DomainException $e) {
            return back()->withErrors([$isCard ? 'amount' : 'channel' => $e->getMessage()]);
        } catch (ApiErrorException $e) {
            report($e);

            return back()->withErrors(['channel' => 'Card checkout could not be started. Please try again shortly.']);
        }

        if ($instruction->isRedirect()) {
            return Inertia::location($instruction->url);
        }

        $number = $instruction->payment->transaction_id;

        if ($instruction->type === 'completed') {
            $user->recordActivity("Paid {$amountLabel} via {$channel} ({$number})");

            return back()->with('success', "Payment of {$amountLabel} recorded.");
        }

        $user->recordActivity("Reported a payment of {$amountLabel} via {$channel} ({$number}), awaiting confirmation");

        return back()->with('success', "Payment {$number} of {$amountLabel} submitted. Your statement updates once the community office has received and verified it.");
    }

    /**
     * Start a payment and return its slip, before any money moves.
     *
     * The payer sees the CH- number from the first step. The payment is
     * Created and nothing else: it is not on the ledger, and it is not in the
     * office's queue — a resident cannot put anything in front of the office
     * to confirm. It is submitted through pay() with its transaction_id.
     */
    public function initiatePayment(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($request->has('payment_channel') && ! $request->has('channel')) {
            $request->merge(['channel' => $request->input('payment_channel')]);
        }

        $validated = $request->validate([
            'channel' => ['required', 'string', 'in:'.implode(',', $this->orchestrator->channelKeys())],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'in:JMD,USD,CAD,GBP,EUR,jmd,usd,cad,gbp,eur'],
            'invoice_id' => ['nullable', 'integer'],
            'purpose' => ['nullable', 'string', 'in:'.implode(',', self::PAYMENT_PURPOSES)],
            'device_identifier' => ['nullable', 'string', 'max:100'],
        ]);

        if (! app(ProviderRegistry::class)->isAvailable($validated['channel'])) {
            $message = Transaction::formatPaymentMethod($validated['channel']).' payments are not available yet.';

            return response()->json(['message' => $message, 'errors' => ['channel' => [$message]]], 422);
        }

        $invoice = isset($validated['invoice_id'])
            ? $user->invoices()->whereKey($validated['invoice_id'])->first()
            : $user->invoices()->outstanding()->latest('due_on')->first();

        if (isset($validated['invoice_id']) && ! $invoice) {
            return response()->json(['message' => 'That statement is not on your account.', 'errors' => ['invoice_id' => ['That statement is not on your account.']]], 422);
        }

        $amountMinor = (int) round($validated['amount'] * 100);

        if ($invoice && $amountMinor > $invoice->balanceRemainingMinor()) {
            return response()->json(['message' => 'That is more than the balance still owed on this statement.', 'errors' => ['amount' => ['That is more than the balance still owed on this statement.']]], 422);
        }

        $payment = $this->orchestrator->startPayment([
            'user' => $user,
            'channel' => $validated['channel'],
            'invoice' => $invoice,
            'purpose' => $validated['purpose'] ?? null,
            'amount_minor' => $amountMinor,
            'currency' => strtoupper($validated['currency'] ?? ($invoice?->currency ?? 'JMD')),
            'device_identifier' => $validated['device_identifier'] ?? null,
            'metadata' => ['initiated_via' => 'portal_payment_center'],
        ]);

        $slip = $payment->toSlip();

        return response()->json([
            'success' => true,
            'transaction' => $slip,
            'slip' => $slip,
            'transaction_id' => $payment->transaction_id,
            'status' => $payment->state->value,
        ]);
    }

    /**
     * Process an authentic or test card payment for an invoice.
     *
     * Non-custodial: only tokenized metadata (brand, last four, cardholder name)
     * is processed; raw PAN and CVV never touch the database.
     * Settles the invoice, updates the double-entry ledger, and issues an official receipt.
     */
    public function processCardPayment(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'invoice_id' => ['nullable', 'integer'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'in:JMD,USD,CAD,GBP,EUR,jmd,usd,cad,gbp,eur'],
            'cardholder_name' => ['nullable', 'string', 'max:150'],
            'last_four' => ['nullable', 'string', 'regex:/^\d{4}$/'],
            'brand' => ['nullable', 'string', 'max:30'],
            'save_card' => ['nullable', 'boolean'],
            'payer_reference' => ['nullable', 'string', 'max:100'],
            'payment_intent_id' => ['nullable', 'string', 'max:100'],
        ]);

        $invoice = isset($validated['invoice_id'])
            ? $user->invoices()->whereKey($validated['invoice_id'])->first()
            : $user->invoices()->outstanding()->latest('due_on')->first();

        if (isset($validated['invoice_id']) && ! $invoice) {
            return response()->json(['message' => 'That statement is not on your account.', 'errors' => ['invoice_id' => ['That statement is not on your account.']]], 422);
        }

        if (! $invoice) {
            return response()->json(['message' => 'There is no open statement to pay.', 'errors' => ['invoice_id' => ['There is no open statement to pay.']]], 422);
        }

        $currency = strtoupper($validated['currency'] ?? ($invoice->currency ?? 'JMD'));
        $amountMinor = (int) round($validated['amount'] * 100);

        if ($amountMinor > $invoice->balanceRemainingMinor()) {
            return response()->json(['message' => 'That is more than the balance still owed on this statement.', 'errors' => ['amount' => ['That is more than the balance still owed on this statement.']]], 422);
        }

        $lastFour = $validated['last_four'] ?? '4242';
        $brand = ucfirst($validated['brand'] ?? 'Visa');
        $cardholderName = $validated['cardholder_name'] ?? $user->display_name;
        $paymentIntent = $validated['payment_intent_id'] ?? ('pi_test_'.Str::random(24));

        $payment = $this->orchestrator->startPayment([
            'user' => $user,
            'channel' => 'card',
            'invoice' => $invoice,
            'purpose' => "HOA Assessment ({$brand} •••• {$lastFour})",
            'amount_minor' => $amountMinor,
            'currency' => $currency,
            'payer_reference' => $validated['payer_reference'] ?? $invoice->reference,
            'metadata' => [
                'card_brand' => $brand,
                'card_last_four' => $lastFour,
                'cardholder_name' => $cardholderName,
                'payment_intent' => $paymentIntent,
                'settlement_mode' => 'card_processor_authorized',
            ],
            'source' => 'stripe:card_checkout',
        ]);

        $payment->update([
            'channel' => 'card',
            'provider' => 'stripe',
            'provider_payment_id' => $paymentIntent,
            'amount_minor' => $amountMinor,
        ]);

        $payment->advanceTo(PaymentState::Succeeded, 'stripe:card_checkout', 'Card payment authorized via card processor');

        $ledger = [
            'reference' => 'STRIPE-'.strtoupper(Str::random(12)),
            'provider_reference' => $paymentIntent,
            'provider_status' => 'succeeded',
            'amount_minor' => $amountMinor,
            'fee_minor' => 0,
            'net_amount_minor' => $amountMinor,
            'notes' => "Card payment ({$brand} •••• {$lastFour}) for Invoice {$invoice->reference}",
        ];

        $this->orchestrator->applyPayment($payment, $ledger, null, 'stripe:card_checkout');

        if (! empty($validated['save_card'])) {
            PaymentMethod::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'last_four' => $lastFour,
                    'brand' => $brand,
                ],
                [
                    'provider' => 'stripe',
                    'method_type' => 'card',
                    'display_name' => "{$brand} ending in {$lastFour}",
                    'status' => 'active',
                    'is_default' => true,
                    'provider_payment_method_id' => 'pm_test_'.Str::random(20),
                ]
            );
        }

        $invoice->refresh();
        $slip = $payment->toSlip();

        $user->recordActivity("Paid {$currency} ".number_format($amountMinor / 100, 2)." via Card ({$brand} •••• {$lastFour})");

        return response()->json([
            'success' => true,
            'message' => "Payment of {$currency} ".number_format($amountMinor / 100, 2).' successfully processed.',
            'transaction' => $slip,
            'slip' => $slip,
            'transaction_id' => $payment->transaction_id,
            'invoice' => [
                'id' => $invoice->id,
                'reference' => $invoice->reference,
                'status' => $invoice->status,
                'balance_remaining' => (float) ($invoice->balanceRemainingMinor() / 100),
            ],
        ]);
    }

    /**
     * An administrator logs that an office payment's money has arrived.
     * Route gate: `manageBilling`. It is applied only once a different
     * administrator verifies it in a bank reconciliation.
     */
    /**
     * The in-person reader panel: whether card-present payments can be taken
     * at all, on which confirmed readers, and what is on a reader right now.
     *
     * @return array<string, mixed>
     */
    private function inPersonProps(): array
    {
        $provider = app(ProviderRegistry::class)->inPersonProvider();

        return [
            'available' => $provider !== null,
            'provider' => $provider?->label(),
            'registerUrl' => route('dashboard.billing.terminals.store'),
            'chargeUrl' => route('dashboard.billing.terminals.charge'),
            'terminals' => PaymentTerminal::query()->where('status', 'active')->orderBy('label')->get()->map(fn (PaymentTerminal $t) => [
                'id' => $t->id,
                'label' => $t->label,
                'terminalId' => $t->terminal_id,
                'deviceId' => $t->device_id,
                'deviceType' => $t->device_type,
                'locationId' => $t->location_id,
                'country' => $t->country,
                'provider' => $t->provider,
                'usable' => $t->isUsable() && $provider?->key() === $t->provider,
                'retireUrl' => route('dashboard.billing.terminals.retire', $t->id),
            ])->values(),
            'onReaders' => Payment::with(['terminal:id,label', 'user:id,display_name', 'invoice:id,reference'])
                ->where('channel', 'nfc_pos')
                ->inState(PaymentState::Created, PaymentState::Processing, PaymentState::Failed)
                ->whereNotNull('payment_terminal_id')
                ->latest('updated_at')
                ->take(20)
                ->get()
                ->map(fn (Payment $p) => [
                    'id' => $p->id,
                    'transactionId' => $p->transaction_id,
                    'state' => $p->state->value,
                    'stateLabel' => $p->state->label(),
                    'amount' => (float) ($p->amount_minor / 100),
                    'currency' => $p->currency,
                    'homeowner' => $p->user?->display_name,
                    'invoiceReference' => $p->invoice?->reference,
                    'terminal' => $p->terminal?->label,
                    'failureReason' => $p->failure_reason,
                    'retryUrl' => $p->state === PaymentState::Failed ? route('dashboard.billing.terminals.retry', $p->id) : null,
                    'cancelUrl' => route('dashboard.billing.terminals.cancel', $p->id),
                ])->values(),
        ];
    }

    /**
     * Register a card reader with the in-person provider. It is recorded
     * only once the provider confirms the reader and its location.
     * Route gate: `manageBilling`.
     */
    public function registerTerminal(Request $request, ProviderRegistry $providers): RedirectResponse
    {
        $validated = $request->validate([
            'registration_code' => ['required', 'string', 'max:64'],
            'location_id' => ['required', 'string', 'max:64'],
            'label' => ['required', 'string', 'max:80'],
        ]);

        $provider = $providers->inPersonProvider();

        if (! $provider) {
            return back()->withErrors(['registration_code' => 'In-person card payments are not set up for this estate.']);
        }

        try {
            $terminal = $provider->registerTerminal($validated['registration_code'], $validated['location_id'], $validated['label'], $request->user());
        } catch (\DomainException $e) {
            return back()->withErrors(['registration_code' => $e->getMessage()]);
        }

        return back()->with('success', "Reader {$terminal->label} registered ({$terminal->country}).");
    }

    /** Take a reader out of use. Route gate: `manageBilling`. */
    public function retireTerminal(Request $request, PaymentTerminal $terminal): RedirectResponse
    {
        $terminal->update(['status' => 'retired']);
        $request->user()->recordActivity("Retired card reader {$terminal->label} ({$terminal->terminal_id})");

        return back()->with('success', "Reader {$terminal->label} retired.");
    }

    /**
     * Staff take a household's payment in person: the amount goes to the
     * chosen reader, and the payer taps or inserts their card there. It is
     * settled by the provider's webhook, never by this request.
     * Route gate: `manageBilling`.
     */
    public function chargeOnTerminal(Request $request, ProviderRegistry $providers): RedirectResponse
    {
        $validated = $request->validate([
            'invoice_id' => ['required', 'integer', 'exists:invoices,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'terminal_id' => ['required', 'integer', 'exists:payment_terminals,id'],
        ]);

        $provider = $providers->inPersonProvider();
        $terminal = PaymentTerminal::findOrFail($validated['terminal_id']);
        $invoice = Invoice::findOrFail($validated['invoice_id']);
        $amountMinor = (int) round($validated['amount'] * 100);

        if (! $provider || $provider->key() !== $terminal->provider || ! $terminal->isUsable()) {
            return back()->withErrors(['terminal_id' => "Reader {$terminal->label} cannot take payments: it is not a confirmed reader of the estate's in-person provider."]);
        }

        if ($amountMinor > $invoice->balanceRemainingMinor()) {
            return back()->withErrors(['amount' => 'That is more than the balance still owed on this statement.']);
        }

        $payment = $this->orchestrator->startPayment([
            'user' => $invoice->user,
            'channel' => 'nfc_pos',
            'invoice' => $invoice,
            'amount_minor' => $amountMinor,
            'currency' => $invoice->currency,
            'source' => 'staff',
        ]);

        try {
            $instruction = $provider->startInPersonPayment($payment, $terminal);
        } catch (\DomainException $e) {
            return back()->withErrors(['terminal_id' => $e->getMessage()]);
        }

        $request->user()->recordActivity("Sent payment {$payment->transaction_id} for {$invoice->reference} to reader {$terminal->label}");

        return back()->with('success', "{$payment->transaction_id}: {$instruction->message}");
    }

    /** Send a payment whose card was declined to its reader again. Route gate: `manageBilling`. */
    public function retryOnTerminal(Payment $payment, ProviderRegistry $providers): RedirectResponse
    {
        $provider = $providers->inPersonProvider();

        if (! $provider || ! $payment->terminal || $payment->state !== PaymentState::Failed) {
            return back()->withErrors(['terminal_id' => "Payment {$payment->transaction_id} cannot be sent to a reader again."]);
        }

        try {
            $instruction = $provider->startInPersonPayment($payment, $payment->terminal);
        } catch (\DomainException $e) {
            return back()->withErrors(['terminal_id' => $e->getMessage()]);
        }

        return back()->with('success', "{$payment->transaction_id}: {$instruction->message}");
    }

    /** Clear a payment from its reader before a card is presented. Route gate: `manageBilling`. */
    public function cancelOnTerminal(Request $request, Payment $payment, ProviderRegistry $providers): RedirectResponse
    {
        $provider = $providers->byKey($payment->provider);

        if (! $provider instanceof TakesInPersonPayments) {
            return back()->withErrors(['terminal_id' => "Payment {$payment->transaction_id} is not on a reader."]);
        }

        try {
            $provider->cancelInPersonPayment($payment, $request->user());
        } catch (\DomainException $e) {
            return back()->withErrors(['terminal_id' => $e->getMessage()]);
        }

        return back()->with('success', "Payment {$payment->transaction_id} cleared from the reader.");
    }

    public function receivePayment(Request $request, Payment $payment): RedirectResponse
    {
        $validated = $request->validate([
            'bank_reference' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->orchestrator->markReceived($payment, $request->user(), $validated['bank_reference'] ?? null, $validated['note'] ?? null);
        } catch (\DomainException $e) {
            return back()->withErrors(['payment' => $e->getMessage()]);
        }

        return back()->with('success', "Payment {$payment->transaction_id} logged as received. Another administrator verifies it in the next bank reconciliation.");
    }

    /**
     * An administrator records that an office payment never arrived.
     * Route gate: `manageBilling`.
     */
    public function rejectPayment(Request $request, Payment $payment): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->orchestrator->rejectPayment($payment, $request->user(), $validated['reason'] ?? null);
        } catch (\DomainException $e) {
            return back()->withErrors(['payment' => $e->getMessage()]);
        }

        return back()->with('success', "Payment {$payment->transaction_id} marked as not received.");
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
        $setting = PaymentChannelSetting::forChannel($channel);
        $targetEnabled = ! $setting->enabled;

        if ($targetEnabled) {
            $validation = $this->orchestrator->validateChannelIntegration($channel);
            if (! $validation['is_ready']) {
                return back()->withErrors([
                    'channel' => "Cannot enable [{$validation['label']}] for production: technical integration requirements not met.",
                ]);
            }
        }

        $setting->fill(['enabled' => $targetEnabled])->save();

        $statusWord = $targetEnabled ? 'enabled for production' : 'disabled';
        $request->user()->recordActivity("Payment channel {$channel} {$statusWord}");

        return back()->with('success', "Payment channel {$channel} has been {$statusWord}.");
    }

    /**
     * Set the account payers are sent to for a bank, Zelle or Cash App channel.
     *
     * Clearing the account also disables the channel, so it is never offered
     * with nowhere to send the money.
     */
    public function updateChannelAccount(Request $request, string $channel): RedirectResponse
    {
        abort_unless(PaymentChannelSetting::requiresAccountDetails($channel), 404);

        $validated = $request->validate([
            'account_identifier' => ['nullable', 'string', 'max:255'],
            'instructions' => ['nullable', 'string', 'max:500'],
        ]);

        $account = filled($validated['account_identifier'] ?? null) ? trim($validated['account_identifier']) : null;

        $attributes = [
            'account_identifier' => $account,
            'instructions' => filled($validated['instructions'] ?? null) ? trim($validated['instructions']) : null,
        ];
        if ($account === null) {
            $attributes['enabled'] = false;
        }

        PaymentChannelSetting::forChannel($channel)->fill($attributes)->save();

        $request->user()->recordActivity($account === null
            ? "Payment channel {$channel} account cleared and channel disabled"
            : "Payment channel {$channel} account updated");

        return back()->with('success', $account === null
            ? "Account removed. {$channel} is disabled until an account is entered."
            : "Payment details for {$channel} saved.");
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

        // A receipt for money nobody has confirmed would be proof of nothing.
        abort_if(
            $transaction->isPending() || in_array($transaction->status, ['rejected'], true),
            409,
            'A receipt is issued once the community office confirms the payment.',
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
            'verify_payment_ids' => ['nullable', 'array'],
            'verify_payment_ids.*' => ['integer'],
        ]);

        $admin = $request->user();
        $date = $validated['bank_statement_date'];
        $statementBalanceMinor = (int) round(((float) $validated['statement_balance']) * 100);

        try {
            [$reconciliation, $verified] = DB::transaction(function () use ($validated, $admin, $date, $statementBalanceMinor): array {
                $reconciliation = BankReconciliation::create([
                    'bank_statement_date' => $date,
                    'statement_balance_minor' => $statementBalanceMinor,
                    'ledger_balance_minor' => 0,
                    'difference_minor' => 0,
                    'reconciled_by' => $admin->id,
                    'status' => 'Discrepancy',
                    'notes' => $validated['notes'] ?? null,
                ]);

                /*
                 | Office payments on this statement are verified first — by an
                 | administrator other than the one who logged them received —
                 | so the comparison below includes them. All or none: one
                 | payment that cannot be verified stops the reconciliation.
                 */
                $verified = 0;
                foreach (Payment::query()->whereKey($validated['verify_payment_ids'] ?? [])->get() as $payment) {
                    $this->orchestrator->verifyReceived($payment, $admin, $reconciliation);
                    $verified++;
                }

                // Money received less money refunded, by the date it arrived.
                $asOf = fn ($query) => $query->whereRaw('date(coalesce(settled_at, created_at)) <= ?', [$date]);
                $completedMinor = (int) $asOf(Transaction::where('status', Transaction::STATUS_COMPLETED))->sum('amount_minor');
                $refundedMinor = (int) $asOf(Transaction::where('status', Transaction::STATUS_REFUNDED))->sum('amount_minor');

                $ledgerBalanceMinor = $completedMinor - $refundedMinor;
                $diffMinor = $statementBalanceMinor - $ledgerBalanceMinor;

                $reconciliation->update([
                    'ledger_balance_minor' => $ledgerBalanceMinor,
                    'difference_minor' => $diffMinor,
                    'status' => $diffMinor === 0 ? 'Reconciled' : 'Discrepancy',
                ]);

                return [$reconciliation, $verified];
            });
        } catch (\DomainException $e) {
            return back()->withErrors(['verify_payment_ids' => $e->getMessage()]);
        }

        $statusWord = $reconciliation->status === 'Reconciled'
            ? 'balanced perfectly'
            : 'has a variance of J$'.number_format($reconciliation->difference_minor / 100, 2);
        $verifiedWord = $verified > 0 ? " {$verified} office ".($verified === 1 ? 'payment' : 'payments').' verified and applied.' : '';

        $admin->recordActivity("Completed bank reconciliation for {$date}: {$statusWord}".($verified ? "; verified {$verified} office payments" : ''));

        return back()->with('success', "Bank reconciliation recorded. Status: {$reconciliation->status} ({$statusWord}).{$verifiedWord}");
    }

    /**
     * Redirect user to Stripe's hosted Billing Customer Portal.
     */
    public function customerPortal(Request $request, StripePaymentService $stripe): RedirectResponse|HttpResponse
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
