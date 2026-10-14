<?php

namespace App\Actions\Billing;

use App\Models\Donation;
use App\Models\Fundraiser;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Payments\Providers\PaymentInstruction;
use App\Services\Payments\Providers\PaymentRequest;
use App\Services\Payments\Providers\ProviderRegistry;
use DomainException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Stripe\Exception\ApiErrorException;

/**
 * Give to a campaign through the estate's payment provider for the chosen
 * channel.
 *
 * A card or device-wallet gift is written as a Donation only once the
 * processor confirms the money, from details carried with the payment (see
 * StripePaymentService::settleDonationSession). Every other gift is on record
 * from the start as `pending`, which keeps it out of campaign totals, the
 * public donor feed and receipts: the Community Wallet settles at once; bank
 * wire, cash, QR, NFC, Zelle and Cash App wait for the office to log the money
 * received and a second administrator to verify it.
 */
class DonateToFundraiserAction
{
    public function __construct(private readonly ProviderRegistry $providers) {}

    /**
     * @param  array{amount: float|string, channel?: string|null, donor_name?: string|null, frequency?: string|null}  $gift
     *
     * @throws ValidationException when the campaign is closed, the channel is
     *                             unavailable or the provider refuses
     */
    public function execute(Fundraiser $fundraiser, User $donor, array $gift, bool $anonymous, bool $recurring): PaymentInstruction
    {
        if (! $fundraiser->isOpen()) {
            throw ValidationException::withMessages(['amount' => 'This fundraiser is not currently accepting donations.']);
        }

        $channel = $gift['channel'] ?? 'card';
        $provider = $this->providers->forChannel($channel);

        if (! $provider) {
            $method = $channel === 'card' ? 'Card' : Transaction::formatPaymentMethod($channel);

            throw ValidationException::withMessages(['channel' => "{$method} donations are not available yet. Please choose another method."]);
        }

        $amountMinor = (int) round($gift['amount'] * 100);
        $details = [
            'donor_name' => $gift['donor_name'] ?? $donor->display_name,
            'is_anonymous' => $anonymous,
            'is_recurring' => $recurring,
            'frequency' => $gift['frequency'] ?? null,
        ];

        $viaProcessor = $channel === 'card' || in_array($channel, ProviderRegistry::WALLET_CHANNELS, true);
        $donation = $viaProcessor ? null : $this->pendingDonation($fundraiser, $donor, $channel, $amountMinor, $details);

        try {
            $instruction = $provider->createPayment(new PaymentRequest(
                payer: $donor,
                channel: $channel,
                amountMinor: $amountMinor,
                currency: $fundraiser->goal_currency,
                fundraiser: $fundraiser,
                donation: $donation,
                donor: $details,
            ));
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['channel' => $e->getMessage()]);
        } catch (ApiErrorException $e) {
            report($e);

            throw ValidationException::withMessages(['channel' => 'Card checkout could not be started. Please try again shortly.']);
        }

        $this->recordActivity($donor, $fundraiser, $gift['amount'], $channel, $instruction);

        return $instruction;
    }

    /**
     * @param  array{donor_name: string, is_anonymous: bool, is_recurring: bool, frequency: string|null}  $details
     */
    private function pendingDonation(Fundraiser $fundraiser, User $donor, string $channel, int $amountMinor, array $details): Donation
    {
        return $fundraiser->donations()->create([
            'user_id' => $donor->id,
            'amount_minor' => $amountMinor,
            'currency' => $fundraiser->goal_currency,
            'donor_name' => $details['donor_name'],
            'is_anonymous' => $details['is_anonymous'],
            'is_recurring' => $details['is_recurring'],
            'frequency' => $details['is_recurring'] ? ($details['frequency'] ?? 'monthly') : null,
            'status' => 'pending',
            'receipt_number' => 'DON-REC-'.date('Ymd').'-'.strtoupper(Str::random(5)),
            'payment_channel' => $channel,
            'donated_at' => now(),
        ]);
    }

    /** A redirect is recorded later, when the processor confirms it. */
    private function recordActivity(User $donor, Fundraiser $fundraiser, float|string $amount, string $channel, PaymentInstruction $instruction): void
    {
        $label = "{$fundraiser->goal_currency} ".number_format((float) $amount, 2);

        match (true) {
            $instruction->isRedirect() => null,
            $instruction->type === 'completed' => $donor->recordActivity("Donated {$label} to {$fundraiser->title}"),
            default => $donor->recordActivity("Pledged {$label} to {$fundraiser->title} via {$channel} ({$instruction->payment->transaction_id}), awaiting confirmation"),
        };
    }
}
