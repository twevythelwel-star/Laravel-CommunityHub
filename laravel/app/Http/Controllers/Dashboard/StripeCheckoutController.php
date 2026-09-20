<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\StripePaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StripeCheckoutController extends Controller
{
    /**
     * Initiate Stripe Checkout session for an invoice.
     */
    public function checkout(Invoice $invoice, StripePaymentService $stripeService): RedirectResponse
    {
        // Enforce access control: only invoice recipient or admins can pay
        $user = Auth::user();
        if ($invoice->user_id !== $user->id && ! $user->role->isAdministrative()) {
            abort(403, 'Unauthorized to pay this statement.');
        }

        if ($invoice->status === 'Paid') {
            return redirect()->route('dashboard.billing')
                ->with('status', "Invoice {$invoice->reference} is already settled.");
        }

        $successUrl = route('dashboard.billing.stripe.success', ['invoice' => $invoice->id]);
        $cancelUrl = route('dashboard.billing.stripe.cancel', ['invoice' => $invoice->id]);

        $checkoutUrl = $stripeService->createCheckoutSession($invoice, $successUrl, $cancelUrl);

        return redirect()->away($checkoutUrl);
    }

    /**
     * Handle return from successful Stripe checkout.
     */
    public function success(Invoice $invoice, Request $request, StripePaymentService $stripeService): RedirectResponse
    {
        $sessionId = $request->query('session_id', $invoice->stripe_session_id ?? 'unknown');

        $stripeService->completePayment($invoice, $sessionId);

        return redirect()->route('dashboard.billing')
            ->with('success', "Payment received for invoice {$invoice->reference}! Receipt generated.");
    }

    /**
     * Handle canceled checkout.
     */
    public function cancel(Invoice $invoice): RedirectResponse
    {
        return redirect()->route('dashboard.billing')
            ->with('status', "Payment cancelled for invoice {$invoice->reference}. No charges were made.");
    }
}
