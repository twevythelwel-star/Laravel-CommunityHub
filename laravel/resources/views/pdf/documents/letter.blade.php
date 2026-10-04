<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Formal Notice {{ $reference ?? 'LTR-001' }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: #1e293b;
            margin: 0;
            padding: 30px;
            font-size: 13px;
            line-height: 1.6;
        }
        .letterhead {
            border-bottom: 2px solid #312e81;
            padding-bottom: 14px;
            margin-bottom: 30px;
        }
        .community-title {
            font-size: 20px;
            font-weight: 900;
            color: #312e81;
            text-transform: uppercase;
            letter-spacing: -0.5px;
        }
        .community-sub {
            font-size: 11px;
            color: #64748b;
        }
        .meta-line {
            margin-bottom: 20px;
            font-size: 12px;
            color: #475569;
        }
        .recipient-block {
            margin-bottom: 24px;
            font-size: 13px;
        }
        .subject-line {
            font-size: 14px;
            font-weight: 800;
            color: #1e1b4b;
            text-transform: uppercase;
            margin: 20px 0 16px;
            padding: 6px 0;
            border-top: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
        }
        .body-p {
            margin-bottom: 14px;
            text-align: justify;
        }
        .callout-box {
            background-color: #f8fafc;
            border-left: 4px solid #4f46e5;
            padding: 12px;
            margin: 16px 0;
            font-size: 12px;
        }
        .signature-section {
            margin-top: 40px;
        }
    </style>
</head>
<body>
    <div class="letterhead">
        <table style="width: 100%;">
            <tr>
                <td>
                    <div class="community-title">{{ $community_name ?? 'Community Estates' }}</div>
                    <div class="community-sub">Office of the Board of Trustees &bull; Architectural Review Committee</div>
                    <div class="community-sub">Central Way &bull; Tel: (555) 019-2831 &bull; security@community.io</div>
                </td>
                <td style="text-align: right; vertical-align: top; font-size: 11px; color: #64748b;">
                    <div>Ref: <strong>{{ $reference ?? 'LTR-2026-0312' }}</strong></div>
                    <div>Date: {{ $date ?? date('F d, Y') }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="recipient-block">
        <div><strong>{{ $recipient_name ?? 'Marcus & Elena Vance' }}</strong></div>
        <div>Lot {{ $lot_number ?? '42' }} &bull; {{ $street_address ?? 'Palmetto Way' }}</div>
        <div>{{ $community_name ?? 'Community Estates' }}</div>
    </div>

    <div class="subject-line">
        Re: {{ $subject ?? 'Approval of Architectural Variance Application #AV-2026-09' }}
    </div>

    <p class="body-p">Dear {{ $recipient_name ?? 'Property Owners' }},</p>

    <p class="body-p">
        We are pleased to inform you that the Architectural Review Committee (ARC) and the Board of Trustees of {{ $community_name ?? 'Community Estates' }} met on {{ date('F d, Y', strtotime('-5 days')) }} and formally reviewed your application for the installation of an integrated solar canopy and perimeter landscape enhancement on Lot {{ $lot_number ?? '42' }}.
    </p>

    <div class="callout-box">
        <strong>Decision Summary:</strong> The committee has unanimously <strong>APPROVED</strong> your variance submission with zero conditions, finding it fully compliant with Estate Environmental and Covenants Section 4.12.
    </div>

    <p class="body-p">
        Contractors and delivery logistics teams may access the community using automated commercial passes generated via your resident portal. Please ensure all heavy vehicle deliveries adhere to the estate gate hours (07:00 to 18:00 Monday through Saturday).
    </p>

    <p class="body-p">
        Thank you for your ongoing investment into the architectural aesthetics and sustainability of our community. If you have any questions regarding gate clearance for your construction personnel, please contact the Security Desk.
    </p>

    <div class="signature-section">
        <p style="margin-bottom: 30px;">Sincerely,</p>
        <div style="border-top: 1px solid #94a3b8; width: 220px; padding-top: 6px; font-weight: bold; color: #0f172a;">
            Victoria Sterling-Hayes
        </div>
        <div style="font-size: 11px; color: #64748b;">Chairperson, Architectural Review Committee</div>
        <div style="font-size: 11px; color: #64748b;">{{ $community_name ?? 'Community HOA' }}</div>
    </div>
</body>
</html>
