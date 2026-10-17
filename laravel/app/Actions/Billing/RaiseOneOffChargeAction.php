<?php

namespace App\Actions\Billing;

use App\Models\BillingSetting;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Bills a resident for something other than the monthly assessment (a facility booking fee,
 * say): one invoice with one line, in the community's billing currency, against the
 * resident's property. It is paid like any other invoice, through its payment link and the
 * ledger, and shows on the resident's billing page and in the billing totals.
 */
class RaiseOneOffChargeAction
{
    public function execute(User $payer, int $amountMinor, string $category, string $title, CarbonInterface|string $dueOn): Invoice
    {
        $dueOn = Carbon::parse($dueOn)->startOfDay();
        $property = $payer->properties()->orderBy('id')->first();

        $invoice = Invoice::create([
            'user_id' => $payer->id,
            'property_id' => $property?->id,
            'reference' => sprintf(
                'INV-%s-%s-%s',
                now()->format('Ym'),
                $property?->property_code ?? "U{$payer->id}",
                strtoupper(bin2hex(random_bytes(2)))
            ),
            'amount_minor' => $amountMinor,
            'currency' => BillingSetting::currencyCode(),
            'period_start' => today()->toDateString(),
            'period_end' => $dueOn->toDateString(),
            'due_on' => $dueOn->toDateString(),
            'status' => 'Unpaid',
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'category' => $category,
            'title' => $title,
            'amount_minor' => $amountMinor,
            'status' => 'Unpaid',
        ]);

        return $invoice;
    }
}
