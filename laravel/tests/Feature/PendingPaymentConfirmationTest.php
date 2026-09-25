<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Donation;
use App\Models\Fundraiser;
use App\Models\Invoice;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Payments by channels the app cannot see.
 *
 * Bank wire, cash, QR, NFC, Apple/Google/Samsung Pay, Zelle and Cash App each
 * had a driver that reported success for whatever amount was posted, so a
 * resident could mark their own invoice Paid — or add to a campaign total —
 * by choosing one of them. They are now recorded as pending and count only
 * once an administrator confirms the money arrived.
 */
class PendingPaymentConfirmationTest extends TestCase
{
    use RefreshDatabase;

    private function resident(): User
    {
        return User::factory()->role(UserRole::Homeowner)->create();
    }

    private function admin(): User
    {
        return User::factory()->role(UserRole::Admin)->create();
    }

    private function invoiceFor(User $user, int $amountMinor = 10_000_00): Invoice
    {
        return Invoice::create([
            'user_id' => $user->id,
            'reference' => 'INV-PEND-'.fake()->unique()->numerify('####'),
            'amount_minor' => $amountMinor,
            'currency' => 'JMD',
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'due_on' => now()->addDays(10),
            'status' => 'Unpaid',
        ]);
    }

    private function fundraiser(): Fundraiser
    {
        return Fundraiser::create([
            'title' => 'Clubhouse Roof',
            'description' => 'Replace the leaking roof.',
            'goal_minor' => 1_000_000_00,
            'goal_currency' => 'JMD',
            'status' => 'Active',
            'created_by' => $this->admin()->id,
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth(),
        ]);
    }

    /** @param  array<string, mixed>  $overrides */
    private function pay(User $user, Invoice $invoice, array $overrides = []): void
    {
        $this->actingAs($user)
            ->post(route('dashboard.billing.pay'), [
                'channel' => 'bank_wire',
                'amount' => $invoice->amount_minor / 100,
                'invoice_id' => $invoice->id,
                ...$overrides,
            ])
            ->assertSessionHasNoErrors();
    }

    // ── Invoices ──

    public function test_an_office_channel_payment_is_pending_and_settles_nothing(): void
    {
        $resident = $this->resident();
        $invoice = $this->invoiceFor($resident);

        $this->pay($resident, $invoice);

        $payment = Transaction::sole();
        $this->assertSame('pending', $payment->status);
        $this->assertSame(10_000_00, $payment->amount_minor);
        $this->assertSame('Unpaid', $invoice->fresh()->status);
        $this->assertSame(0, $invoice->fresh()->amountPaidMinor());
    }

    public function test_every_channel_the_app_cannot_see_waits_for_the_office(): void
    {
        $resident = $this->resident();

        foreach (['bank_wire', 'cash_office', 'qr_code', 'nfc_pos', 'apple_pay', 'google_pay', 'samsung_wallet', 'zelle', 'cash_app'] as $channel) {
            $invoice = $this->invoiceFor($resident);

            $this->pay($resident, $invoice, ['channel' => $channel]);

            $this->assertSame('Unpaid', $invoice->fresh()->status, "{$channel} settled the invoice on its own.");
        }

        $this->assertSame(0, Transaction::where('status', 'completed')->count());
    }

    public function test_confirming_a_payment_settles_the_invoice_and_records_who_confirmed(): void
    {
        $resident = $this->resident();
        $admin = $this->admin();
        $invoice = $this->invoiceFor($resident);

        $this->pay($resident, $invoice);
        $payment = Transaction::sole();

        $this->actingAs($admin)
            ->post(route('dashboard.billing.transactions.confirm', $payment))
            ->assertSessionHasNoErrors();

        $payment->refresh();
        $this->assertSame('completed', $payment->status);
        $this->assertSame($admin->id, $payment->reviewed_by);
        $this->assertNotNull($payment->reviewed_at);
        $this->assertSame('Paid', $invoice->fresh()->status);
    }

    public function test_confirming_a_part_payment_leaves_the_invoice_partially_paid(): void
    {
        $resident = $this->resident();
        $invoice = $this->invoiceFor($resident);

        $this->pay($resident, $invoice, ['amount' => 4_000]);

        $this->actingAs($this->admin())
            ->post(route('dashboard.billing.transactions.confirm', Transaction::sole()));

        $this->assertSame('Partially Paid', $invoice->fresh()->status);
        $this->assertSame(6_000_00, $invoice->fresh()->balanceRemainingMinor());
    }

    public function test_chosen_line_items_are_marked_paid_only_on_confirmation(): void
    {
        $resident = $this->resident();
        $invoice = $this->invoiceFor($resident);
        $item = $invoice->items()->create(['category' => 'Dues', 'title' => 'Security levy', 'amount_minor' => 2_000_00, 'status' => 'Unpaid']);
        $untouched = $invoice->items()->create(['category' => 'Dues', 'title' => 'Garbage', 'amount_minor' => 1_000_00, 'status' => 'Unpaid']);

        $this->pay($resident, $invoice, ['amount' => 2_000, 'item_ids' => [$item->id]]);

        $this->assertSame('Unpaid', $item->fresh()->status);

        $this->actingAs($this->admin())
            ->post(route('dashboard.billing.transactions.confirm', Transaction::sole()));

        $this->assertSame('Paid', $item->fresh()->status);
        $this->assertSame('Unpaid', $untouched->fresh()->status);
    }

    public function test_rejecting_a_payment_changes_nothing_it_would_have_paid(): void
    {
        $resident = $this->resident();
        $invoice = $this->invoiceFor($resident);

        $this->pay($resident, $invoice);
        $payment = Transaction::sole();

        $this->actingAs($this->admin())
            ->post(route('dashboard.billing.transactions.reject', $payment), ['reason' => 'No deposit on the statement'])
            ->assertSessionHasNoErrors();

        $payment->refresh();
        $this->assertSame('rejected', $payment->status);
        $this->assertStringContainsString('No deposit on the statement', $payment->notes);
        $this->assertSame('Unpaid', $invoice->fresh()->status);
    }

    public function test_a_payment_is_reviewed_once(): void
    {
        $resident = $this->resident();
        $admin = $this->admin();
        $invoice = $this->invoiceFor($resident);

        $this->pay($resident, $invoice);
        $payment = Transaction::sole();

        $this->actingAs($admin)->post(route('dashboard.billing.transactions.reject', $payment));

        $this->actingAs($admin)
            ->post(route('dashboard.billing.transactions.confirm', $payment))
            ->assertSessionHasErrors('transaction');

        $this->assertSame('rejected', $payment->fresh()->status);
        $this->assertSame('Unpaid', $invoice->fresh()->status);
    }

    public function test_a_resident_cannot_confirm_their_own_payment(): void
    {
        $resident = $this->resident();
        $invoice = $this->invoiceFor($resident);

        $this->pay($resident, $invoice);

        $this->actingAs($resident)
            ->post(route('dashboard.billing.transactions.confirm', Transaction::sole()))
            ->assertForbidden();

        $this->assertSame('Unpaid', $invoice->fresh()->status);
    }

    public function test_no_receipt_is_issued_for_an_unconfirmed_payment(): void
    {
        $resident = $this->resident();
        $invoice = $this->invoiceFor($resident);

        $this->pay($resident, $invoice);

        $this->actingAs($resident)
            ->get(route('dashboard.billing.transactions.receipt', Transaction::sole()))
            ->assertStatus(409);
    }

    public function test_administrators_see_every_payment_awaiting_confirmation(): void
    {
        $resident = $this->resident();
        $invoice = $this->invoiceFor($resident);
        $this->pay($resident, $invoice);

        $this->actingAs($this->admin())
            ->get(route('dashboard.billing'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('pendingPayments', 1)
                ->where('pendingPayments.0.status', 'pending')
                ->where('pendingPayments.0.confirmUrl', route('dashboard.billing.transactions.confirm', Transaction::sole()))
            );

        $this->actingAs($resident)
            ->get(route('dashboard.billing'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('pendingPayments', [])
                ->where('transactions.data.0.confirmUrl', null)
            );
    }

    // ── The Community Wallet, which the app can see ──

    public function test_the_wallet_still_pays_immediately(): void
    {
        $resident = $this->resident();
        $invoice = $this->invoiceFor($resident, 1_000_00);
        Wallet::create(['user_id' => $resident->id, 'currency' => 'JMD', 'available_balance_minor' => 5_000_00, 'pending_balance_minor' => 0, 'rewards_balance_minor' => 0]);

        $this->pay($resident, $invoice, ['channel' => 'wallet']);

        $this->assertSame('completed', Transaction::sole()->status);
        $this->assertSame('Paid', $invoice->fresh()->status);
    }

    public function test_a_short_wallet_is_refused(): void
    {
        $resident = $this->resident();
        $invoice = $this->invoiceFor($resident, 1_000_00);
        Wallet::create(['user_id' => $resident->id, 'currency' => 'JMD', 'available_balance_minor' => 100_00, 'pending_balance_minor' => 0, 'rewards_balance_minor' => 0]);

        $this->actingAs($resident)
            ->post(route('dashboard.billing.pay'), ['channel' => 'wallet', 'amount' => 1_000, 'invoice_id' => $invoice->id])
            ->assertSessionHasErrors('channel');

        // The driver's refusal used to be ignored and a completed payment written.
        $this->assertSame(0, Transaction::count());
        $this->assertSame('Unpaid', $invoice->fresh()->status);
    }

    // ── Donations ──

    public function test_a_pending_gift_does_not_count_until_confirmed(): void
    {
        $fundraiser = $this->fundraiser();
        $donor = $this->resident();

        $this->actingAs($donor)
            ->post(route('dashboard.fundraising.donate', $fundraiser), ['amount' => 500, 'channel' => 'zelle', 'donor_name' => 'The Clarkes'])
            ->assertSessionHasNoErrors();

        $donation = Donation::sole();
        $this->assertSame('pending', $donation->status);
        $this->assertSame(0, $fundraiser->fresh()->raisedMinor());
        $this->assertSame(0, $fundraiser->fresh()->donorCount());

        $this->actingAs($donor)
            ->get(route('dashboard.fundraising'))
            ->assertInertia(fn (Assert $page) => $page->has('fundraisers.0.recentDonations', 0));

        $this->actingAs($this->admin())
            ->post(route('dashboard.billing.transactions.confirm', Transaction::where('donation_id', $donation->id)->sole()))
            ->assertSessionHasNoErrors();

        $this->assertSame('completed', $donation->fresh()->status);
        $this->assertSame(500_00, $fundraiser->fresh()->raisedMinor());
        $this->assertSame(1, $fundraiser->fresh()->donorCount());
    }

    public function test_a_rejected_gift_never_counts(): void
    {
        $fundraiser = $this->fundraiser();

        $this->actingAs($this->resident())
            ->post(route('dashboard.fundraising.donate', $fundraiser), ['amount' => 500, 'channel' => 'cash_office']);

        $this->actingAs($this->admin())
            ->post(route('dashboard.billing.transactions.reject', Transaction::sole()));

        $this->assertSame('rejected', Donation::sole()->status);
        $this->assertSame(0, $fundraiser->fresh()->raisedMinor());
    }

    public function test_a_pending_gift_cannot_be_refunded(): void
    {
        $fundraiser = $this->fundraiser();

        $this->actingAs($this->resident())
            ->post(route('dashboard.fundraising.donate', $fundraiser), ['amount' => 500, 'channel' => 'cash_office']);

        $this->actingAs($this->admin())
            ->post(route('dashboard.fundraising.donations.refund', Donation::sole()), ['reason' => 'Changed mind'])
            ->assertSessionHasErrors('refund');

        $this->assertSame('pending', Donation::sole()->status);
    }
}
