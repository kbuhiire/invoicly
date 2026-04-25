<?php

namespace Invoicly;

use Invoicly\Resources\ClientResource;
use Invoicly\Resources\InvoiceResource;

/**
 * Main entry point for the Invoicly PHP SDK.
 *
 * Usage:
 *
 *   $sdk = new \Invoicly\InvoiclyClient('https://your-invoicly.app', 'your-api-token');
 *
 *   // List your invoice recipients (clients)
 *   $clients = $sdk->clients()->list();
 *
 *   // Create an invoice for a client
 *   $invoice = $sdk->invoices()->create([
 *       'client_id'  => 42,
 *       'issue_date' => '2026-04-06',
 *       'due_date'   => '2026-04-20',
 *       'status'     => 'awaiting_payment',
 *       'currency'   => 'USD',
 *       'line_items' => [
 *           ['description' => 'Web design', 'quantity' => 1, 'unit_price' => 1500.00],
 *       ],
 *   ]);
 *
 *   // Download the PDF
 *   $sdk->invoices()->savePdf($invoice['data']['id'], '/tmp/invoice.pdf');
 */
class InvoiclyClient
{
    private HttpClient $http;
    private InvoiceResource $invoices;
    private ClientResource $clients;

    /**
     * @param  string  $baseUrl    The base URL of your Invoicly installation (e.g. https://invoicly.app)
     * @param  string  $apiToken   Personal API token generated from Settings → API Tokens
     * @param  array<string, mixed>  $options  Extra Guzzle options (timeout, proxy, etc.)
     */
    public function __construct(string $baseUrl, string $apiToken, array $options = [])
    {
        $this->http = new HttpClient($baseUrl, $apiToken, $options);
        $this->invoices = new InvoiceResource($this->http);
        $this->clients = new ClientResource($this->http);
    }

    public function invoices(): InvoiceResource
    {
        return $this->invoices;
    }

    public function clients(): ClientResource
    {
        return $this->clients;
    }
}
