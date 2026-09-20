<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\BillingSetting;
use App\Models\Invoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Backs src/app/dashboard/billing/page.tsx, billing-summary.tsx and
 * admin-billing.tsx.
 *
 * This page carried the most invented content in the application. The resident
 * half had a card-payment form that collected a name, showed four identical
 * generic icons as though they were card brands, and stated that
 * "Card information is securely collected by Stripe (PCI-DSS Level 1
 * Compliant)" above a Pay button with no handler. The admin half showed four
 * fabricated transactions, a twelve-month collections chart of made-up figures,
 * and totals derived from them. All of that is gone; the figures here are
 * aggregated from the invoices table.
 *
 * Two working endpoints also existed that the UI could not reach: `markPaid`,
 * and the currency and due-day halves of `updateSettings`.
 *
 * A Stripe integration has since been added alongside this controller
 * (StripeCheckoutController, StripePaymentService) and `checkoutUrl` below
 * points at it.
 *
 * DO NOT enable that path in production as it stands. `config/services.php`
 * declares no `stripe` key, so `config('services.stripe.secret')` is null and
 * StripePaymentService always takes its demo branch — which marks the invoice
 * Paid without taking any payment. Configure the key, and settle invoices from
 * a verified `checkout.session.completed` webhook rather than from the browser
 * hitting the success URL, before this is exposed to residents.
 */
class BillingController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $isAdmin = $user->can('manageBilling');
        $settings = BillingSetting::current();

        return Inertia::render('Dashboard/Billing', [
            'settings' => [
                'monthlyFee' => $settings->monthlyFee(),
                'currency' => $settings->currency,
                'dueDayOfMonth' => $settings->due_day_of_month,
            ],

            'myInvoices' => $user->invoices()
                ->latest('period_start')
                ->paginate(12)
                ->withQueryString()
                ->through(fn (Invoice $i) => $this->invoicePayload($i)),

            'summary' => [
                'outstanding' => (float) ($user->invoices()->outstanding()->sum('amount_minor') / 100),
                // Replaces a hardcoded "Year-to-Date Payments (2025)" heading
                // and four fabricated payments.
                'paidThisYear' => (float) ($user->invoices()
                    ->where('status', 'Paid')
                    ->whereYear('paid_at', now()->year)
                    ->sum('amount_minor') / 100),
                'paidCountThisYear' => $user->invoices()
                    ->where('status', 'Paid')
                    ->whereYear('paid_at', now()->year)
                    ->count(),
                'year' => (int) now()->year,
            ],

            // Estate-wide figures only for those who may manage billing.
            'adminSummary' => $isAdmin ? [
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
            ] : null,

            'monthlyCollections' => $isAdmin ? $this->monthlyCollections() : null,

            'invoices' => $isAdmin
                ? Invoice::with('user:id,display_name,lot')
                    ->latest('due_on')
                    ->paginate(20)
                    ->withQueryString()
                    ->through(fn (Invoice $i) => [
                        ...$this->invoicePayload($i),
                        'homeowner' => $i->user?->display_name,
                        'lot' => $i->user?->lot,
                    ])
                : null,

            'canManage' => $isAdmin,
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'monthly_fee' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'due_day_of_month' => ['required', 'integer', 'between:1,28'],
        ]);

        BillingSetting::current()->update([
            // Money is stored in minor units. round() before the cast, or
            // (int) truncates 4999.999999 — a float artefact of × 100 — to 4999.
            'monthly_fee_minor' => (int) round($validated['monthly_fee'] * 100),
            'currency' => strtoupper($validated['currency']),
            'due_day_of_month' => $validated['due_day_of_month'],
        ]);

        $request->user()->recordActivity('Updated billing settings');

        return back()->with('success', 'Billing settings updated.');
    }

    public function markPaid(Request $request, Invoice $invoice): RedirectResponse
    {
        /*
         | Marking an already-paid invoice again would overwrite paid_at and
         | move the payment into the current month, quietly corrupting the
         | collections figures. The button is hidden for paid invoices; this is
         | the half that holds if the request arrives anyway.
         */
        if ($invoice->status === 'Paid') {
            return back()->with('error', "Invoice {$invoice->reference} is already marked paid.");
        }

        $invoice->markPaid();

        $request->user()->recordActivity("Marked invoice {$invoice->reference} paid");

        return back()->with('success', "Invoice {$invoice->reference} marked paid.");
    }

    /**
     * Collections per calendar month for the current year.
     *
     * Replaces `monthlyCollectionsData`, twelve hardcoded figures. Pulled in one
     * query and grouped in PHP rather than with a database date function, so it
     * behaves the same on SQLite and MySQL.
     *
     * @return array<int, array{month: string, total: float}>
     */
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

    /** @return array<string, mixed> */
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
            // Overdue is derived from due_on rather than stored, so a row never
            // sits at "Unpaid" after its date has passed.
            'status' => $invoice->isOverdue() ? 'Overdue' : $invoice->status,
            'paidAt' => $invoice->paid_at?->toIso8601String(),
            'pdfUrl' => route('dashboard.billing.invoice.pdf', $invoice->id),
            'checkoutUrl' => route('dashboard.billing.stripe.checkout', $invoice->id),
        ];
    }
}
