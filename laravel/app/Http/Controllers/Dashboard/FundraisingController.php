<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\Donation;
use App\Models\Fundraiser;
use App\Models\FundraiserUpdate;
use App\Services\Payments\PaymentOrchestratorService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class FundraisingController extends Controller
{
    public function __construct(
        protected PaymentOrchestratorService $orchestrator
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Dashboard/Fundraising', [
            'fundraisers' => Fundraiser::with(['donations', 'updates'])
                ->orderByRaw("CASE status WHEN 'Active' THEN 1 WHEN 'Upcoming' THEN 2 WHEN 'Completed' THEN 3 ELSE 4 END")
                ->orderBy('end_date')
                ->get()
                ->map(fn (Fundraiser $f) => [
                    'id' => $f->id,
                    'title' => $f->title,
                    'description' => $f->description,
                    'beneficiary' => $f->beneficiary ?? 'Community Improvement Fund',
                    'coverImageUrl' => $f->cover_image_url,
                    'goal' => $f->goal(),
                    'raised' => $f->raised(),
                    'progress' => $f->progressPercent(),
                    'donorCount' => $f->donorCount(),
                    'currency' => $f->goal_currency,
                    'startDate' => $f->start_date->toDateString(),
                    'endDate' => $f->end_date->toDateString(),
                    'status' => $f->status,
                    'isOpen' => $f->isOpen(),
                    'allowAnonymous' => $f->allow_anonymous,
                    'allowRecurring' => $f->allow_recurring,
                    'suggestedAmounts' => $f->suggested_amounts ?? [1000, 2500, 5000, 10000],
                    'matchingSponsor' => $f->matching_sponsor,
                    'fundAllocation' => $f->fund_allocation ?? ['Project Execution' => 70, 'Materials & Equipment' => 20, 'Contingency' => 10],
                    'showLeaderboard' => $f->show_leaderboard,
                    'updates' => $f->updates->map(fn (FundraiserUpdate $u) => [
                        'id' => $u->id,
                        'title' => $u->title,
                        'content' => $u->content,
                        'imageUrl' => $u->image_url,
                        'date' => $u->created_at->format('M d, Y'),
                    ]),
                    'recentDonations' => $f->donations
                        ->sortByDesc('donated_at')
                        ->take(10)
                        ->map(fn ($d) => [
                            'id' => $d->id,
                            'donorName' => $d->publicDonorName(),
                            'amount' => $d->amount(),
                            'currency' => $d->currency,
                            'isRecurring' => $d->is_recurring,
                            'receiptNumber' => $d->receipt_number,
                            'receiptUrl' => route('dashboard.fundraising.donation.receipt', $d->id),
                            'timestamp' => $d->donated_at->toIso8601String(),
                        ])->values(),
                ]),
            'canManage' => $request->user()->can('manageFundraisers'),
            'availableChannels' => $this->orchestrator->getAvailableChannels(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'description' => ['required', 'string', 'max:5000'],
            'beneficiary' => ['nullable', 'string', 'max:150'],
            'goal' => ['required', 'numeric', 'min:1'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'status' => ['required', 'in:Active,Upcoming'],
            'matching_sponsor' => ['nullable', 'string', 'max:150'],
        ]);

        Fundraiser::create([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'beneficiary' => $validated['beneficiary'] ?? 'Cypress Bay Community Improvement Fund',
            'goal_minor' => (int) round($validated['goal'] * 100),
            'goal_currency' => 'JMD',
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'status' => $validated['status'],
            'matching_sponsor' => $validated['matching_sponsor'] ?? null,
            'suggested_amounts' => [1000, 2500, 5000, 10000],
            'fund_allocation' => ['Project Execution' => 70, 'Materials & Equipment' => 20, 'Contingency' => 10],
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Fundraiser created.');
    }

    public function update(Request $request, Fundraiser $fundraiser): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:Active,Upcoming,Completed,Canceled'],
        ]);

        if ($validated['status'] === 'Active' && $fundraiser->end_date->isPast()) {
            return back()->withErrors([
                'status' => 'This fundraiser ended on '.$fundraiser->end_date->toFormattedDateString().'. Extend the end date before opening it.',
            ]);
        }

        $fundraiser->update($validated);
        $request->user()->recordActivity("Set fundraiser {$fundraiser->title} to {$validated['status']}");

        return back()->with('success', "\"{$fundraiser->title}\" is now {$validated['status']}.");
    }

    public function donate(Request $request, Fundraiser $fundraiser): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:100000000'],
            'donor_name' => ['nullable', 'string', 'max:120'],
            'is_anonymous' => ['boolean'],
            'is_recurring' => ['nullable', 'boolean'],
            // Validated against the registered drivers. Without this an
            // unknown key reached PaymentOrchestratorService::getDriver(),
            // which throws InvalidArgumentException — a 500 from a form post.
            'channel' => ['nullable', 'string', 'in:'.implode(',', $this->orchestrator->channelKeys())],
        ]);

        if (! $fundraiser->isOpen()) {
            return back()->withErrors(['amount' => 'This fundraiser is not currently accepting donations.']);
        }

        $user = $request->user();
        $channel = $validated['channel'] ?? 'card';
        $amountMinor = (int) round($validated['amount'] * 100);
        $receiptNumber = 'DON-REC-'.date('Ymd').'-'.strtoupper(Str::random(5));

        // Settle via Payment Orchestrator
        $settlement = $this->orchestrator->settlePayment($channel, [
            'amount_minor' => $amountMinor,
            'currency' => $fundraiser->goal_currency,
            'user_id' => $user->id,
            'description' => "Contribution to {$fundraiser->title}",
        ]);

        $donation = $fundraiser->donations()->create([
            'user_id' => $user->id,
            'amount_minor' => $amountMinor,
            'currency' => $fundraiser->goal_currency,
            'donor_name' => $validated['donor_name'] ?? $user->display_name,
            'is_anonymous' => $request->boolean('is_anonymous'),
            'is_recurring' => $request->boolean('is_recurring'),
            'frequency' => $request->boolean('is_recurring') ? 'monthly' : null,
            // Not set here. Whether a gift is deductible depends on the
            // community's charitable registration and the donor's own
            // circumstances; recording `true` on every donation asserted
            // both. Left at the column default until a registration number
            // is held and checked.
            'receipt_number' => $receiptNumber,
            'payment_channel' => $channel,
            'donated_at' => now(),
        ]);

        // Record in Master Ledger
        $this->orchestrator->recordTransaction(
            user: $user,
            amountMinor: $amountMinor,
            channel: $channel,
            reference: $settlement['reference'] ?? ('DON-'.strtoupper(Str::random(8))),
            fundraiserId: $fundraiser->id,
            notes: "Donation to {$fundraiser->title} by ".($request->boolean('is_anonymous') ? 'Anonymous' : ($validated['donor_name'] ?? $user->display_name))
        );

        $user->recordActivity('Donated $'.number_format($validated['amount'], 2)." to {$fundraiser->title}");

        return back()->with('success', 'Thank you for your generous donation! Your official receipt is available.');
    }

    /**
     * A donation receipt names the donor and the amount they gave.
     *
     * The route was bound to `{donation}` with no check at all, so any signed-in
     * user could walk the ids and read every gift in the estate — including
     * gifts the donor marked anonymous, since `is_anonymous` only governs the
     * public leaderboard, not this document.
     */
    public function receipt(Request $request, Donation $donation): HttpResponse
    {
        $user = $request->user();

        abort_unless(
            $donation->user_id === $user->id || $user->can('manageFundraisers'),
            403,
            'You can only view your own donation receipts.',
        );

        $donation->load('fundraiser');
        $community = Community::first();

        $pdf = Pdf::loadView('pdf.donation-receipt', [
            'donation' => $donation,
            'community' => $community,
        ]);

        return $pdf->stream('donation-receipt-'.($donation->receipt_number ?? $donation->id).'.pdf');
    }

    public function addUpdate(Request $request, Fundraiser $fundraiser): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'content' => ['required', 'string', 'max:2000'],
        ]);

        $fundraiser->updates()->create($validated);

        return back()->with('success', 'Project update posted.');
    }
}
