<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ $isOverdue ? 'Invoice overdue' : 'Invoice reminder' }}</title>
    <style>
        body { margin: 0; padding: 0; background-color: #f3f4f6; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #111827; }
        .wrapper { max-width: 600px; margin: 40px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,0.08); }
        .header { background-color: #111827; padding: 32px 40px; }
        .header h1 { margin: 0; font-size: 20px; font-weight: 700; color: #ffffff; letter-spacing: -0.3px; }
        .body { padding: 32px 40px; }
        .body p { margin: 0 0 16px; font-size: 15px; line-height: 1.6; color: #374151; }
        .badge { display: inline-block; margin-bottom: 16px; padding: 4px 12px; border-radius: 9999px; font-size: 12px; font-weight: 600; }
        .badge.overdue { background: #fef2f2; color: #b91c1c; }
        .badge.upcoming { background: #eff6ff; color: #1d4ed8; }
        .invoice-card { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px; padding: 20px 24px; margin: 24px 0; }
        .invoice-card table { width: 100%; border-collapse: collapse; }
        .invoice-card td { padding: 6px 0; font-size: 14px; color: #374151; vertical-align: top; }
        .invoice-card td:first-child { color: #6b7280; width: 160px; }
        .invoice-card td strong { color: #111827; font-weight: 600; }
        .footer { padding: 20px 40px; background: #f9fafb; border-top: 1px solid #e5e7eb; font-size: 12px; color: #9ca3af; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <h1>{{ $businessName }}</h1>
        </div>
        <div class="body">
            @if ($isOverdue)
                <span class="badge overdue">Overdue</span>
                <p>Hi {{ $clientName }},</p>
                <p>
                    Our records show that invoice <strong>{{ $invoiceNumber }}</strong> was due on
                    <strong>{{ $dueDate }}</strong> and is now overdue. We'd appreciate it if you could
                    arrange payment at your earliest convenience.
                </p>
            @else
                <span class="badge upcoming">Due soon</span>
                <p>Hi {{ $clientName }},</p>
                <p>
                    This is a friendly reminder that invoice <strong>{{ $invoiceNumber }}</strong> is due on
                    <strong>{{ $dueDate }}</strong>.
                </p>
            @endif

            <div class="invoice-card">
                <table>
                    <tr>
                        <td>Invoice number</td>
                        <td><strong>{{ $invoiceNumber }}</strong></td>
                    </tr>
                    <tr>
                        <td>Invoice total</td>
                        <td>{{ $currency }} {{ $amount }}</td>
                    </tr>
                    <tr>
                        <td>Outstanding balance</td>
                        <td><strong>{{ $currency }} {{ $outstanding }}</strong></td>
                    </tr>
                    <tr>
                        <td>Due date</td>
                        <td>{{ $dueDate }}</td>
                    </tr>
                </table>
            </div>

            <p>If you've already made this payment, please disregard this message. Thank you!</p>
        </div>
        <div class="footer">
            <p>This is an automated payment reminder from {{ $businessName }}.</p>
        </div>
    </div>
</body>
</html>
