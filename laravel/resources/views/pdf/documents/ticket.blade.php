<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Admission Ticket {{ $ticket_code ?? 'TCK-001' }}</title>
    <style>
        @page {
            margin: 0;
            size: a5 landscape;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: #0f172a;
            margin: 0;
            padding: 24px;
            background-color: #f1f5f9;
        }
        .ticket-wrapper {
            background-color: #ffffff;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            border: 2px solid #cbd5e1;
            height: 90%;
            display: table;
            width: 100%;
        }
        .main-stub {
            display: table-cell;
            width: 72%;
            padding: 24px;
            vertical-align: top;
            border-right: 2px dashed #94a3b8;
            box-sizing: border-box;
        }
        .entry-stub {
            display: table-cell;
            width: 28%;
            padding: 20px;
            vertical-align: top;
            background-color: #faf5ff;
            text-align: center;
            box-sizing: border-box;
        }
        .event-badge {
            display: inline-block;
            background-color: #e0e7ff;
            color: #3730a3;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            padding: 3px 8px;
            border-radius: 9999px;
            margin-bottom: 8px;
        }
        .event-title {
            font-size: 20px;
            font-weight: 900;
            color: #1e1b4b;
            text-transform: uppercase;
            margin-bottom: 6px;
        }
        .meta-line {
            font-size: 12px;
            color: #64748b;
            margin-bottom: 16px;
        }
        .details-grid {
            width: 100%;
            font-size: 11px;
            margin-bottom: 14px;
        }
        .details-grid td {
            padding: 3px 0;
        }
        .barcode {
            font-family: 'Courier New', Courier, monospace;
            font-size: 24px;
            letter-spacing: 5px;
            font-weight: bold;
            color: #0f172a;
            margin-top: 10px;
        }
        .stub-tier {
            font-size: 14px;
            font-weight: 900;
            color: #7e22ce;
            text-transform: uppercase;
            margin-bottom: 6px;
        }
    </style>
</head>
<body>
    <div class="ticket-wrapper">
        <div class="main-stub">
            <div class="event-badge">{{ $access_tier ?? 'VIP Resident Pass' }}</div>
            <div class="event-title">{{ $event_name ?? 'Annual Community Gala & Wine Tasting' }}</div>
            <div class="meta-line">{{ $community_name ?? 'Community' }} &bull; Grand Clubhouse Pavilion</div>

            <table class="details-grid">
                <tr>
                    <td style="color: #64748b; width: 30%;">Date &amp; Time:</td>
                    <td><strong>{{ $event_date ?? 'Saturday, Nov 14, 2026 @ 19:00' }}</strong></td>
                </tr>
                <tr>
                    <td style="color: #64748b;">Ticket Holder:</td>
                    <td><strong>{{ $recipient_name ?? 'Alexander Vance' }}</strong> (Lot {{ $lot_number ?? '42' }})</td>
                </tr>
                <tr>
                    <td style="color: #64748b;">Admit Count:</td>
                    <td><strong>{{ $admit_count ?? '2 Guests' }}</strong></td>
                </tr>
                <tr>
                    <td style="color: #64748b;">Gate Entry Route:</td>
                    <td>Gate 1 Main Boulevard (Express Lane Valet)</td>
                </tr>
            </table>

            <div style="font-size: 9px; color: #94a3b8; line-height: 1.3;">
                Terms: Non-transferable credential issued exclusively to registered estate residents and invited companions. Please present ticket or mobile QR scanner pass at the clubhouse entrance for barcode redemption.
            </div>
        </div>

        <div class="entry-stub">
            <div class="stub-tier">{{ $access_tier ?? 'VIP' }}</div>
            <div style="font-size: 11px; font-weight: bold; color: #0f172a;">{{ $recipient_name ?? 'Alexander Vance' }}</div>
            <div style="font-size: 10px; color: #64748b;">Lot #{{ $lot_number ?? '42' }}</div>

            <div class="barcode">||| | |||| | |||</div>
            <div style="font-size: 9px; font-family: monospace; color: #475569;">{{ $ticket_code ?? 'TCK-8492-01A' }}</div>

            <div style="margin-top: 14px; font-size: 8px; color: #94a3b8; text-transform: uppercase;">
                Valid For 1-Time Entry
            </div>
        </div>
    </div>
</body>
</html>
