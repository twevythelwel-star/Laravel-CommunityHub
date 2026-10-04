<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Receipt {{ $reference ?? 'REC-001' }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: #1e293b;
            margin: 0;
            padding: 24px;
            font-size: 13px;
        }
        .receipt-card {
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 24px;
            position: relative;
        }
        .header {
            border-bottom: 2px dashed #cbd5e1;
            padding-bottom: 16px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
        }
        .title {
            font-size: 22px;
            font-weight: 900;
            color: #047857;
            text-transform: uppercase;
        }
        .paid-stamp {
            position: absolute;
            top: 20px;
            right: 30px;
            border: 3px solid #059669;
            color: #059669;
            font-size: 18px;
            font-weight: 900;
            text-transform: uppercase;
            padding: 4px 14px;
            border-radius: 6px;
            transform: rotate(-8deg);
            opacity: 0.85;
        }
        .meta-table {
            width: 100%;
            margin-bottom: 20px;
            font-size: 12px;
        }
        .meta-table td {
            padding: 4px 0;
        }
        .breakdown-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
        }
        .breakdown-table th {
            background-color: #f8fafc;
            border-bottom: 2px solid #cbd5e1;
            padding: 8px;
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
            color: #475569;
        }
        .breakdown-table td {
            border-bottom: 1px solid #e2e8f0;
            padding: 10px 8px;
        }
        .total-row {
            font-size: 16px;
            font-weight: 900;
            color: #047857;
            border-top: 2px solid #cbd5e1;
        }
        .footer-note {
            font-size: 11px;
            color: #64748b;
            text-align: center;
            border-top: 1px dashed #cbd5e1;
            padding-top: 14px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="receipt-card">
        <div class="paid-stamp">PAID IN FULL</div>

        <table style="width: 100%; border-bottom: 2px dashed #cbd5e1; padding-bottom: 14px; margin-bottom: 18px;">
            <tr>
                <td>
                    <div style="font-size: 18px; font-weight: 800; color: #0f172a;">{{ $community_name ?? 'Community' }}</div>
                    <div style="font-size: 11px; color: #64748b;">Official Payment &amp; Revenue Receipt</div>
                </td>
            </tr>
        </table>

        <table class="meta-table">
            <tr>
                <td style="width: 50%;">
                    <div style="font-size: 10px; color: #64748b; text-transform: uppercase; font-weight: bold;">Received From</div>
                    <div style="font-weight: bold; font-size: 14px; color: #0f172a;">{{ $recipient_name ?? 'Julian Montgomery' }}</div>
                    <div>Lot {{ $lot_number ?? '18' }} &bull; {{ $street_address ?? 'Fairway Ridge' }}</div>
                </td>
                <td style="width: 50%; text-align: right;">
                    <div><strong>Receipt #:</strong> {{ $reference ?? 'REC-2026-4401' }}</div>
                    <div><strong>Transaction ID:</strong> <span style="font-family: monospace;">{{ $transaction_id ?? 'tx_3NqL2e2eZvKYlo2C' }}</span></div>
                    <div><strong>Date &amp; Time:</strong> {{ $payment_date ?? date('Y-m-d H:i:s') }}</div>
                    <div><strong>Method:</strong> {{ $payment_method ?? 'Stripe Card (ending 4242)' }}</div>
                </td>
            </tr>
        </table>

        <table class="breakdown-table">
            <thead>
                <tr>
                    <th>Payment Item</th>
                    <th style="width: 25%; text-align: right;">Ledger Account</th>
                    <th style="width: 20%; text-align: right;">Amount Paid</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($items ?? [
                    ['description' => 'HOA Annual Common Area Assessment', 'ledger' => '4010-HOA-ASSESS', 'amount' => 350.00],
                    ['description' => 'Tennis Court Floodlight Keycard Access', 'ledger' => '4090-AMENITY-FEE', 'amount' => 25.00]
                ] as $item)
                    <tr>
                        <td><strong>{{ $item['description'] }}</strong></td>
                        <td style="text-align: right; font-family: monospace; font-size: 11px; color: #64748b;">{{ $item['ledger'] }}</td>
                        <td style="text-align: right; font-weight: bold;">${{ number_format($item['amount'], 2) }}</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="2" style="text-align: right;">Total Received:</td>
                    <td style="text-align: right;">${{ number_format($total ?? 375.00, 2) }}</td>
                </tr>
            </tbody>
        </table>

        <div class="footer-note">
            This document represents a legally binding electronic receipt generated automatically by the Community Hub payment system. All funds have cleared in the community operating account.
        </div>
    </div>
</body>
</html>
