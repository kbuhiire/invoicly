<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $invoice->number }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111827; padding: 32px; background: #fff; }

        /* ── Preview banner ── */
        .preview-banner { background: #e0f2fe; border: 1px solid #38bdf8; color: #0c4a6e; padding: 8px 12px; margin-bottom: 16px; font-size: 11px; font-weight: bold; }

        /* ── Top header row ── */
        .top-row { display: table; width: 100%; margin-bottom: 0; padding-bottom: 20px; }
        .top-left { display: table-cell; vertical-align: top; }
        .top-right { display: table-cell; vertical-align: top; text-align: right; width: 300px; }

        .issuer-name { font-size: 22px; font-weight: bold; color: #111827; margin-bottom: 6px; }

        .invoice-badge { display: inline-block; background: #e5e7eb; padding: 6px 24px; font-size: 11px; letter-spacing: 3px; color: #374151; font-weight: bold; text-transform: uppercase; margin-bottom: 12px; }

        .meta-table { width: 100%; border-collapse: collapse; }
        .meta-table td { padding: 3px 0; font-size: 11px; color: #374151; }
        .meta-table .meta-label { text-align: left; color: #374151; }
        .meta-table .meta-value { text-align: right; color: #111827; }
        .meta-table .meta-total-label { text-align: left; font-weight: bold; color: #111827; }
        .meta-table .meta-total-value { text-align: right; font-weight: bold; color: #111827; }

        /* ── Info row (Bill From / Tax Details / Payment Method) ── */
        .info-row { display: table; width: 100%; margin-bottom: 28px; border-top: 1px solid #e5e7eb; border-bottom: 1px solid #e5e7eb; padding: 16px 0; }
        .info-cell { display: table-cell; vertical-align: top; padding-right: 16px; text-align: left; }
        .info-cell:last-child { padding-right: 0; }
        .info-label { font-weight: bold; color: #374151; margin-bottom: 8px; font-size: 11px; }
        .info-muted { color: #6b7280; font-size: 10px; margin-bottom: 4px; }
        .addr-line { margin: 2px 0; color: #374151; line-height: 1.5; }
        .section-gap { margin-top: 14px; }

        /* ── Line items table ── */
        .items-table { width: 100%; border-collapse: collapse; margin-top: 4px; }
        .items-table th { background: #e5e7eb; padding: 8px 10px; text-align: left; font-size: 10px; letter-spacing: 1px; text-transform: uppercase; font-weight: bold; color: #374151; border: 1px solid #000; }
        .items-table th.right { text-align: right; }
        .items-table td { border: 1px solid #000; padding: 8px 10px; color: #111827; vertical-align: top; }
        .items-table td.right { text-align: right; }
        .items-table tr.line-item td { font-weight: bold; }
        .items-table tr.summary-row td { color: #374151; }
        .items-table tr.total-due td { }
        .items-table td.empty-cell { background: #fff; }

        /* ── Note / Signature ── */
        .note-block { margin-top: 20px; }
        .muted { color: #6b7280; font-size: 10px; margin-bottom: 4px; }
    </style>
</head>
<body>
    @if(!empty($isPreview))
        <div class="preview-banner">Preview — sample invoice (not saved)</div>
    @endif

    {{-- ── TOP HEADER ROW ── --}}
    <div class="top-row">
        <div class="top-left">
            @php
                $issuerName = '';
                if (isset($issuer) && $issuer) {
                    $issuerName = trim(trim((string) ($issuer->legal_first_name ?? '')).' '.trim((string) ($issuer->legal_last_name ?? '')));
                    if ($issuerName === '') {
                        $issuerName = (string) ($issuer->name ?? '');
                    }
                }
            @endphp
            @if($issuerName !== '')
                <div class="issuer-name">{{ $issuerName }}</div>
            @endif
            @if(isset($issuerLogoUri) && !empty($issuerLogoUri))
                <div style="margin-top:6px;"><img src="{{ $issuerLogoUri }}" style="max-height:48px;max-width:200px;" alt=""></div>
            @endif

            {{-- Bill To (inside left column, below issuer name) --}}
            @php
                $clientAddrLines = array_filter([
                    $invoice->client->street ?? null,
                    $invoice->client->city ?? null,
                    $invoice->client->postal_code ?? null,
                ], fn ($v) => $v !== null && (string) $v !== '');
                $clientCountryCode = $invoice->client->country ?? null;
                $clientCountryName = $clientCountryCode ? (config('countries', [])[$clientCountryCode] ?? $clientCountryCode) : null;
            @endphp
            <div style="margin-top:20px;">
                <div class="info-label">Bill To:</div>
                <div class="addr-line"><strong>{{ $invoice->client->name }}</strong></div>
                @foreach($clientAddrLines as $line)
                    <div class="addr-line">{{ $line }}</div>
                @endforeach
                @if($clientCountryName)
                    <div class="addr-line">{{ $clientCountryName }}</div>
                @endif
                @if(isset($invoice->client->vat_number) && $invoice->client->vat_number)
                    <div class="addr-line" style="margin-top:4px;color:#6b7280;font-size:10px;">VAT: {{ $invoice->client->vat_number }}</div>
                @endif
            </div>
        </div>
        <div class="top-right">
            <div><span class="invoice-badge">Invoice</span></div>
            <table class="meta-table" style="margin-top:4px;">
                <tr>
                    <td class="meta-label">#</td>
                    <td class="meta-value">{{ $invoice->number }}</td>
                </tr>
                <tr>
                    <td class="meta-label">Status</td>
                    <td class="meta-value">{{ str_replace('_', ' ', $invoice->status->value) }}</td>
                </tr>
                <tr>
                    <td class="meta-label">Invoice date</td>
                    <td class="meta-value">{{ $invoice->issue_date->format('F j, Y') }}</td>
                </tr>
                @if($invoice->due_date)
                <tr>
                    <td class="meta-label">Due date</td>
                    <td class="meta-value">{{ $invoice->due_date->format('F j, Y') }}</td>
                </tr>
                @endif
                <tr>
                    <td class="meta-total-label">Total amount</td>
                    <td class="meta-total-value">{{ $invoice->currency }} {{ number_format((float) $invoice->amount, 2) }}</td>
                </tr>
            </table>
        </div>
    </div>

    {{-- ── BILL FROM / TAX DETAILS / PAYMENT METHOD (3-column row) ── --}}
    @php
        // Issuer address lines
        $issuerAddrLines = [];
        if (isset($issuer) && $issuer) {
            $addr = $issuer->issuerAddressForInvoice();
            $pa = is_array($addr) ? $addr : [];
            $issuerAddrLines = array_filter([
                $pa['line1'] ?? null,
                $pa['line2'] ?? null,
                $pa['city'] ?? null,
                $pa['region'] ?? null,
                $pa['postal_code'] ?? null,
            ], fn ($v) => $v !== null && (string) $v !== '');
            $cc = $pa['country_code'] ?? null;
            if ($cc) {
                $countries = config('countries', []);
                if (isset($countries[$cc])) {
                    $issuerAddrLines[] = $countries[$cc];
                }
            }
        }

        // Tax details
        $vatId = $invoice->vat_id ?? null;
        $taxId = $invoice->tax_id ?? null;
        $hasTaxDetails = ($vatId || $taxId);

        // Payment details
        $paymentDetails = $invoice->payment_details ?? null;
    @endphp
    <div class="info-row">
        {{-- Bill From --}}
        <div class="info-cell" style="width:33%;">
            @if(isset($issuer) && $issuer)
                <div class="info-label">Bill From:</div>
                @if($issuerName !== '')
                    <div class="addr-line">{{ $issuerName }}</div>
                @endif
                @foreach($issuerAddrLines as $line)
                    <div class="addr-line">{{ $line }}</div>
                @endforeach
                @if(($issuer->invoice_show_email ?? false) && $issuer->email)
                    <div class="addr-line">{{ $issuer->email }}</div>
                @endif
                @if(($issuer->invoice_show_phone ?? false) && $issuer->phoneForInvoice())
                    <div class="addr-line">{{ $issuer->phoneForInvoice() }}</div>
                @endif
            @endif
        </div>

        {{-- Tax Details --}}
        <div class="info-cell" style="width:33%;">
            <div class="info-label" style="color:#6b7280;font-weight:normal;">Tax Details:</div>
            @if($hasTaxDetails)
                @if($vatId)
                    <div class="addr-line">VAT ID: {{ $vatId }}</div>
                @endif
                @if($taxId)
                    <div class="addr-line">Tax ID: {{ $taxId }}</div>
                @endif
            @endif
        </div>

        {{-- Payment Method --}}
        <div class="info-cell" style="width:34%;">
            <div class="info-label">Payment Method:</div>
            @if($paymentDetails)
                <div style="white-space:pre-wrap;line-height:1.6;">{{ $paymentDetails }}</div>
            @endif
        </div>
    </div>

    {{-- ── LINE ITEMS TABLE ── --}}
    @php
        // Derive unit label from invoice_type
        $invoiceType = strtolower((string) ($invoice->invoice_type ?? 'service'));
        $unitLabel = match($invoiceType) {
            'hour', 'hours'   => 'hrs',
            'day',  'days'    => 'days',
            'word', 'words'   => 'words',
            default           => 'items',
        };
        $vatAmount = $invoice->vat_amount ?? '0.00';
    @endphp

    <table class="items-table">
        <thead>
            <tr>
                <th style="width:50%;">Description</th>
                <th class="right" style="width:18%;">QTY (Unit)</th>
                <th class="right" style="width:16%;">Rate</th>
                <th class="right" style="width:16%;">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->lineItems as $line)
                <tr class="line-item">
                    <td style="white-space:pre-wrap;">{{ $line->description }}</td>
                    <td class="right">{{ $line->quantity }} ({{ $unitLabel }})</td>
                    <td class="right">{{ $invoice->currency }} {{ number_format((float) $line->unit_price, 2) }}</td>
                    <td class="right">{{ $invoice->currency }} {{ number_format((float) bcmul((string) $line->quantity, (string) $line->unit_price, 2), 2) }}</td>
                </tr>
            @endforeach

            {{-- VAT row --}}
            <tr class="summary-row">
                <td class="empty-cell" colspan="2"></td>
                <td class="right">VAT</td>
                <td class="right">{{ $invoice->currency }} {{ number_format((float) $vatAmount, 2) }}</td>
            </tr>

            {{-- Total Due row --}}
            <tr class="total-due">
                <td class="empty-cell" colspan="2"></td>
                <td class="right" style="color:#374151;">Total Due</td>
                <td class="right" style="font-weight:bold;">{{ $invoice->currency }} {{ number_format((float) $invoice->amount, 2) }}</td>
            </tr>
        </tbody>
    </table>

    @if ($invoice->amount_secondary !== null && $invoice->currency_secondary)
        <div style="text-align:right;margin-top:6px;font-size:10px;color:#6b7280;">
            ≈ {{ $invoice->currency_secondary }} {{ number_format((float) $invoice->amount_secondary, 2) }}
        </div>
    @endif

    {{-- ── NOTE ── --}}
    @if(isset($issuer) && $issuer && !empty($issuer->invoice_note))
        <div class="note-block">
            <div class="muted">Note</div>
            <div style="white-space:pre-wrap;margin-top:4px;">{{ $issuer->invoice_note }}</div>
        </div>
    @endif

    {{-- ── SIGNATURE ── --}}
    @if(isset($issuer) && $issuer && ($issuer->invoice_show_signature ?? false))
        @php
            $sigType = $issuer->invoice_signature_type ?? 'digital';
            $sigName = trim(trim((string) ($issuer->legal_first_name ?? '')).' '.trim((string) ($issuer->legal_last_name ?? '')));
            if ($sigName === '') {
                $sigName = (string) ($issuer->name ?? '');
            }
        @endphp
        <div style="margin-top:16px;">
            <div class="muted">Signature</div>
            @if($sigType === 'custom' && !empty($issuerSignatureUri))
                <div style="margin-top:4px;"><img src="{{ $issuerSignatureUri }}" style="max-height:72px;max-width:220px;" alt=""></div>
            @elseif($sigType === 'digital' && $sigName !== '')
                <div style="font-family:cursive;font-size:26px;color:#111827;margin-top:4px;">{{ $sigName }}</div>
            @endif
        </div>
    @endif
</body>
</html>
