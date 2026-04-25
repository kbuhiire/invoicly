# Invoicly PHP SDK

PHP SDK for the [Invoicly](https://github.com/invoicly/invoicly) API.  
Lets any PHP application generate invoices and download PDFs on behalf of an Invoicly account.

## Requirements

- PHP 8.1+
- An Invoicly account with an API token (Settings → API Tokens)

## Installation

```bash
composer require invoicly/php-sdk
```

## Quick start

```php
use Invoicly\InvoiclyClient;
use Invoicly\Exceptions\InvoiclyException;

// 1. Instantiate with your Invoicly base URL and personal API token
$sdk = new InvoiclyClient('https://your-invoicly.app', 'inv_tok_xxxxxxxxxxxxxxxx');

// 2. List your invoice recipients
$page = $sdk->clients()->list();
foreach ($page['data'] as $client) {
    echo $client['name'] . PHP_EOL;
}

// 3. Create an invoice
$invoice = $sdk->invoices()->create([
    'client_id'  => 42,
    'issue_date' => '2026-04-06',
    'due_date'   => '2026-04-20',
    'status'     => 'awaiting_payment',
    'currency'   => 'USD',
    'line_items' => [
        ['description' => 'Web design', 'quantity' => 1,   'unit_price' => 1500.00],
        ['description' => 'Hosting',    'quantity' => 12,  'unit_price' => 15.00],
    ],
]);

$invoiceId = $invoice['data']['id'];
echo 'Created invoice #' . $invoice['data']['number'] . PHP_EOL;

// 4. Download the PDF
$sdk->invoices()->savePdf($invoiceId, "/tmp/invoice-{$invoiceId}.pdf");

// Or get raw bytes to stream it to a browser:
$pdf = $sdk->invoices()->downloadPdf($invoiceId);
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="invoice.pdf"');
echo $pdf;
```

## Error handling

```php
use Invoicly\Exceptions\InvoiclyException;

try {
    $invoice = $sdk->invoices()->create([...]);
} catch (InvoiclyException $e) {
    if ($e->isValidationError()) {
        // $e->getErrors() returns field => [messages] array
        print_r($e->getErrors());
    } elseif ($e->isRateLimited()) {
        // Wait and retry
    } else {
        throw $e;
    }
}
```

## API reference

### `$sdk->invoices()`

| Method | Description |
|--------|-------------|
| `list(array $filters = [])` | Paginated invoice list. Filters: `status`, `client_id`, `date_from`, `date_to`, `per_page` |
| `get(int $id)` | Fetch a single invoice with line items |
| `create(array $data)` | Create a new invoice |
| `update(int $id, array $data)` | Update an invoice |
| `delete(int $id)` | Delete an invoice |
| `downloadPdf(int $id)` | Return raw PDF bytes |
| `savePdf(int $id, string $path)` | Download PDF and save to disk |

### `$sdk->clients()`

| Method | Description |
|--------|-------------|
| `list(array $filters = [])` | Paginated client list. Filters: `type` (`external`\|`invoicly`), `search`, `per_page` |
| `get(int $id)` | Fetch a single client |
| `create(array $data)` | Create a new invoice recipient |

## Token abilities

Generate tokens from **Settings → API Tokens** in your Invoicly dashboard and grant only the abilities needed:

| Ability | Description |
|---------|-------------|
| `invoices:read` | List/view invoices, download PDFs |
| `invoices:write` | Create, update, delete invoices |
| `clients:read` | List/view invoice recipients |
| `clients:write` | Create invoice recipients |
