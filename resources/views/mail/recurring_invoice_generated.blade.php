<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Invoice Generated</title>
    <style>
        body { margin: 0; padding: 0; background-color: #f3f4f6; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #111827; }
        .wrapper { max-width: 600px; margin: 40px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,0.08); }
        .header { background-color: #111827; padding: 32px 40px; }
        .header h1 { margin: 0; font-size: 20px; font-weight: 700; color: #ffffff; letter-spacing: -0.3px; }
        .body { padding: 32px 40px; }
        .body p { margin: 0 0 16px; font-size: 15px; line-height: 1.6; color: #374151; }
        .invoice-card { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px; padding: 20px 24px; margin: 24px 0; }
        .invoice-card table { width: 100%; border-collapse: collapse; }
        .invoice-card td { padding: 6px 0; font-size: 14px; color: #374151; vertical-align: top; }
        .invoice-card td:first-child { color: #6b7280; width: 140px; }
        .invoice-card td strong { color: #111827; font-weight: 600; }
        .btn { display: inline-block; margin-top: 8px; padding: 12px 24px; background-color: #111827; color: #ffffff; text-decoration: none; border-radius: 8px; font-size: 14px; font-weight: 600; }
        .btn:hover { background-color: #1f2937; }
        .footer { padding: 20px 40px; background: #f9fafb; border-top: 1px solid #e5e7eb; font-size: 12px; color: #9ca3af; }
        .footer a { color: #6b7280; text-decoration: underline; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <h1>Invoicly</h1>
        </div>
        <div class="body">
            <p>Hi {{ $recipientName }},</p>
            <p>
                Your recurring automation <strong>{{ $scheduleName }}</strong> has automatically generated a new invoice for you.
            </p>

            <div class="invoice-card">
                <table>
                    <tr>
                        <td>Invoice number</td>
                        <td><strong>{{ $invoiceNumber }}</strong></td>
                    </tr>
                    <tr>
                        <td>Client</td>
                        <td>{{ $clientName }}</td>
                    </tr>
                    <tr>
                        <td>Amount</td>
                        <td><strong>{{ $invoiceCurrency }} {{ $invoiceAmount }}</strong></td>
                    </tr>
                    <tr>
                        <td>Issue date</td>
                        <td>{{ $issueDate }}</td>
                    </tr>
                    <tr>
                        <td>Frequency</td>
                        <td>{{ $frequency }}</td>
                    </tr>
                    <tr>
                        <td>Next generation</td>
                        <td>{{ $nextRunAt }}</td>
                    </tr>
                </table>
            </div>

            <p>You can view and manage your invoices from the link below.</p>

            <a href="{{ $invoicesUrl }}" class="btn">View invoices</a>
        </div>
        <div class="footer">
            <p>
                This invoice was generated automatically.
                You can <a href="{{ $automationUrl }}">manage your automations</a> at any time.
            </p>
        </div>
    </div>
</body>
</html>
