<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\Billing\DonateToFundraiserAction;
use App\Actions\Billing\RefundDonationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\AddFundraiserUpdateRequest;
use App\Http\Requests\Billing\DonateFundraiserRequest;
use App\Http\Requests\Billing\RefundDonationRequest;
use App\Http\Requests\Billing\StoreFundraiserRequest;
use App\Http\Requests\Billing\UpdateFundraiserRequest;
use App\Models\Community;
use App\Models\Donation;
use App\Models\Fundraiser;
use App\Services\Billing\FundraisingPresenter;
use App\Services\StripePaymentService;
use App\Support\Csv;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FundraisingController extends Controller
{
    private const DONATION_EXPORT_HEADER = [
        'Receipt Number', 'Date', 'Campaign Title', 'Beneficiary', 'Donor Name', 'Public Name',
        'Is Anonymous', 'User Email', 'Amount', 'Currency', 'Payment Channel', 'Is Recurring',
        'Frequency', 'Status', 'Refunded At', 'Refund Reason',
    ];

    public function index(Request $request, FundraisingPresenter $presenter): Response
    {
        return Inertia::render('Dashboard/Fundraising', $presenter->props($request->user()));
    }

    public function store(StoreFundraiserRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        Fundraiser::create([
            ...collect($validated)->only(['title', 'description', 'start_date', 'end_date', 'status'])->all(),
            'beneficiary' => $validated['beneficiary'] ?? 'Cypress Bay Community Improvement Fund',
            'goal_minor' => (int) round($validated['goal'] * 100),
            'goal_currency' => 'JMD',
            'cover_image_url' => $validated['cover_image_url'] ?? null,
            'images' => $request->images(),
            'matching_sponsor' => $validated['matching_sponsor'] ?? null,
            'suggested_amounts' => Fundraiser::DEFAULT_SUGGESTED_AMOUNTS,
            'fund_allocation' => Fundraiser::DEFAULT_FUND_ALLOCATION,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Fundraiser created.');
    }

    public function update(UpdateFundraiserRequest $request, Fundraiser $fundraiser): RedirectResponse
    {
        $validated = $request->validated();

        if ($validated['status'] === 'Active' && $fundraiser->end_date->isPast()) {
            return back()->withErrors([
                'status' => 'This fundraiser ended on '.$fundraiser->end_date->toFormattedDateString().'. Extend the end date before opening it.',
            ]);
        }

        $fundraiser->update($validated);
        $request->user()->recordActivity("Set fundraiser {$fundraiser->title} to {$validated['status']}");

        return back()->with('success', "\"{$fundraiser->title}\" is now {$validated['status']}.");
    }

    /** Give to a campaign; see DonateToFundraiserAction. */
    public function donate(DonateFundraiserRequest $request, Fundraiser $fundraiser, DonateToFundraiserAction $donate): SymfonyResponse
    {
        $instruction = $donate->execute(
            $fundraiser,
            $request->user(),
            $request->validated(),
            $request->boolean('is_anonymous'),
            $request->boolean('is_recurring'),
        );

        if ($instruction->isRedirect()) {
            return Inertia::location($instruction->url);
        }

        if ($instruction->type === 'completed') {
            return back()->with('success', 'Thank you for your generous donation! Your official receipt is available.');
        }

        return back()->with('success', "Thank you! Your gift {$instruction->payment->transaction_id} is recorded as pending and will count towards the campaign once the community office has received and verified it.");
    }

    /** Administrator refund of a confirmed donation; see RefundDonationAction. */
    public function refund(RefundDonationRequest $request, Donation $donation, RefundDonationAction $refund): RedirectResponse
    {
        $refund->execute($donation, $request->user(), $request->validated('reason'));

        return back()->with('success', "Donation #{$donation->receipt_number} has been refunded.");
    }

    /** Every donation as CSV, for reporting and compliance. */
    public function exportDonations(): StreamedResponse
    {
        $rows = Donation::with(['fundraiser', 'user'])->latest('donated_at')->lazy()->map(fn (Donation $d) => [
            $d->receipt_number,
            $d->donated_at->toDateTimeString(),
            $d->fundraiser?->title ?? 'N/A',
            $d->fundraiser?->beneficiary ?? 'N/A',
            $d->donor_name ?? ($d->user?->display_name ?? 'N/A'),
            $d->publicDonorName(),
            $d->is_anonymous ? 'Yes' : 'No',
            $d->user?->email ?? 'N/A',
            number_format($d->amount(), 2, '.', ''),
            $d->currency,
            $d->payment_channel ?? 'card',
            $d->is_recurring ? 'Yes' : 'No',
            $d->frequency ?? 'one-time',
            $d->status ?? 'completed',
            $d->refunded_at?->toDateTimeString() ?? '',
            $d->refund_reason ?? '',
        ]);

        return Csv::download('fundraising-donations-'.date('Ymd-His').'.csv', self::DONATION_EXPORT_HEADER, $rows);
    }

    /**
     * A donation receipt, for the donor and fundraiser managers (DonationPolicy).
     * Pending and rejected gifts have none yet.
     */
    public function receipt(Donation $donation): HttpResponse
    {
        $this->authorize('viewReceipt', $donation);

        abort_if(
            in_array($donation->status, ['pending', 'rejected'], true),
            409,
            'A receipt is issued once the community office confirms the donation.',
        );

        return Pdf::loadView('pdf.donation-receipt', [
            'donation' => $donation->load('fundraiser'),
            'community' => Community::first(),
        ])->stream('donation-receipt-'.($donation->receipt_number ?? $donation->id).'.pdf');
    }

    /**
     * The donor returning from Stripe Checkout. The session is checked with
     * Stripe before anything is recorded; see completeDonation().
     */
    public function donationStripeSuccess(Request $request, Fundraiser $fundraiser, StripePaymentService $stripe): RedirectResponse
    {
        abort_unless($stripe->isLive(), 404);

        $validated = $request->validate([
            'session_id' => ['required', 'string', 'max:255'],
        ]);

        if (! $stripe->completeDonation($fundraiser, $request->user(), $validated['session_id'])) {
            return redirect()->route('dashboard.fundraising')->with(
                'error',
                'We could not confirm your donation with Stripe. If you were charged, please contact the community office.',
            );
        }

        return redirect()->route('dashboard.fundraising')
            ->with('success', "Thank you for supporting \"{$fundraiser->title}\"! Your receipt is ready.");
    }

    /** The donor backed out of Stripe Checkout. */
    public function donationStripeCancel(Fundraiser $fundraiser, StripePaymentService $stripe): RedirectResponse
    {
        abort_unless($stripe->isLive(), 404);

        return redirect()->route('dashboard.fundraising')
            ->with('info', 'Donation cancelled. You have not been charged.');
    }

    public function addUpdate(AddFundraiserUpdateRequest $request, Fundraiser $fundraiser): RedirectResponse
    {
        $validated = $request->validated();

        $fundraiser->updates()->create($validated);

        return back()->with('success', 'Project update posted.');
    }
}
