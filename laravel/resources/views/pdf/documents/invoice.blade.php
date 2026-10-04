<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $reference ?? 'INV-001' }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: #1e293b;
            margin: 0;
            padding: 24px;
            font-size: 13px;
            line-height: 1.5;
        }
        .header-table {
            width: 100%;
            margin-bottom: 30px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 16px;
        }
        .community-name {
            font-size: 22px;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.5px;
        }
        .community-meta {
            font-size: 11px;
            color: #64748b;
        }
        .doc-title {
            text-align: right;
            font-size: 26px;
            font-weight: 900;
            color: #4f46e5;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            background-color: #ecfdf5;
            color: #059669;
            border: 1px solid #a7f3d0;
        }
        .status-unpaid {
            background-color: #fff1f2;
            color: #e11d48;
            border-color: #fecdd3;
        }
        .meta-grid {
            width: 100%;
            margin-bottom: 24px;
        }
        .meta-col {
            vertical-align: top;
            width: 50%;
        }
        .section-label {
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            color: #64748b;
            margin-bottom: 4px;
            letter-spacing: 0.5px;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
        }
        .items-table th {
            background-color: #f8fafc;
            border-bottom: 2px solid #cbd5e1;
            padding: 8px 10px;
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
            color: #475569;
        }
        .items-table td {
            border-bottom: 1px solid #e2e8f0;
            padding: 10px;
        }
        .totals-table {
            width: 40%;
            margin-left: auto;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .totals-table td {
            padding: 6px 10px;
        }
        .grand-total {
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
            border-top: 2px solid #cbd5e1;
        }
        .footer-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 14px;
            font-size: 11px;
            color: #64748b;
        }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td>
                <div class="community-name">{{ $community_name ?? 'Community Estates' }}</div>
                <div class="community-meta">Official HOA Financial Administration &bull; Automated Ledger</div>
            </td>
            <td style="text-align: right;">
                <div class="doc-title">INVOICE</div>
                <div style="margin-top: 4px;">
                    <span class="status-badge {{ ($status ?? 'paid') === 'paid' ? '' : 'status-unpaid' }}">
                        {{ strtoupper($status ?? 'PAID') }}
                    </span>
                </div>
            </td>
        </tr>
    </table>

    <table class="meta-grid">
        <tr>
            <td class="meta-col">
                <div class="section-label">Billed To</div>
                <div style="font-weight: bold; font-size: 14px;">{{ $recipient_name ?? 'Alexander Vance' }}</div>
                <div>Lot {{ $lot_number ?? '42' }} &bull; {{ $street_address ?? 'Palmetto Way' }}</div>
                <div>{{ $recipient_email ?? 'resident@community.io' }}</div>
            </td>
            <td class="meta-col" style="text-align: right;">
                <div class="section-label">Invoice Details</div>
                <div><strong>Invoice #:</strong> {{ $reference ?? 'INV-2026-0841' }}</div>
                <div><strong>Issue Date:</strong> {{ $issue_date ?? date('M d, Y') }}</div>
                <div><strong>Due Date:</strong> {{ $due_date ?? date('M d, Y', strtotime('+15 days')) }}</div>
            </td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th>Description</th>
                <th style="text-align: center; width: 10%;">Qty</th>
                <th style="text-align: right; width: 20%;">Rate</th>
                <th style="text-align: right; width: 20%;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($items ?? [
                ['description' => 'Monthly HOA Maintenance & Security Assessment', 'quantity' => 1, 'rate' => 250.00, 'amount' => 250.00],
                ['description' => 'Clubhouse Infrastructure & Pool Reserve Sinking Fund', 'quantity' => 1, 'rate' => 45.00, 'amount' => 45.00],
                ['description' => 'RFID Gate Transponder Vehicle Permit Tag #492', 'quantity' => 2, 'rate' => 15.00, 'amount' => 30.00]
            ] as $item)
                <tr>
                    <td>
                        <div style="font-weight: 600;">{{ $item['description'] }}</div>
                    </td>
                    <td style="text-align: center;">{{ $item['quantity'] }}</td>
                    <td style="text-align: right;">${{ number_format($item['rate'], 2) }}</td>
                    <td style="text-align: right; font-weight: 600;">${{ number_format($item['amount'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals-table">
        <tr>
            <td style="color: #64748b;">Subtotal:</td>
            <td style="text-align: right; font-weight: 600;">${{ number_format($subtotal ?? 325.00, 2) }}</td>
        </tr>
        <tr>
            <td style="color: #64748b;">Tax / Municipal Levy:</td>
            <td style="text-align: right; font-weight: 600;">${{ number_format($tax ?? 0.00, 2) }}</td>
        </tr>
        <tr class="grand-total">
            <td>Total Due:</td>
            <td style="text-align: right; color: #4f46e5;">${{ number_format($total ?? 325.00, 2) }}</td>
        </tr>
    </table>

    <div class="footer-box">
        <strong>Remittance Instructions:</strong>
        Payments may be processed via the resident portal credit/debit card gateway, direct bank transfer, or automated recurring Stripe ACH. Please quote invoice reference <strong>{{ $reference ?? 'INV-2026-0841' }}</strong> on all remittances.
    </div>
</body>
</html>
