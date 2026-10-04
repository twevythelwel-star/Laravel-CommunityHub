<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Government Declaration Form {{ $reference ?? 'FORM-RES-104' }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: #0f172a;
            margin: 0;
            padding: 24px;
            font-size: 11px;
            line-height: 1.4;
        }
        .form-header {
            text-align: center;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }
        .form-id {
            font-size: 10px;
            font-weight: bold;
            color: #475569;
            text-transform: uppercase;
        }
        .form-title {
            font-size: 16px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #0f172a;
            margin: 4px 0;
        }
        .form-subtitle {
            font-size: 10px;
            color: #64748b;
        }
        .section-header {
            background-color: #0f172a;
            color: #ffffff;
            font-size: 10px;
            font-weight: bold;
            padding: 4px 8px;
            text-transform: uppercase;
            margin-top: 14px;
            margin-bottom: 8px;
        }
        .field-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .field-table td {
            border: 1px solid #cbd5e1;
            padding: 6px 8px;
            vertical-align: top;
        }
        .label {
            font-size: 9px;
            font-weight: bold;
            color: #64748b;
            text-transform: uppercase;
            display: block;
            margin-bottom: 2px;
        }
        .val {
            font-size: 12px;
            font-weight: 600;
            color: #0f172a;
        }
        .affidavit-text {
            font-size: 10px;
            text-align: justify;
            color: #334155;
            line-height: 1.4;
            padding: 8px;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            margin-bottom: 14px;
        }
        .sign-table {
            width: 100%;
            margin-top: 20px;
        }
        .sign-col {
            width: 48%;
            border: 1px solid #cbd5e1;
            padding: 12px;
            vertical-align: top;
            box-sizing: border-box;
        }
    </style>
</head>
<body>
    <div class="form-header">
        <div class="form-id">MUNICIPAL HOUSING &amp; LOCAL JURISDICTION DIRECTORY &bull; STATUTORY FORM RES-104</div>
        <div class="form-title">DECLARATION OF RESIDENCY &amp; PARCEL OCCUPANCY</div>
        <div class="form-subtitle">For Official Civic Registry, Utility Meter Provisioning, and Voter Precinct Confirmation</div>
    </div>

    {{-- Section 1 --}}
    <div class="section-header">Section 1: Registrant &amp; Household Information</div>
    <table class="field-table">
        <tr>
            <td style="width: 50%;">
                <span class="label">Full Legal Name of Declarant:</span>
                <span class="val">{{ $recipient_name ?? 'Alexander Montgomery Vance' }}</span>
            </td>
            <td style="width: 25%;">
                <span class="label">National Identification / Passport:</span>
                <span class="val">{{ $id_number ?? 'ID-849-209-114' }}</span>
            </td>
            <td style="width: 25%;">
                <span class="label">Date of Birth:</span>
                <span class="val">{{ $dob ?? '1984-06-18' }}</span>
            </td>
        </tr>
        <tr>
            <td>
                <span class="label">Primary Email Address:</span>
                <span class="val">{{ $recipient_email ?? 'resident@community.io' }}</span>
            </td>
            <td colspan="2">
                <span class="label">Primary Mobile Contact:</span>
                <span class="val">{{ $phone ?? '+1 (555) 019-8422' }}</span>
            </td>
        </tr>
    </table>

    {{-- Section 2 --}}
    <div class="section-header">Section 2: Cadastral Boundary &amp; Real Property Specifications</div>
    <table class="field-table">
        <tr>
            <td style="width: 40%;">
                <span class="label">Gated Community / Residential Scheme:</span>
                <span class="val">{{ $community_name ?? 'Community Scheme' }}</span>
            </td>
            <td style="width: 20%;">
                <span class="label">Lot / Parcel #:</span>
                <span class="val">Lot {{ $lot_number ?? '42' }}</span>
            </td>
            <td style="width: 40%;">
                <span class="label">Cadastral Zone &amp; Datum:</span>
                <span class="val">{{ $cadastral_zone ?? 'Zone 4-North (WGS84)' }}</span>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <span class="label">Physical Street Address:</span>
                <span class="val">{{ $street_address ?? ('42 Palmetto Way, ' . ($community_name ?? 'Community Scheme') . ', Coastal Parish') }}</span>
            </td>
            <td>
                <span class="label">Tenure Type:</span>
                <span class="val">{{ $tenure_type ?? 'Fee Simple Homeowner' }}</span>
            </td>
        </tr>
    </table>

    {{-- Section 3 --}}
    <div class="section-header">Section 3: Statutory Declaration &amp; Legal Affirmation</div>
    <div class="affidavit-text">
        I, the undersigned declarant, hereby solemnly affirm under penalty of civil perjury that I am the bona fide occupant and registered proprietor of the real property designated above. I further declare that all household entries and resident vehicles registered under Community Hub pass credentials are true and correct representations of authorized inhabitants.
    </div>

    {{-- Section 4 --}}
    <div class="section-header">Section 4: Attestation &amp; Community Seal Block</div>
    <table class="sign-table">
        <tr>
            <td class="sign-col" style="margin-right: 4%;">
                <span class="label">Declarant Signature:</span>
                <div style="height: 40px; border-bottom: 1px solid #94a3b8; margin-top: 10px;"></div>
                <div style="font-size: 10px; margin-top: 4px;">{{ $recipient_name ?? 'Alexander Montgomery Vance' }}</div>
                <div style="font-size: 9px; color: #64748b;">Date: {{ date('F d, Y') }}</div>
            </td>
            <td class="sign-col">
                <span class="label">HOA / Municipal Notary Verification:</span>
                <div style="height: 40px; border-bottom: 1px solid #94a3b8; margin-top: 10px;"></div>
                <div style="font-size: 10px; margin-top: 4px;">Arthur Pendelton (Trustee Reg #TR-9940)</div>
                <div style="font-size: 9px; color: #64748b;">Seal Registered &bull; Verification #: <strong>{{ $reference ?? 'GOV-2026-8819' }}</strong></div>
            </td>
        </tr>
    </table>
</body>
</html>
