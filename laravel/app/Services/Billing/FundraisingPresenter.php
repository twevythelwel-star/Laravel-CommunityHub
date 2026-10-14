<?php

namespace App\Services\Billing;

use App\Models\Donation;
use App\Models\Fundraiser;
use App\Models\FundraiserUpdate;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Payments\PaymentOrchestratorService;
use Illuminate\Support\Collection;

/**
 * The props of the fundraising page: every campaign with its progress,
 * updates and public donor feed, the viewer's own gifts, and, for those
 * who manage fundraisers, the totals, donor report and reconciliation.
 */
class FundraisingPresenter
{
    public function __construct(private readonly PaymentOrchestratorService $orchestrator) {}

    /**
     * @return array<string, mixed>
     */
    public function props(User $user): array
    {
        $canManage = $user->can('manageFundraisers');

        return [
            'fundraisers' => $this->campaigns(),
            'canManage' => $canManage,
            'availableChannels' => $this->orchestrator->getAvailableChannels(),
            'adminStats' => $canManage ? $this->adminStats() : null,
            'donorReports' => $canManage ? $this->donorReport() : [],
            'reconciliation' => $canManage ? $this->reconciliation() : null,
            'userDonations' => $this->donationsBy($user),
        ];
    }

    /** Open campaigns first, then upcoming, then completed; soonest end first. */
    private function campaigns(): Collection
    {
        return Fundraiser::with(['donations', 'updates'])
            ->orderByRaw("CASE status WHEN 'Active' THEN 1 WHEN 'Upcoming' THEN 2 WHEN 'Completed' THEN 3 ELSE 4 END")
            ->orderBy('end_date')
            ->get()
            ->map(fn (Fundraiser $f) => [
                'id' => $f->id,
                'title' => $f->title,
                'description' => $f->description,
                'beneficiary' => $f->beneficiary ?? 'Community Improvement Fund',
                'coverImageUrl' => $f->cover_image_url,
                'images' => $f->images ?? [],
                'goal' => $f->goal(),
                'raised' => $f->raised(),
                'refunded' => $f->refunded(),
                'progress' => $f->progressPercent(),
                'donorCount' => $f->donorCount(),
                'currency' => $f->goal_currency,
                'startDate' => $f->start_date->toDateString(),
                'endDate' => $f->end_date->toDateString(),
                'status' => $f->status,
                'isOpen' => $f->isOpen(),
                'allowAnonymous' => $f->allow_anonymous,
                'allowRecurring' => $f->allow_recurring,
                'suggestedAmounts' => $f->suggested_amounts ?? Fundraiser::DEFAULT_SUGGESTED_AMOUNTS,
                'matchingSponsor' => $f->matching_sponsor,
                'fundAllocation' => $f->fund_allocation ?? Fundraiser::DEFAULT_FUND_ALLOCATION,
                'showLeaderboard' => $f->show_leaderboard,
                'updates' => $f->updates->map(fn (FundraiserUpdate $u) => [
                    'id' => $u->id,
                    'title' => $u->title,
                    'content' => $u->content,
                    'imageUrl' => $u->image_url,
                    'date' => $u->created_at->format('M d, Y'),
                ]),
                // Only gifts whose money has arrived; a pending one may yet be rejected.
                'recentDonations' => $f->donations
                    ->filter(fn (Donation $d) => $d->isCounted())
                    ->sortByDesc('donated_at')
                    ->take(10)
                    ->map(fn (Donation $d) => [
                        'id' => $d->id,
                        'donorName' => $d->publicDonorName(),
                        'amount' => $d->amount(),
                        'currency' => $d->currency,
                        'isRecurring' => $d->is_recurring,
                        'status' => $d->status ?? 'completed',
                        'receiptNumber' => $d->receipt_number,
                        'receiptUrl' => $this->receiptUrl($d),
                        'timestamp' => $d->donated_at->toIso8601String(),
                    ])->values(),
            ]);
    }

    /**
     * @return array<string, int|float>
     */
    private function adminStats(): array
    {
        $average = Donation::counted()->avg('amount_minor');

        return [
            'totalCampaigns' => Fundraiser::count(),
            'activeCampaigns' => Fundraiser::where('status', 'Active')->count(),
            'completedCampaigns' => Fundraiser::where('status', 'Completed')->count(),
            'totalRaised' => (float) ($this->raisedMinor() / 100),
            'totalGoal' => (float) (Fundraiser::sum('goal_minor') / 100),
            'totalRefunded' => (float) ($this->refundedMinor() / 100),
            'totalDonors' => Donation::counted()->distinct('user_id')->count('user_id'),
            'averageDonation' => $average ? round($average / 100, 2) : 0,
        ];
    }

    /** The latest 150 gifts, with the donor's real name and email for the office. */
    private function donorReport(): Collection
    {
        return Donation::with(['fundraiser', 'user'])
            ->latest('donated_at')
            ->take(150)
            ->get()
            ->map(fn (Donation $d) => [
                'id' => $d->id,
                'receiptNumber' => $d->receipt_number,
                'fundraiserId' => $d->fundraiser_id,
                'fundraiserTitle' => $d->fundraiser?->title ?? 'Unknown Campaign',
                'donorName' => $d->donor_name ?? ($d->user?->display_name ?? 'Resident'),
                'publicDonorName' => $d->publicDonorName(),
                'isAnonymous' => (bool) $d->is_anonymous,
                'userEmail' => $d->user?->email,
                'amount' => $d->amount(),
                'currency' => $d->currency,
                'channel' => $d->payment_channel ?? 'card',
                'isRecurring' => (bool) $d->is_recurring,
                'frequency' => $d->frequency,
                'status' => $d->status ?? 'completed',
                'refundedAt' => $d->refunded_at?->toIso8601String(),
                'refundReason' => $d->refund_reason,
                'donatedAt' => $d->donated_at->toIso8601String(),
                'receiptUrl' => $this->receiptUrl($d),
            ]);
    }

    /**
     * Counted gifts against the ledger's completed fundraising transactions,
     * with the split by payment channel.
     *
     * @return array<string, mixed>
     */
    private function reconciliation(): array
    {
        $raised = $this->raisedMinor();
        $ledger = (int) Transaction::whereNotNull('fundraiser_id')->where('status', 'completed')->sum('amount_minor');

        return [
            'totalDonationsMinor' => $raised,
            'totalRefundsMinor' => $this->refundedMinor(),
            'ledgerTransactionsMinor' => $ledger,
            'varianceMinor' => abs($raised - $ledger),
            'channelBreakdown' => Donation::counted()
                ->selectRaw('payment_channel, count(*) as count, sum(amount_minor) as total_minor')
                ->groupBy('payment_channel')
                ->get()
                ->map(fn ($row) => [
                    'channel' => $row->payment_channel ?? 'card',
                    'count' => (int) $row->count,
                    'total' => round($row->total_minor / 100, 2),
                ]),
        ];
    }

    private function donationsBy(User $user): Collection
    {
        return Donation::where('user_id', $user->id)
            ->with('fundraiser')
            ->latest('donated_at')
            ->take(15)
            ->get()
            ->map(fn (Donation $d) => [
                'id' => $d->id,
                'fundraiserId' => $d->fundraiser_id,
                'fundraiserTitle' => $d->fundraiser?->title ?? 'Campaign',
                'amount' => $d->amount(),
                'currency' => $d->currency,
                'isAnonymous' => (bool) $d->is_anonymous,
                'isRecurring' => (bool) $d->is_recurring,
                'frequency' => $d->frequency,
                'status' => $d->status ?? 'completed',
                'donatedAt' => $d->donated_at->toIso8601String(),
                'receiptUrl' => $this->receiptUrl($d),
            ]);
    }

    private function raisedMinor(): int
    {
        return (int) Donation::counted()->sum('amount_minor');
    }

    private function refundedMinor(): int
    {
        return (int) Donation::where('status', 'refunded')->sum('amount_minor');
    }

    private function receiptUrl(Donation $donation): string
    {
        return route('dashboard.fundraising.donation.receipt', $donation->id);
    }
}
