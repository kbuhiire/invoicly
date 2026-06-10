<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $quote->number }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111827; padding: 32px; background: #fff; }

        .top-row { display: table; width: 100%; padding-bottom: 20px; }
        .top-left { display: table-cell; vertical-align: top; }
        .top-right { display: table-cell; vertical-align: top; text-align: right; width: 300px; }

        .issuer-name { font-size: 22px; font-weight: bold; color: #111827; margin-bottom: 6px; }
        .logo { max-height: 48px; max-width: 160px; margin-bottom: 8px; }

        .doc-badge { display: inline-block; background: #e5e7eb; padding: 6px 24px; font-size: 11px; letter-spacing: 3px; color: #374151; font-weight: bold; text-transform: uppercase; margin-bottom: 12px; }

        .meta-table { width: 100%; border-collapse: collapse; }
        .meta-table td { padding: 3px 0; font-size: 11px; color: #374151; }
        .meta-table .meta-label { text-align: left; color: #374151; }
        .meta-table .meta-value { text-align: right; color: #111827; }
        .meta-table .meta-total-label { text-align: left; font-weight: bold; color: #111827; }
        .meta-table .meta-total-value { text-align: right; font-weight: bold; color: #111827; }

        .info-row { display: table; width: 100%; margin-bottom: 28px; border-top: 1px solid #e5e7eb; border-bottom: 1px solid #e5e7eb; padding: 16px 0; }
        .info-cell { display: table-cell; vertical-align: top; padding-right: 16px; text-align: left; }
        .info-label { font-weight: bold; color: #374151; margin-bottom: 8px; font-size: 11px; }
        .addr-line { margin: 2px 0; color: #374151; line-height: 1.5; }

        .items-table { width: 100%; border-collapse: collapse; margin-top: 4px; }
        .items-table th { background: #e5e7eb; padding: 8px 10px; text-align: left; font-size: 10px; letter-spacing: 1px; text-transform: uppercase; font-weight: bold; color: #374151; border: 1px solid #000; }
        .items-table th.right { text-align: right; }
        .items-table td { border: 1px solid #000; padding: 8px 10px; color: #111827; vertical-align: top; }
        .items-table td.right { text-align: right; }

        .note-block { margin-top: 20px; }
        .muted { color: #6b7280; font-size: 10px; margin-bottom: 4px; }
    </style>
</head>
<body>
    <div class="top-row">
        <div class="top-left">
            @if(!empty($issuerLogoUri))
                <img class="logo" src="{{ $issuerLogoUri }}" alt="">
            @endif
            <div class="issuer-name">{{ $issuer?->name }}</div>
        </div>
        <div class="top-right">
            <div class="doc-badge">Quote</div>
            <table class="meta-table">
                <tr>
                    <td class="meta-label">Quote no.</td>
                    <td class="meta-value">{{ $quote->number }}</td>
                </tr>
                <tr>
                    <td class="meta-label">Issue date</td>
                    <td class="meta-value">{{ $quote->issue_date->format('M j, Y') }}</td>
                </tr>
                @if($quote->expiry_date)
                    <tr>
                        <td class="meta-label">Valid until</td>
                        <td class="meta-value">{{ $quote->expiry_date->format('M j, Y') }}</td>
                    </tr>
                @endif
                <tr>
                    <td class="meta-total-label">Total</td>
                    <td class="meta-total-value">{{ $quote->currency }} {{ number_format((float) $quote->amount + (float) ($quote->vat_amount ?? 0), 2) }}</td>
                </tr>
            </table>
        </div>
    </div>

    <div class="info-row">
        <div class="info-cell">
            <div class="info-label">Quote from</div>
            <div class="addr-line">{{ $issuer?->name }}</div>
            @if($issuer?->email)
                <div class="addr-line">{{ $issuer->email }}</div>
            @endif
        </div>
        <div class="info-cell">
            <div class="info-label">Quote for</div>
            <div class="addr-line">{{ $quote->client?->name }}</div>
            @if($quote->client?->street)
                <div class="addr-line">{{ $quote->client->street }}</div>
            @endif
            @if($quote->client?->city || $quote->client?->postal_code)
                <div class="addr-line">{{ trim(($quote->client->city ?? '').' '.($quote->client->postal_code ?? '')) }}</div>
            @endif
            @if($quote->client?->email)
                <div class="addr-line">{{ $quote->client->email }}</div>
            @endif
        </div>
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th>Description</th>
                <th class="right">Qty</th>
                <th class="right">Unit price</th>
                <th class="right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($quote->lineItems as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td class="right">{{ rtrim(rtrim(number_format((float) $item->quantity, 3), '0'), '.') }}</td>
                    <td class="right">{{ number_format((float) $item->unit_price, 2) }}</td>
                    <td class="right">{{ number_format((float) $item->quantity * (float) $item->unit_price, 2) }}</td>
                </tr>
            @endforeach
            <tr>
                <td colspan="3" class="right">Subtotal</td>
                <td class="right">{{ $quote->currency }} {{ number_format((float) $quote->amount, 2) }}</td>
            </tr>
            @if($quote->vat_amount !== null)
                <tr>
                    <td colspan="3" class="right">Tax</td>
                    <td class="right">{{ $quote->currency }} {{ number_format((float) $quote->vat_amount, 2) }}</td>
                </tr>
            @endif
            <tr>
                <td colspan="3" class="right" style="font-weight: bold;">Total</td>
                <td class="right" style="font-weight: bold;">{{ $quote->currency }} {{ number_format((float) $quote->amount + (float) ($quote->vat_amount ?? 0), 2) }}</td>
            </tr>
        </tbody>
    </table>

    @if($quote->payer_memo)
        <div class="note-block">
            <div class="muted">Note</div>
            <div>{{ $quote->payer_memo }}</div>
        </div>
    @endif

    <div class="note-block">
        <div class="muted">
            This is a quotation, not a request for payment.
            @if($quote->expiry_date)
                Prices are valid until {{ $quote->expiry_date->format('M j, Y') }}.
            @endif
        </div>
    </div>
</body>
</html>
