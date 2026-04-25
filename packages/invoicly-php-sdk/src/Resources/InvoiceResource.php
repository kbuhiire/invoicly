<?php

namespace Invoicly\Resources;

use Invoicly\Exceptions\InvoiclyException;
use Invoicly\HttpClient;

class InvoiceResource
{
    public function __construct(private readonly HttpClient $http) {}

    /**
     * List invoices for the authenticated Invoicly user.
     *
     * @param  array<string, mixed>  $filters  Optional: status, client_id, date_from, date_to, per_page
     * @return array<string, mixed>
     * @throws InvoiclyException
     */
    public function list(array $filters = []): array
    {
        return $this->http->get('invoices', $filters);
    }

    /**
     * Get a single invoice.
     *
     * @return array<string, mixed>
     * @throws InvoiclyException
     */
    public function get(int $id): array
    {
        return $this->http->get("invoices/{$id}");
    }

    /**
     * Create an invoice for an existing client.
     *
     * Required fields:
     *   - client_id   (int)     — must belong to the authenticated user
     *   - issue_date  (string)  — Y-m-d
     *   - status      (string)  — awaiting_payment | paid
     *   - currency    (string)  — ISO-4217 code (e.g. USD)
     *   - line_items  (array)   — [{description, quantity, unit_price}]
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     * @throws InvoiclyException
     */
    public function create(array $data): array
    {
        return $this->http->post('invoices', $data);
    }

    /**
     * Update an invoice.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     * @throws InvoiclyException
     */
    public function update(int $id, array $data): array
    {
        return $this->http->put("invoices/{$id}", $data);
    }

    /**
     * Delete an invoice.
     *
     * @throws InvoiclyException
     */
    public function delete(int $id): void
    {
        $this->http->delete("invoices/{$id}");
    }

    /**
     * Download the PDF for an invoice and return the raw binary content.
     *
     * @throws InvoiclyException
     */
    public function downloadPdf(int $id): string
    {
        return $this->http->getRaw("invoices/{$id}/pdf");
    }

    /**
     * Download the PDF and save it to a local path.
     *
     * @throws InvoiclyException
     */
    public function savePdf(int $id, string $destinationPath): void
    {
        $pdf = $this->downloadPdf($id);
        file_put_contents($destinationPath, $pdf);
    }
}
