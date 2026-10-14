<?php

namespace App\Actions\Billing;

use App\Models\Donation;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Refund a confirmed gift: the donation is marked refunded and the ledger
 * gets a matching negative entry, so campaign totals and the ledger agree.
 */
class RefundDonationAction
{
    /**
     * @throws ValidationException when the gift is already refunded or not yet confirmed
     */
    public function execute(Donation $donation, User $refundedBy, string $reason): void
    {
        if ($donation->status === 'refunded') {
            throw ValidationException::withMessages(['refund' => 'This donation has already been refunded.']);
        }

        if (! $donation->isCounted()) {
            throw ValidationException::withMessages(['refund' => 'Only a confirmed donation can be refunded. Reject a pending one from the billing ledger instead.']);
        }

        DB::transaction(function () use ($donation, $reason) {
            $donation->update([
                'status' => 'refunded',
                'refunded_at' => now(),
                'refund_reason' => $reason,
            ]);

            Transaction::create([
                'user_id' => $donation->user_id,
                'fundraiser_id' => $donation->fundraiser_id,
                'amount_minor' => $donation->amount_minor,
                'fee_minor' => 0,
                'net_amount_minor' => -$donation->amount_minor,
                'currency' => $donation->currency,
                'payment_channel' => $donation->payment_channel ?? 'card',
                'reference' => 'REF-DON-'.strtoupper(Str::random(8)),
                'status' => 'refunded',
                'settled_at' => now(),
                'notes' => "Refund for donation #{$donation->receipt_number}: {$reason}",
            ]);
        });

        $refundedBy->recordActivity("Refunded donation #{$donation->receipt_number} ({$donation->amount()} {$donation->currency})");
    }
}
