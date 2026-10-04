<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Operations Report {{ $period ?? 'October 2026' }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: #0f172a;
            margin: 0;
            padding: 24px;
            font-size: 12px;
            line-height: 1.5;
        }
        .header {
            border-bottom: 2px solid #0f172a;
            padding-bottom: 14px;
            margin-bottom: 20px;
        }
        .report-title {
            font-size: 20px;
            font-weight: 800;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: -0.5px;
        }
        .report-meta {
            font-size: 11px;
            color: #64748b;
        }
        .kpi-table {
            width: 100%;
            margin-bottom: 24px;
            border-collapse: collapse;
        }
        .kpi-card {
            width: 25%;
            padding: 12px;
            border: 1px solid #e2e8f0;
            background-color: #f8fafc;
            border-radius: 6px;
        }
        .kpi-label {
            font-size: 9px;
            font-weight: bold;
            color: #64748b;
            text-transform: uppercase;
        }
        .kpi-val {
            font-size: 18px;
            font-weight: 900;
            color: #1e1b4b;
            margin-top: 4px;
        }
        .section-title {
            font-size: 13px;
            font-weight: 800;
            color: #1e293b;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 4px;
            margin: 20px 0 10px;
            text-transform: uppercase;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .data-table th {
            background-color: #f1f5f9;
            border: 1px solid #cbd5e1;
            padding: 6px 8px;
            text-align: left;
            font-size: 10px;
            text-transform: uppercase;
            color: #475569;
        }
        .data-table td {
            border: 1px solid #e2e8f0;
            padding: 6px 8px;
            font-size: 11px;
        }
        .status-pill {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            background-color: #ecfdf5;
            color: #059669;
        }
    </style>
</head>
<body>
    <div class="header">
        <table style="width: 100%;">
            <tr>
                <td>
                    <div class="report-title">Monthly Security &amp; Operations Report</div>
                    <div class="report-meta">{{ $community_name ?? 'Community' }} &bull; Period: {{ $period ?? 'October 2026' }}</div>
                </td>
                <td style="text-align: right; font-size: 11px; color: #64748b;">
                    <div>Report #: <strong>{{ $reference ?? 'REP-2026-10' }}</strong></div>
                    <div>Generated: {{ $generated_at ?? date('Y-m-d H:i:s') }}</div>
                </td>
            </tr>
        </table>
    </div>

    {{-- KPI Cards --}}
    <table class="kpi-table">
        <tr>
            <td class="kpi-card" style="padding-right: 6px;">
                <div class="kpi-label">Gate Crossings</div>
                <div class="kpi-val">{{ number_format($total_crossings ?? 18450) }}</div>
                <div style="font-size: 10px; color: #10b981;">+8.4% vs last period</div>
            </td>
            <td class="kpi-card" style="padding: 0 6px;">
                <div class="kpi-label">Active Guest Passes</div>
                <div class="kpi-val">{{ number_format($active_passes ?? 2314) }}</div>
                <div style="font-size: 10px; color: #6366f1;">98.2% scan pass rate</div>
            </td>
            <td class="kpi-card" style="padding: 0 6px;">
                <div class="kpi-label">Security Incidents</div>
                <div class="kpi-val" style="color: #f43f5e;">{{ $incidents_count ?? 3 }}</div>
                <div style="font-size: 10px; color: #10b981;">100% resolved</div>
            </td>
            <td class="kpi-card" style="padding-left: 6px;">
                <div class="kpi-label">Dues Collection Rate</div>
                <div class="kpi-val" style="color: #059669;">{{ $collection_rate ?? '97.6%' }}</div>
                <div style="font-size: 10px; color: #64748b;">Target: 95.0%</div>
            </td>
        </tr>
    </table>

    <div class="section-title">Perimeter &amp; Checkpoint Incident Log</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 15%;">Timestamp</th>
                <th style="width: 20%;">Incident Type</th>
                <th style="width: 25%;">Location</th>
                <th>Resolution Summary</th>
                <th style="width: 12%;">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($incidents ?? [
                ['timestamp' => '2026-10-04 14:22', 'type' => 'Tailgating Vehicle', 'location' => 'Gate 1 (Visitor Inbound)', 'resolution' => 'Guard triggered barrier stop; visitor authenticated.', 'status' => 'Resolved'],
                ['timestamp' => '2026-10-12 21:05', 'type' => 'Expired QR Token', 'location' => 'North Perimeter Kiosk', 'resolution' => 'Resident contacted via automated phone dialer; pass refreshed.', 'status' => 'Resolved'],
                ['timestamp' => '2026-10-23 03:40', 'type' => 'Perimeter Sensor Trip', 'location' => 'West Canal Fence #14', 'resolution' => 'Drone sweep confirmed wild animal; sensor re-calibrated.', 'status' => 'Resolved']
            ] as $inc)
                <tr>
                    <td style="font-family: monospace;">{{ $inc['timestamp'] }}</td>
                    <td><strong>{{ $inc['type'] }}</strong></td>
                    <td>{{ $inc['location'] }}</td>
                    <td>{{ $inc['resolution'] }}</td>
                    <td><span class="status-pill">{{ $inc['status'] }}</span></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="section-title">Financial Ledger &amp; Assessment Reconciliation</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Fund Category</th>
                <th style="text-align: right;">Billed ($)</th>
                <th style="text-align: right;">Collected ($)</th>
                <th style="text-align: right;">Outstanding ($)</th>
                <th style="text-align: right;">Ratio (%)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Operations &amp; Security Services</td>
                <td style="text-align: right;">$84,000.00</td>
                <td style="text-align: right;">$82,320.00</td>
                <td style="text-align: right;">$1,680.00</td>
                <td style="text-align: right; font-weight: bold;">98.0%</td>
            </tr>
            <tr>
                <td>Infrastructure Capital Reserve</td>
                <td style="text-align: right;">$21,000.00</td>
                <td style="text-align: right;">$20,496.00</td>
                <td style="text-align: right;">$504.00</td>
                <td style="text-align: right; font-weight: bold;">97.6%</td>
            </tr>
            <tr>
                <td>Amenity &amp; Clubhouse Licensing</td>
                <td style="text-align: right;">$9,500.00</td>
                <td style="text-align: right;">$9,120.00</td>
                <td style="text-align: right;">$380.00</td>
                <td style="text-align: right; font-weight: bold;">96.0%</td>
            </tr>
        </tbody>
    </table>

    <table style="width: 100%; margin-top: 30px;">
        <tr>
            <td style="width: 50%; font-size: 11px;">
                <div style="border-top: 1px solid #94a3b8; width: 80%; padding-top: 4px; font-weight: bold;">
                    Prepared by: Operations Directorate
                </div>
            </td>
            <td style="width: 50%; font-size: 11px; text-align: right;">
                <div style="border-top: 1px solid #94a3b8; width: 80%; margin-left: auto; padding-top: 4px; font-weight: bold;">
                    Approved by: Chief Security Officer
                </div>
            </td>
        </tr>
    </table>
</body>
</html>
