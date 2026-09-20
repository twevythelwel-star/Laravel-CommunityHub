<?php

namespace App\Http\Controllers;

use App\Models\Community;
use App\Models\GatePass;
use App\Models\Invoice;
use App\Models\Visitor;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class PdfController extends Controller
{
    /**
     * Download official HOA statement / invoice PDF.
     */
    public function downloadInvoice(Invoice $invoice): Response
    {
        $invoice->load('user');
        $community = Community::first();

        $pdf = Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'community' => $community,
        ])->setPaper('a4', 'portrait');

        return $pdf->download("statement-{$invoice->reference}.pdf");
    }

    /**
     * Download visitor dashboard entry permit PDF.
     */
    public function downloadVisitorPass(Visitor $visitor): Response
    {
        $visitor->load('homeowner');
        $community = Community::first();

        $qrData = route('guest-pass.show', ['token' => $visitor->share_token]);

        $pdf = Pdf::loadView('pdf.gate-pass', [
            'name' => $visitor->name,
            'category' => $visitor->type ?? 'Visitor',
            'lot' => $visitor->homeowner?->lot ?? 'Residential Lot',
            'vehicle' => $visitor->vehicle,
            'qrData' => $qrData,
            'community' => $community,
        ])->setPaper('a5', 'landscape');

        return $pdf->download("visitor-permit-{$visitor->id}.pdf");
    }

    /**
     * Download resident gate pass permit PDF.
     */
    public function downloadGatePass(GatePass $gatePass): Response
    {
        $gatePass->load('user');
        $community = Community::first();

        $pdf = Pdf::loadView('pdf.gate-pass', [
            'name' => $gatePass->user->name,
            'category' => $gatePass->category->value ?? 'Resident',
            'lot' => $gatePass->user->lot ?? 'Residential Zone',
            'vehicle' => 'REGISTERED RESIDENT',
            'qrData' => "GATE-PASS-{$gatePass->pass_id}",
            'community' => $community,
        ])->setPaper('a5', 'landscape');

        return $pdf->download("resident-permit-{$gatePass->pass_id}.pdf");
    }
}
