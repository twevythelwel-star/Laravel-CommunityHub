<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\User;
use App\Services\StripePaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Stripe Checkout.
 *
 * The whole flow 404s unless a real Stripe key is configured. Without a
 * processor there is nothing here to do but simulate a payment, and
 * simulating one is what made `success()` a way to clear a debt for free.
 */
class StripeCheckoutController extends Controller
{
    public function __construct(
        protected StripePaymentService $stripe
    ) {}

    /**
     * Initiate Stripe Checkout session for an invoice.
     */
    public function checkout(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->ensureEnabled();
        $this->authorizeInvoice($request->user(), $invoice);

        if ($invoice->status === 'Paid') {
            return redirect()->route('dashboard.billing')
                ->with('status', "Invoice {$invoice->reference} is already settled.");
        }

        $checkoutUrl = $this->stripe->createCheckoutSession(
            $invoice,
            route('dashboard.billing.stripe.success', ['invoice' => $invoice->id]),
            route('dashboard.billing.stripe.cancel', ['invoice' => $invoice->id]),
        );

        return redirect()->away($checkoutUrl);
    }

    /**
     * Handle return from Stripe checkout.
     *
     * This was a GET with no authorization of any kind that called
     * completePayment() and announced success unconditionally. Requesting
     *
     *     /dashboard/billing/invoices/{any}/stripe-success?session_id=anything
     *
     * marked any invoice in the estate Paid. Three things were missing and are
     * now present: the feature gate, the ownership check `checkout()` already
     * had, and any regard for whether settlement actually succeeded.
     */
    public function success(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->ensureEnabled();
        $this->authorizeInvoice($request->user(), $invoice);

        $validated = $request->validate([
            'session_id' => ['required', 'string', 'max:255'],
        ]);

        $settled = $this->stripe->completePayment($invoice, $validated['session_id']);

        if (! $settled) {
            return redirect()->route('dashboard.billing')->with(
                'error',
                "We could not confirm payment for invoice {$invoice->reference} with Stripe. "
                .'It has been left unpaid. If you were charged, contact the community office.',
            );
        }

        return redirect()->route('dashboard.billing')
            ->with('success', "Payment received for invoice {$invoice->reference}.");
    }

    /**
     * Handle cancelled checkout.
     */
    public function cancel(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->ensureEnabled();
        $this->authorizeInvoice($request->user(), $invoice);

        return redirect()->route('dashboard.billing')
            ->with('status', "Payment cancelled for invoice {$invoice->reference}. No charges were made.");
    }

    /** No processor, no checkout. See StripePaymentService::isLive(). */
    protected function ensureEnabled(): void
    {
        abort_unless($this->stripe->isLive(), 404);
    }

    /**
     * An invoice is payable by the household it bills and by the
     * administrators who issue it.
     */
    protected function authorizeInvoice(User $user, Invoice $invoice): void
    {
        abort_unless(
            $invoice->user_id === $user->id || $user->can('manageBilling'),
            403,
            'Unauthorized to pay this statement.',
        );
    }
}
