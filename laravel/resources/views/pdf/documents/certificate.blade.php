<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Certificate {{ $reference ?? 'CERT-001' }}</title>
    <style>
        @page {
            margin: 0;
            size: a4 landscape;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: #0f172a;
            margin: 0;
            padding: 30px;
            background-color: #ffffff;
        }
        .outer-border {
            border: 8px solid #312e81;
            padding: 10px;
            height: 94%;
            box-sizing: border-box;
        }
        .inner-border {
            border: 2px dashed #6366f1;
            padding: 30px;
            height: 94%;
            text-align: center;
            box-sizing: border-box;
            position: relative;
        }
        .watermark {
            position: absolute;
            top: 35%;
            left: 20%;
            width: 60%;
            font-size: 80px;
            font-weight: 900;
            color: rgba(99, 102, 241, 0.05);
            text-transform: uppercase;
            transform: rotate(-15deg);
            z-index: 0;
            pointer-events: none;
        }
        .community-name {
            font-size: 16px;
            font-weight: 800;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #4338ca;
            margin-bottom: 8px;
        }
        .title {
            font-size: 32px;
            font-weight: 900;
            color: #1e1b4b;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 12px;
        }
        .subtitle {
            font-size: 13px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 24px;
        }
        .recipient {
            font-size: 26px;
            font-weight: 800;
            color: #312e81;
            border-bottom: 2px solid #c7d2fe;
            display: inline-block;
            padding: 0 40px 6px;
            margin-bottom: 18px;
        }
        .statement {
            font-size: 13px;
            line-height: 1.8;
            color: #334155;
            max-width: 750px;
            margin: 0 auto 28px;
        }
        .signatures-table {
            width: 100%;
            margin-top: 30px;
        }
        .sig-block {
            width: 33%;
            text-align: center;
            vertical-align: bottom;
            font-size: 11px;
        }
        .sig-line {
            border-top: 1px solid #94a3b8;
            margin: 40px 20px 6px;
            padding-top: 4px;
            font-weight: bold;
            color: #1e293b;
        }
        .cert-footer {
            font-size: 10px;
            color: #94a3b8;
            font-family: monospace;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="outer-border">
        <div class="inner-border">
            <div class="watermark">VERIFIED OFFICIAL</div>

            <div class="community-name">{{ $community_name ?? 'Community' }}</div>
            <div class="title">{{ $certificate_title ?? 'Certificate of Good Standing & Residency' }}</div>
            <div class="subtitle">Official Verification &bull; Reference #{{ $reference ?? 'CERT-2026-9041' }}</div>

            <p style="font-size: 13px; color: #64748b; margin-bottom: 10px;">This certifies that</p>

            <div class="recipient">{{ $recipient_name ?? 'Eleanor Rigby-Montague' }}</div>

            <div class="statement">
                Has complied with all community covenants, property maintenance standards, and has fully discharged all Homeowner Association assessments for Lot {{ $lot_number ?? '108' }} ({{ $street_address ?? 'Ocean View Terrace' }}). The holder is hereby granted unrestricted biometric and automated vehicle gate clearance for the duration of the current operating fiscal term.
            </div>

            <table class="signatures-table">
                <tr>
                    <td class="sig-block">
                        <div class="sig-line">Arthur Pendelton</div>
                        <div style="color: #64748b;">President, Board of Trustees</div>
                    </td>
                    <td class="sig-block">
                        <div style="display: inline-block; border: 2px double #4338ca; border-radius: 50%; width: 70px; height: 70px; line-height: 70px; font-weight: 900; font-size: 10px; color: #4338ca; text-align: center;">
                            SEAL
                        </div>
                    </td>
                    <td class="sig-block">
                        <div class="sig-line">Capt. Marcus Sterling</div>
                        <div style="color: #64748b;">Chief of Estate Security</div>
                    </td>
                </tr>
            </table>

            <div class="cert-footer">
                VALID THROUGH: {{ $valid_through ?? date('M d, Y', strtotime('+1 year')) }} &bull; DIGITAL SIGNATURE CHECKSUM: {{ strtoupper(substr(hash('sha256', ($reference ?? 'CERT').time()), 0, 24)) }}
            </div>
        </div>
    </div>
</body>
</html>
