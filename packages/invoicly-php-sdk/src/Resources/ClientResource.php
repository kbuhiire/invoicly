<?php

namespace Invoicly\Resources;

use Invoicly\Exceptions\InvoiclyException;
use Invoicly\HttpClient;

class ClientResource
{
    public function __construct(private readonly HttpClient $http) {}

    /**
     * List invoice recipients for the authenticated Invoicly user.
     *
     * @param  array<string, mixed>  $filters  Optional: type (external|invoicly), search, per_page
     * @return array<string, mixed>
     * @throws InvoiclyException
     */
    public function list(array $filters = []): array
    {
        return $this->http->get('clients', $filters);
    }

    /**
     * Get a single client (invoice recipient).
     *
     * @return array<string, mixed>
     * @throws InvoiclyException
     */
    public function get(int $id): array
    {
        return $this->http->get("clients/{$id}");
    }

    /**
     * Create a new invoice recipient.
     *
     * Required fields:
     *   - type         (string)  — 'external' or 'invoicly'
     *   - is_business  (bool)
     *   - country      (string)  — ISO-3166 alpha-2 code
     *   - street       (string)
     *   - city         (string)
     *   - postal_code  (string)
     *   - first_name + last_name  (required when is_business = false)
     *   - business_name           (required when is_business = true)
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     * @throws InvoiclyException
     */
    public function create(array $data): array
    {
        return $this->http->post('clients', $data);
    }
}
