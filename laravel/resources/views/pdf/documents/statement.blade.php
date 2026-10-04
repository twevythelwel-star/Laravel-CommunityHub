<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Account Statement {{ $account_number ?? 'ACC-001' }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: #1e293b;
            margin: 0;
            padding: 24px;
            font-size: 12px;
            line-height: 1.5;
        }
        .header-table {
            width: 100%;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 14px;
            margin-bottom: 20px;
        }
        .title {
            font-size: 22px;
            font-weight: 900;
            color: #0f172a;
            text-transform: uppercase;
        }
        .summary-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 20px;
        }
        .aging-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            text-align: center;
        }
        .aging-table th {
            background-color: #f1f5f9;
            border: 1px solid #cbd5e1;
            padding: 6px;
            font-size: 10px;
            text-transform: uppercase;
            color: #475569;
        }
        .aging-table td {
            border: 1px solid #e2e8f0;
            padding: 8px;
            font-weight: bold;
        }
        .ledger-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
        }
        .ledger-table th {
            background-color: #0f172a;
            color: #ffffff;
            padding: 7px 8px;
            text-align: left;
            font-size: 10px;
            text-transform: uppercase;
        }
        .ledger-table td {
            border-bottom: 1px solid #e2e8f0;
            padding: 8px;
            font-size: 11px;
        }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td>
                <div class="title">ACCOUNT STATEMENT</div>
                <div style="font-size: 11px; color: #64748b;">{{ $community_name ?? 'Community HOA' }} &bull; Financial Directorate</div>
            </td>
            <td style="text-align: right; font-size: 11px;">
                <div><strong>Statement Date:</strong> {{ $statement_date ?? date('M d, Y') }}</div>
                <div><strong>Period:</strong> {{ $period ?? 'Sep 01, 2026 - Sep 30, 2026' }}</div>
                <div><strong>Account #:</strong> {{ $account_number ?? 'ACC-LOT-042' }}</div>
            </td>
        </tr>
    </table>

    <table style="width: 100%; margin-bottom: 20px;">
        <tr>
            <td style="width: 50%;">
                <div style="font-size: 10px; font-weight: bold; color: #64748b; text-transform: uppercase;">Resident Account</div>
                <div style="font-size: 14px; font-weight: bold; color: #0f172a;">{{ $recipient_name ?? 'Alexander Vance' }}</div>
                <div>Lot {{ $lot_number ?? '42' }} &bull; {{ $street_address ?? 'Palmetto Way' }}</div>
            </td>
            <td style="width: 50%; text-align: right;">
                <div style="font-size: 10px; font-weight: bold; color: #64748b; text-transform: uppercase;">Outstanding Balance</div>
                <div style="font-size: 24px; font-weight: 900; color: #4f46e5;">${{ number_format($ending_balance ?? 0.00, 2) }}</div>
                <div style="font-size: 10px; color: #10b981;">Account in Good Standing</div>
            </td>
        </tr>
    </table>

    {{-- Aging Analysis --}}
    <table class="aging-table">
        <thead>
            <tr>
                <th>Current</th>
                <th>1 - 30 Days</th>
                <th>31 - 60 Days</th>
                <th>61 - 90 Days</th>
                <th>Over 90 Days</th>
                <th style="background-color: #e0e7ff; color: #3730a3;">Total Due</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>${{ number_format($aging_current ?? 0.00, 2) }}</td>
                <td>${{ number_format($aging_30 ?? 0.00, 2) }}</td>
                <td>${{ number_format($aging_60 ?? 0.00, 2) }}</td>
                <td>${{ number_format($aging_90 ?? 0.00, 2) }}</td>
                <td>${{ number_format($aging_over_90 ?? 0.00, 2) }}</td>
                <td style="background-color: #e0e7ff; color: #3730a3; font-size: 13px;">${{ number_format($ending_balance ?? 0.00, 2) }}</td>
            </tr>
        </tbody>
    </table>

    {{-- Ledger Entries --}}
    <table class="ledger-table">
        <thead>
            <tr>
                <th style="width: 15%;">Date</th>
                <th style="width: 18%;">Reference</th>
                <th>Description</th>
                <th style="text-align: right; width: 14%;">Debit ($)</th>
                <th style="text-align: right; width: 14%;">Credit ($)</th>
                <th style="text-align: right; width: 14%;">Balance ($)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($entries ?? [
                ['date' => '2026-09-01', 'reference' => 'BAL-FWD', 'description' => 'Opening Balance Forward', 'debit' => null, 'credit' => null, 'balance' => 0.00],
                ['date' => '2026-09-01', 'reference' => 'INV-2026-0711', 'description' => 'Monthly HOA Assessment - September', 'debit' => 250.00, 'credit' => null, 'balance' => 250.00],
                ['date' => '2026-09-05', 'reference' => 'REC-2026-3810', 'description' => 'Online Payment Received - Stripe (Card ending 4242)', 'debit' => null, 'credit' => 250.00, 'balance' => 0.00],
                ['date' => '2026-09-18', 'reference' => 'FEE-2026-0091', 'description' => 'Guest Clubhouse Booking Fee', 'debit' => 75.00, 'credit' => null, 'balance' => 75.00],
                ['date' => '2026-09-20', 'reference' => 'REC-2026-3914', 'description' => 'Payment Received - Clubhouse Booking', 'debit' => null, 'credit' => 75.00, 'balance' => 0.00]
            ] as $e)
                <tr>
                    <td style="font-family: monospace;">{{ $e['date'] }}</td>
                    <td style="font-family: monospace; color: #64748b;">{{ $e['reference'] }}</td>
                    <td>{{ $e['description'] }}</td>
                    <td style="text-align: right;">{{ $e['debit'] !== null ? '$'.number_format($e['debit'], 2) : '-' }}</td>
                    <td style="text-align: right; color: #059669;">{{ $e['credit'] !== null ? '$'.number_format($e['credit'], 2) : '-' }}</td>
                    <td style="text-align: right; font-weight: bold;">${{ number_format($e['balance'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
