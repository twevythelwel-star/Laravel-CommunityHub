<?php

namespace Tests\Feature;

use App\Models\Community;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripeBillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_resident_can_initiate_stripe_checkout_for_own_invoice(): void
    {
        $user = User::factory()->create();
        $invoice = Invoice::create([
            'user_id' => $user->id,
            'reference' => 'INV-TEST-001',
            'amount_minor' => 25000,
            'currency' => 'USD',
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'due_on' => now()->addDays(15),
            'status' => 'Unpaid',
        ]);

        $response = $this->actingAs($user)
            ->post(route('dashboard.billing.stripe.checkout', ['invoice' => $invoice->id]));

        $response->assertRedirect();
        $invoice->refresh();
        $this->assertNotNull($invoice->stripe_session_id);
    }

    public function test_user_cannot_checkout_another_users_invoice(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $invoice = Invoice::create([
            'user_id' => $owner->id,
            'reference' => 'INV-TEST-002',
            'amount_minor' => 25000,
            'currency' => 'USD',
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'due_on' => now()->addDays(15),
            'status' => 'Unpaid',
        ]);

        $response = $this->actingAs($intruder)
            ->post(route('dashboard.billing.stripe.checkout', ['invoice' => $invoice->id]));

        $response->assertForbidden();
    }

    public function test_stripe_success_settles_invoice_and_marks_paid(): void
    {
        $user = User::factory()->create();
        $invoice = Invoice::create([
            'user_id' => $user->id,
            'reference' => 'INV-TEST-003',
            'amount_minor' => 25000,
            'currency' => 'USD',
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'due_on' => now()->addDays(15),
            'status' => 'Unpaid',
            'stripe_session_id' => 'cs_demo_12345',
        ]);

        $response = $this->actingAs($user)
            ->get(route('dashboard.billing.stripe.success', [
                'invoice' => $invoice->id,
                'session_id' => 'cs_demo_12345',
            ]));

        $response->assertRedirect(route('dashboard.billing'));
        $invoice->refresh();
        $this->assertEquals('Paid', $invoice->status);
        $this->assertNotNull($invoice->paid_at);
    }

    public function test_invoice_pdf_download_streams_pdf(): void
    {
        $user = User::factory()->create();
        $invoice = Invoice::create([
            'user_id' => $user->id,
            'reference' => 'INV-TEST-004',
            'amount_minor' => 25000,
            'currency' => 'USD',
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'due_on' => now()->addDays(15),
            'status' => 'Paid',
            'paid_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->get(route('dashboard.billing.invoice.pdf', ['invoice' => $invoice->id]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }
}
