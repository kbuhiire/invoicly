<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $creditNote->number }}</title>
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

        .info-row { display: table; width: 100%; margin-bottom: 28px; border-top: 1px solid #e5e7eb; border-bottom: 1px solid #e5e7eb; padding: 16px 0; }
        .info-cell { display: table-cell; vertical-align: top; padding-right: 16px; text-align: left; }
        .info-label { font-weight: bold; color: #374151; margin-bottom: 8px; font-size: 11px; }
        .addr-line { margin: 2px 0; color: #374151; line-height: 1.5; }

        .amount-table { width: 100%; border-collapse: collapse; margin-top: 4px; }
        .amount-table th { background: #e5e7eb; padding: 8px 10px; text-align: left; font-size: 10px; letter-spacing: 1px; text-transform: uppercase; font-weight: bold; color: #374151; border: 1px solid #000; }
        .amount-table th.right { text-align: right; }
        .amount-table td { border: 1px solid #000; padding: 8px 10px; color: #111827; vertical-align: top; }
        .amount-table td.right { text-align: right; font-weight: bold; }

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
            <div class="doc-badge">Credit Note</div>
            <table class="meta-table">
                <tr>
                    <td class="meta-label">Credit note no.</td>
                    <td class="meta-value">{{ $creditNote->number }}</td>
                </tr>
                <tr>
                    <td class="meta-label">Issue date</td>
                    <td class="meta-value">{{ $creditNote->issue_date->format('M j, Y') }}</td>
                </tr>
                @if($creditNote->invoice)
                    <tr>
                        <td class="meta-label">Applies to invoice</td>
                        <td class="meta-value">{{ $creditNote->invoice->number }}</td>
                    </tr>
                @endif
                <tr>
                    <td class="meta-label">Status</td>
                    <td class="meta-value">{{ $creditNote->status->label() }}</td>
                </tr>
            </table>
        </div>
    </div>

    <div class="info-row">
        <div class="info-cell">
            <div class="info-label">Issued by</div>
            <div class="addr-line">{{ $issuer?->name }}</div>
            @if($issuer?->email)
                <div class="addr-line">{{ $issuer->email }}</div>
            @endif
        </div>
        <div class="info-cell">
            <div class="info-label">Credited to</div>
            <div class="addr-line">{{ $creditNote->client?->name }}</div>
            @if($creditNote->client?->street)
                <div class="addr-line">{{ $creditNote->client->street }}</div>
            @endif
            @if($creditNote->client?->city || $creditNote->client?->postal_code)
                <div class="addr-line">{{ trim(($creditNote->client->city ?? '').' '.($creditNote->client->postal_code ?? '')) }}</div>
            @endif
            @if($creditNote->client?->email)
                <div class="addr-line">{{ $creditNote->client->email }}</div>
            @endif
        </div>
    </div>

    <table class="amount-table">
        <thead>
            <tr>
                <th>Description</th>
                <th class="right">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $creditNote->memo ?: 'Credit' }}</td>
                <td class="right">{{ $creditNote->currency }} {{ number_format((float) $creditNote->amount, 2) }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Total credit</td>
                <td class="right">{{ $creditNote->currency }} {{ number_format((float) $creditNote->amount, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="note-block">
        <div class="muted">
            This credit note reduces the amount owed by the client.
            @if($creditNote->invoice)
                It has been applied against invoice {{ $creditNote->invoice->number }}.
            @endif
        </div>
    </div>
</body>
</html>
