<?php

namespace App\Http\Controllers;

use App\Actions\Billing\PayThroughPublicLinkAction;
use App\Http\Requests\Billing\ProcessPaymentLinkRequest;
use App\Models\BrandingSetting;
use App\Models\Community;
use App\Models\PaymentLink;
use App\Services\QrCodePng;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Public, unauthenticated payment links.
 *
 * Disabled unless `PAYMENT_PUBLIC_LINKS_ENABLED` is set. `process()` records
 * a payment awaiting transfer, which counts only once the office logs it
 * received and verifies it in a reconciliation (PayThroughPublicLinkAction);
 * nothing here settles money.
 *
 * See config/payments.php.
 */
class UniversalPaymentLinkController extends Controller
{
    /** Public links stay off until a driver genuinely settles funds. */
    protected function ensureEnabled(): void
    {
        abort_unless(config('payments.public_links_enabled'), 404);
    }

    public function show(string $token): View
    {
        $this->ensureEnabled();

        $paymentLink = PaymentLink::where('token', $token)->where('active', true)->firstOrFail();
        $community = Community::first();
        $branding = BrandingSetting::current();

        return view('blade.public-pay', [
            'paymentLink' => $paymentLink,
            'community' => $community,
            'branding' => $branding,
        ]);
    }

    /**
     * Printable poster whose QR opens the public payment page.
     *
     * The QR is rendered here and inlined as a PNG data: URI, which DomPDF
     * draws with remote fetching disabled. 220 px matches .qr-img in
     * pdf/qr-poster.blade.php; margin 4 is the standard quiet zone for print.
     */
    public function poster(string $token, QrCodePng $qr): Response
    {
        $this->ensureEnabled();

        $paymentLink = PaymentLink::where('token', $token)->firstOrFail();
        $community = Community::first();

        $pdf = Pdf::loadView('pdf.qr-poster', [
            'paymentLink' => $paymentLink,
            'community' => $community,
            'qrImage' => 'data:image/png;base64,'.base64_encode($qr->render($paymentLink->publicUrl(), 220, 4)),
        ]);

        return $pdf->stream('payment-poster-'.$token.'.pdf');
    }

    /** Records the payment for the office to confirm; see PayThroughPublicLinkAction. */
    public function process(ProcessPaymentLinkRequest $request, string $token, PayThroughPublicLinkAction $pay): RedirectResponse
    {
        $this->ensureEnabled();

        $paymentLink = PaymentLink::where('token', $token)->where('active', true)->firstOrFail();
        $payment = $pay->execute($paymentLink, $request->validated());

        return back()->with(
            'status',
            "Payment {$payment->transaction_id} of ".$paymentLink->formattedAmount().' recorded. The community office will confirm it.',
        );
    }
}
