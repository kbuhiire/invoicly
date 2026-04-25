<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted } from 'vue';

// ── Language tabs ────────────────────────────────────────────────────────────
const langs = [
    { id: 'curl',   label: 'cURL'    },
    { id: 'php',    label: 'PHP'     },
    { id: 'node',   label: 'Node.js' },
    { id: 'python', label: 'Python'  },
    { id: 'ruby',   label: 'Ruby'    },
];
const activeLang = ref('curl');

// ── Right-panel tabs ─────────────────────────────────────────────────────────
const rightTab = ref('code'); // 'code' | 'try'

// ── Sidebar active endpoint ──────────────────────────────────────────────────
const activeEndpoint = ref('overview');

// ── Copy-to-clipboard ────────────────────────────────────────────────────────
const copied = ref(false);
async function copyCode(text) {
    await navigator.clipboard.writeText(text);
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
}

// ── Try It state ─────────────────────────────────────────────────────────────
const tryToken    = ref('');
const tryParams   = ref({});
const tryResponse = ref(null); // { status, statusText, body, ms }
const tryLoading  = ref(false);

onMounted(() => {
    tryToken.value = localStorage.getItem('inv_dev_token') ?? '';
});

watch(tryToken, (val) => {
    localStorage.setItem('inv_dev_token', val);
});

// Reset try params whenever endpoint changes
watch(activeEndpoint, () => {
    tryParams.value  = {};
    tryResponse.value = null;
    rightTab.value   = 'code';
});

async function sendRequest() {
    if (!tryToken.value.trim()) {
        alert('Please enter your API token first.');
        return;
    }

    const ep = currentEndpoint.value;
    if (!ep.path) return;

    tryLoading.value  = true;
    tryResponse.value = null;

    try {
        // Build URL — replace path params first
        let path = ep.path;
        for (const param of ep.params.filter(p => p.in === 'path')) {
            const val = tryParams.value[param.name] ?? '';
            path = path.replace(`{${param.name}}`, encodeURIComponent(val));
        }

        const url = new URL('/api/v1' + path.replace('/api/v1', ''), window.location.origin);

        // Query params
        for (const param of ep.params.filter(p => p.in === 'query')) {
            const val = tryParams.value[param.name];
            if (val !== undefined && val !== '') {
                url.searchParams.append(param.name, val);
            }
        }

        // Build body for POST / PUT
        let body     = undefined;
        let bodyJson = undefined;
        if (['POST', 'PUT'].includes(ep.method)) {
            const bodyObj = {};
            for (const param of ep.params.filter(p => p.in === 'body')) {
                const val = tryParams.value[param.name];
                if (val !== undefined && val !== '') {
                    // Try parsing JSON-like values
                    if (val === 'true')  { bodyObj[param.name] = true; continue; }
                    if (val === 'false') { bodyObj[param.name] = false; continue; }
                    const num = Number(val);
                    if (!isNaN(num) && val.trim() !== '') { bodyObj[param.name] = num; continue; }
                    bodyObj[param.name] = val;
                }
            }
            // line_items special: expect JSON array string
            if (tryParams.value['line_items_raw']) {
                try { bodyObj.line_items = JSON.parse(tryParams.value['line_items_raw']); } catch {}
            }
            body     = JSON.stringify(bodyObj);
            bodyJson = bodyObj;
        }

        const start = performance.now();

        const headers = {
            'Authorization': `Bearer ${tryToken.value.trim()}`,
            'Accept': 'application/json',
        };
        if (body) headers['Content-Type'] = 'application/json';

        const res = await fetch(url.toString(), {
            method:  ep.method ?? 'GET',
            headers,
            body,
        });

        const ms       = Math.round(performance.now() - start);
        const bodyText = await res.text();

        let formatted = bodyText;
        try { formatted = JSON.stringify(JSON.parse(bodyText), null, 2); } catch {}

        tryResponse.value = {
            status:     res.status,
            statusText: res.statusText,
            body:       formatted,
            ms,
        };
    } catch (err) {
        tryResponse.value = {
            status:     0,
            statusText: 'Network Error',
            body:       err.message,
            ms:         0,
        };
    } finally {
        tryLoading.value = false;
    }
}

// ── API endpoint data ────────────────────────────────────────────────────────
const apiEndpoints = [
    // ── Getting Started ───────────────────────────────────────────────────────
    {
        id: 'overview',
        group: 'start',
        groupLabel: 'Getting Started',
        title: 'Overview',
        method: null,
        path: null,
        description: 'The Invoicly API v1 enables seamless integration with your systems and Invoicly services. With it you can manage invoices programmatically, create and retrieve client records, download invoice PDFs, and automate your entire billing workflow.',
        features: [
            'Create, read, update and delete invoices',
            'Manage client records',
            'Download invoice PDFs',
            'Filter and paginate all resources',
            'Token-based authentication with granular abilities',
        ],
        note: null,
        ability: null,
        params: [],
        response: null,
        code: { curl: null, php: null, node: null, python: null, ruby: null },
    },
    {
        id: 'auth',
        group: 'start',
        groupLabel: 'Getting Started',
        title: 'Authentication',
        method: null,
        path: null,
        description: 'The Invoicly API uses token-based authentication via Laravel Sanctum. Generate an API token from Settings → API Tokens inside your dashboard, then pass it as a Bearer token in the Authorization header of every request.',
        features: null,
        note: 'Each token supports granular abilities: invoices:read, invoices:write, clients:read, clients:write. Ensure your token is issued with the correct abilities for the endpoints you intend to call.',
        ability: null,
        params: [],
        response: null,
        code: {
            curl: `curl --request GET \\
  --url https://app.invoicly.io/api/v1/invoices \\
  --header 'Authorization: Bearer YOUR_API_TOKEN' \\
  --header 'Accept: application/json'`,
            php: `<?php

use GuzzleHttp\\Client;

$http = new Client();

$response = $http->request('GET', 'https://app.invoicly.io/api/v1/invoices', [
    'headers' => [
        'Authorization' => 'Bearer YOUR_API_TOKEN',
        'Accept'        => 'application/json',
    ],
]);

$data = json_decode($response->getBody()->getContents(), true);`,
            node: `const axios = require('axios');

const response = await axios.get(
  'https://app.invoicly.io/api/v1/invoices',
  {
    headers: {
      Authorization: 'Bearer YOUR_API_TOKEN',
      Accept: 'application/json',
    },
  }
);

console.log(response.data);`,
            python: `import requests

response = requests.get(
    'https://app.invoicly.io/api/v1/invoices',
    headers={
        'Authorization': 'Bearer YOUR_API_TOKEN',
        'Accept': 'application/json',
    },
)

data = response.json()
print(data)`,
            ruby: `require 'net/http'
require 'json'

uri = URI('https://app.invoicly.io/api/v1/invoices')
req = Net::HTTP::Get.new(uri)
req['Authorization'] = 'Bearer YOUR_API_TOKEN'
req['Accept']        = 'application/json'

res  = Net::HTTP.start(uri.host, uri.port, use_ssl: true) { |h| h.request(req) }
data = JSON.parse(res.body)
puts data`,
        },
    },

    // ── Invoices ──────────────────────────────────────────────────────────────
    {
        id: 'invoices-list',
        group: 'invoices',
        groupLabel: 'Invoices',
        title: 'List Invoices',
        method: 'GET',
        path: '/api/v1/invoices',
        description: 'Returns a paginated list of all invoices belonging to the authenticated user. Supports filtering by status, client, and date range. Results are ordered by issue date descending.',
        features: null,
        note: null,
        ability: 'invoices:read',
        params: [
            { name: 'status',    in: 'query', type: 'string',  required: false, description: 'Filter by status: draft, awaiting_payment, paid, overdue, cancelled' },
            { name: 'client_id', in: 'query', type: 'integer', required: false, description: 'Filter invoices for a specific client by their ID' },
            { name: 'date_from', in: 'query', type: 'date',    required: false, description: 'Return invoices issued on or after this date (YYYY-MM-DD)' },
            { name: 'date_to',   in: 'query', type: 'date',    required: false, description: 'Return invoices issued on or before this date (YYYY-MM-DD)' },
            { name: 'per_page',  in: 'query', type: 'integer', required: false, description: 'Number of results per page (default: 15)' },
        ],
        response: `{
  "data": [
    {
      "id": 1,
      "number": "INV-2024-001",
      "status": "paid",
      "issue_date": "2024-01-15",
      "due_date": "2024-02-15",
      "currency": "USD",
      "amount": 4400.00,
      "vat_amount": null,
      "is_template": false,
      "pdf_url": "https://app.invoicly.io/api/v1/invoices/1/pdf",
      "client": { "id": 5, "name": "Acme Corporation" },
      "line_items": [
        {
          "id": 1,
          "description": "Web Design — 20h",
          "quantity": 20,
          "unit_price": 100.00,
          "line_total": 2000.00
        }
      ],
      "created_at": "2024-01-15T10:00:00+00:00"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 42,
    "last_page": 3
  }
}`,
        code: {
            curl: `curl --request GET \\
  --url 'https://app.invoicly.io/api/v1/invoices?status=paid&per_page=10' \\
  --header 'Authorization: Bearer YOUR_API_TOKEN' \\
  --header 'Accept: application/json'`,
            php: `<?php

use GuzzleHttp\\Client;

$http = new Client();

$response = $http->request('GET', 'https://app.invoicly.io/api/v1/invoices', [
    'headers' => [
        'Authorization' => 'Bearer YOUR_API_TOKEN',
        'Accept'        => 'application/json',
    ],
    'query' => [
        'status'   => 'paid',
        'per_page' => 10,
    ],
]);

$data = json_decode($response->getBody()->getContents(), true);
// $data['data'] is the invoices array`,
            node: `const axios = require('axios');

const { data } = await axios.get(
  'https://app.invoicly.io/api/v1/invoices',
  {
    params: { status: 'paid', per_page: 10 },
    headers: { Authorization: 'Bearer YOUR_API_TOKEN' },
  }
);

// data.data is the invoices array
console.log(data.data);`,
            python: `import requests

response = requests.get(
    'https://app.invoicly.io/api/v1/invoices',
    headers={'Authorization': 'Bearer YOUR_API_TOKEN'},
    params={'status': 'paid', 'per_page': 10},
)

data = response.json()
print(data['data'])  # list of invoices`,
            ruby: `require 'net/http'
require 'json'

uri = URI('https://app.invoicly.io/api/v1/invoices')
uri.query = URI.encode_www_form(status: 'paid', per_page: 10)

req = Net::HTTP::Get.new(uri)
req['Authorization'] = 'Bearer YOUR_API_TOKEN'
req['Accept']        = 'application/json'

res  = Net::HTTP.start(uri.host, uri.port, use_ssl: true) { |h| h.request(req) }
data = JSON.parse(res.body)
puts data['data']  # array of invoices`,
        },
    },
    {
        id: 'invoices-get',
        group: 'invoices',
        groupLabel: 'Invoices',
        title: 'Get Invoice',
        method: 'GET',
        path: '/api/v1/invoices/{id}',
        description: 'Retrieves a single invoice by ID. Includes the full client object and all line items. Returns 403 if the invoice does not belong to the authenticated user.',
        features: null,
        note: null,
        ability: 'invoices:read',
        params: [
            { name: 'id', in: 'path', type: 'integer', required: true, description: 'The ID of the invoice to retrieve' },
        ],
        response: `{
  "data": {
    "id": 1,
    "number": "INV-2024-001",
    "status": "awaiting_payment",
    "issue_date": "2024-01-15",
    "due_date": "2024-02-15",
    "currency": "USD",
    "amount": 4400.00,
    "vat_amount": 880.00,
    "payer_memo": "Please reference INV-2024-001",
    "payment_details": "Bank: Wells Fargo\\nAccount: 1234567890",
    "invoice_type": "Service",
    "is_template": false,
    "has_attachment": false,
    "pdf_url": "https://app.invoicly.io/api/v1/invoices/1/pdf",
    "client": {
      "id": 5,
      "name": "Acme Corporation",
      "is_business": true,
      "country": "DE",
      "email": "billing@acme.com"
    },
    "line_items": [
      {
        "id": 1,
        "description": "Web Design — 20h",
        "quantity": 20,
        "unit_price": 100.00,
        "line_total": 2000.00,
        "sort_order": 0
      }
    ],
    "created_at": "2024-01-15T10:00:00+00:00",
    "updated_at": "2024-01-20T14:32:00+00:00"
  }
}`,
        code: {
            curl: `curl --request GET \\
  --url https://app.invoicly.io/api/v1/invoices/1 \\
  --header 'Authorization: Bearer YOUR_API_TOKEN' \\
  --header 'Accept: application/json'`,
            php: `<?php

use GuzzleHttp\\Client;

$http = new Client();
$invoiceId = 1;

$response = $http->request('GET',
    "https://app.invoicly.io/api/v1/invoices/{$invoiceId}",
    [
        'headers' => [
            'Authorization' => 'Bearer YOUR_API_TOKEN',
            'Accept'        => 'application/json',
        ],
    ]
);

$invoice = json_decode($response->getBody()->getContents(), true)['data'];`,
            node: `const axios = require('axios');

const invoiceId = 1;

const { data } = await axios.get(
  \`https://app.invoicly.io/api/v1/invoices/\${invoiceId}\`,
  { headers: { Authorization: 'Bearer YOUR_API_TOKEN' } }
);

console.log(data.data);`,
            python: `import requests

invoice_id = 1

response = requests.get(
    f'https://app.invoicly.io/api/v1/invoices/{invoice_id}',
    headers={'Authorization': 'Bearer YOUR_API_TOKEN'},
)

invoice = response.json()['data']
print(invoice)`,
            ruby: `require 'net/http'
require 'json'

invoice_id = 1
uri = URI("https://app.invoicly.io/api/v1/invoices/#{invoice_id}")

req = Net::HTTP::Get.new(uri)
req['Authorization'] = 'Bearer YOUR_API_TOKEN'
req['Accept']        = 'application/json'

res     = Net::HTTP.start(uri.host, uri.port, use_ssl: true) { |h| h.request(req) }
invoice = JSON.parse(res.body)['data']
puts invoice`,
        },
    },
    {
        id: 'invoices-create',
        group: 'invoices',
        groupLabel: 'Invoices',
        title: 'Create Invoice',
        method: 'POST',
        path: '/api/v1/invoices',
        description: 'Creates a new invoice for the authenticated user. Requires at least one line item. The invoice number is auto-generated based on the client type and issue date.',
        features: null,
        note: null,
        ability: 'invoices:write',
        params: [
            { name: 'client_id',       in: 'body', type: 'integer', required: true,  description: 'ID of the client to invoice' },
            { name: 'issue_date',      in: 'body', type: 'date',    required: true,  description: 'Invoice issue date (YYYY-MM-DD)' },
            { name: 'status',          in: 'body', type: 'string',  required: true,  description: 'draft, awaiting_payment, paid, overdue, cancelled' },
            { name: 'currency',        in: 'body', type: 'string',  required: true,  description: 'ISO 4217 currency code (e.g. USD, EUR)' },
            { name: 'line_items',      in: 'body', type: 'array',   required: true,  description: 'Array of line items. Each requires description, quantity, unit_price' },
            { name: 'due_date',        in: 'body', type: 'date',    required: false, description: 'Payment due date (YYYY-MM-DD)' },
            { name: 'vat_amount',      in: 'body', type: 'number',  required: false, description: 'VAT amount added to the invoice total' },
            { name: 'payment_details', in: 'body', type: 'string',  required: false, description: 'Bank or payment instructions shown on the invoice (max 250 chars)' },
            { name: 'payer_memo',      in: 'body', type: 'string',  required: false, description: 'Reference note for the payer (max 300 chars)' },
            { name: 'invoice_type',    in: 'body', type: 'string',  required: false, description: 'Invoice category label, e.g. "Service" (default)' },
            { name: 'is_template',     in: 'body', type: 'boolean', required: false, description: 'Save as a reusable template (default: false)' },
        ],
        response: `{
  "data": {
    "id": 12,
    "number": "INV-2024-012",
    "status": "awaiting_payment",
    "issue_date": "2024-03-01",
    "due_date": "2024-03-31",
    "currency": "USD",
    "amount": 3200.00,
    "is_template": false,
    "pdf_url": "https://app.invoicly.io/api/v1/invoices/12/pdf",
    "client": { "id": 5, "name": "Acme Corporation" },
    "line_items": [
      {
        "id": 21,
        "description": "Consulting — 20h",
        "quantity": 20,
        "unit_price": 160.00,
        "line_total": 3200.00,
        "sort_order": 0
      }
    ],
    "created_at": "2024-03-01T09:15:00+00:00"
  }
}`,
        code: {
            curl: `curl --request POST \\
  --url https://app.invoicly.io/api/v1/invoices \\
  --header 'Authorization: Bearer YOUR_API_TOKEN' \\
  --header 'Accept: application/json' \\
  --header 'Content-Type: application/json' \\
  --data '{
    "client_id": 5,
    "issue_date": "2024-03-01",
    "due_date": "2024-03-31",
    "status": "awaiting_payment",
    "currency": "USD",
    "line_items": [
      {
        "description": "Consulting — 20h",
        "quantity": 20,
        "unit_price": 160.00
      }
    ]
  }'`,
            php: `<?php

use GuzzleHttp\\Client;

$http = new Client();

$response = $http->request('POST', 'https://app.invoicly.io/api/v1/invoices', [
    'headers' => [
        'Authorization' => 'Bearer YOUR_API_TOKEN',
        'Accept'        => 'application/json',
    ],
    'json' => [
        'client_id'  => 5,
        'issue_date' => '2024-03-01',
        'due_date'   => '2024-03-31',
        'status'     => 'awaiting_payment',
        'currency'   => 'USD',
        'line_items' => [
            [
                'description' => 'Consulting — 20h',
                'quantity'    => 20,
                'unit_price'  => 160.00,
            ],
        ],
    ],
]);

$invoice = json_decode($response->getBody()->getContents(), true)['data'];`,
            node: `const axios = require('axios');

const { data } = await axios.post(
  'https://app.invoicly.io/api/v1/invoices',
  {
    client_id:  5,
    issue_date: '2024-03-01',
    due_date:   '2024-03-31',
    status:     'awaiting_payment',
    currency:   'USD',
    line_items: [
      { description: 'Consulting — 20h', quantity: 20, unit_price: 160 },
    ],
  },
  { headers: { Authorization: 'Bearer YOUR_API_TOKEN' } }
);

console.log(data.data);`,
            python: `import requests

response = requests.post(
    'https://app.invoicly.io/api/v1/invoices',
    headers={'Authorization': 'Bearer YOUR_API_TOKEN'},
    json={
        'client_id':  5,
        'issue_date': '2024-03-01',
        'due_date':   '2024-03-31',
        'status':     'awaiting_payment',
        'currency':   'USD',
        'line_items': [
            {
                'description': 'Consulting — 20h',
                'quantity':    20,
                'unit_price':  160.00,
            },
        ],
    },
)

invoice = response.json()['data']
print(invoice)`,
            ruby: `require 'net/http'
require 'json'

uri = URI('https://app.invoicly.io/api/v1/invoices')
req = Net::HTTP::Post.new(uri)
req['Authorization'] = 'Bearer YOUR_API_TOKEN'
req['Accept']        = 'application/json'
req['Content-Type']  = 'application/json'
req.body = JSON.generate({
  client_id:  5,
  issue_date: '2024-03-01',
  due_date:   '2024-03-31',
  status:     'awaiting_payment',
  currency:   'USD',
  line_items: [
    { description: 'Consulting — 20h', quantity: 20, unit_price: 160.0 },
  ],
})

res     = Net::HTTP.start(uri.host, uri.port, use_ssl: true) { |h| h.request(req) }
invoice = JSON.parse(res.body)['data']
puts invoice`,
        },
    },
    {
        id: 'invoices-update',
        group: 'invoices',
        groupLabel: 'Invoices',
        title: 'Update Invoice',
        method: 'PUT',
        path: '/api/v1/invoices/{id}',
        description: 'Updates an existing invoice. All line items are replaced on each update — include the full set of desired line items in every request. The client segment (external vs invoicly) cannot be changed.',
        features: null,
        note: null,
        ability: 'invoices:write',
        params: [
            { name: 'id',              in: 'path', type: 'integer', required: true,  description: 'ID of the invoice to update' },
            { name: 'client_id',       in: 'body', type: 'integer', required: true,  description: 'Client ID (must match original client segment)' },
            { name: 'issue_date',      in: 'body', type: 'date',    required: true,  description: 'Invoice issue date (YYYY-MM-DD)' },
            { name: 'status',          in: 'body', type: 'string',  required: true,  description: 'draft, awaiting_payment, paid, overdue, cancelled' },
            { name: 'currency',        in: 'body', type: 'string',  required: true,  description: 'ISO 4217 currency code' },
            { name: 'line_items',      in: 'body', type: 'array',   required: true,  description: 'Full replacement set of line items' },
            { name: 'payment_details', in: 'body', type: 'string',  required: false, description: 'Updated payment instructions (max 500 chars)' },
            { name: 'is_template',     in: 'body', type: 'boolean', required: false, description: 'Mark or unmark as template' },
        ],
        response: `{
  "data": {
    "id": 1,
    "number": "INV-2024-001",
    "status": "paid",
    "issue_date": "2024-01-15",
    "due_date": "2024-02-15",
    "currency": "USD",
    "amount": 4400.00,
    "updated_at": "2024-02-10T08:00:00+00:00"
  }
}`,
        code: {
            curl: `curl --request PUT \\
  --url https://app.invoicly.io/api/v1/invoices/1 \\
  --header 'Authorization: Bearer YOUR_API_TOKEN' \\
  --header 'Accept: application/json' \\
  --header 'Content-Type: application/json' \\
  --data '{
    "client_id": 5,
    "issue_date": "2024-01-15",
    "status": "paid",
    "currency": "USD",
    "line_items": [
      {
        "description": "Web Design — 20h",
        "quantity": 20,
        "unit_price": 100.00
      }
    ]
  }'`,
            php: `<?php

use GuzzleHttp\\Client;

$http = new Client();

$response = $http->request('PUT',
    'https://app.invoicly.io/api/v1/invoices/1',
    [
        'headers' => [
            'Authorization' => 'Bearer YOUR_API_TOKEN',
            'Accept'        => 'application/json',
        ],
        'json' => [
            'client_id'  => 5,
            'issue_date' => '2024-01-15',
            'status'     => 'paid',
            'currency'   => 'USD',
            'line_items' => [
                [
                    'description' => 'Web Design — 20h',
                    'quantity'    => 20,
                    'unit_price'  => 100.00,
                ],
            ],
        ],
    ]
);

$invoice = json_decode($response->getBody()->getContents(), true)['data'];`,
            node: `const axios = require('axios');

const { data } = await axios.put(
  'https://app.invoicly.io/api/v1/invoices/1',
  {
    client_id:  5,
    issue_date: '2024-01-15',
    status:     'paid',
    currency:   'USD',
    line_items: [
      { description: 'Web Design — 20h', quantity: 20, unit_price: 100 },
    ],
  },
  { headers: { Authorization: 'Bearer YOUR_API_TOKEN' } }
);

console.log(data.data);`,
            python: `import requests

response = requests.put(
    'https://app.invoicly.io/api/v1/invoices/1',
    headers={'Authorization': 'Bearer YOUR_API_TOKEN'},
    json={
        'client_id':  5,
        'issue_date': '2024-01-15',
        'status':     'paid',
        'currency':   'USD',
        'line_items': [
            {'description': 'Web Design — 20h', 'quantity': 20, 'unit_price': 100.0},
        ],
    },
)

invoice = response.json()['data']
print(invoice)`,
            ruby: `require 'net/http'
require 'json'

uri = URI('https://app.invoicly.io/api/v1/invoices/1')
req = Net::HTTP::Put.new(uri)
req['Authorization'] = 'Bearer YOUR_API_TOKEN'
req['Accept']        = 'application/json'
req['Content-Type']  = 'application/json'
req.body = JSON.generate({
  client_id:  5,
  issue_date: '2024-01-15',
  status:     'paid',
  currency:   'USD',
  line_items: [
    { description: 'Web Design — 20h', quantity: 20, unit_price: 100.0 },
  ],
})

res     = Net::HTTP.start(uri.host, uri.port, use_ssl: true) { |h| h.request(req) }
invoice = JSON.parse(res.body)['data']
puts invoice`,
        },
    },
    {
        id: 'invoices-delete',
        group: 'invoices',
        groupLabel: 'Invoices',
        title: 'Delete Invoice',
        method: 'DELETE',
        path: '/api/v1/invoices/{id}',
        description: 'Permanently deletes an invoice and all its associated line items. This action is irreversible.',
        features: null,
        note: null,
        ability: 'invoices:write',
        params: [
            { name: 'id', in: 'path', type: 'integer', required: true, description: 'ID of the invoice to delete' },
        ],
        response: `{
  "message": "Invoice deleted."
}`,
        code: {
            curl: `curl --request DELETE \\
  --url https://app.invoicly.io/api/v1/invoices/1 \\
  --header 'Authorization: Bearer YOUR_API_TOKEN' \\
  --header 'Accept: application/json'`,
            php: `<?php

use GuzzleHttp\\Client;

$http = new Client();

$response = $http->request('DELETE',
    'https://app.invoicly.io/api/v1/invoices/1',
    [
        'headers' => [
            'Authorization' => 'Bearer YOUR_API_TOKEN',
            'Accept'        => 'application/json',
        ],
    ]
);

$result = json_decode($response->getBody()->getContents(), true);
// $result['message'] === 'Invoice deleted.'`,
            node: `const axios = require('axios');

const { data } = await axios.delete(
  'https://app.invoicly.io/api/v1/invoices/1',
  { headers: { Authorization: 'Bearer YOUR_API_TOKEN' } }
);

console.log(data.message); // 'Invoice deleted.'`,
            python: `import requests

response = requests.delete(
    'https://app.invoicly.io/api/v1/invoices/1',
    headers={'Authorization': 'Bearer YOUR_API_TOKEN'},
)

result = response.json()
print(result['message'])  # 'Invoice deleted.'`,
            ruby: `require 'net/http'
require 'json'

uri = URI('https://app.invoicly.io/api/v1/invoices/1')
req = Net::HTTP::Delete.new(uri)
req['Authorization'] = 'Bearer YOUR_API_TOKEN'
req['Accept']        = 'application/json'

res    = Net::HTTP.start(uri.host, uri.port, use_ssl: true) { |h| h.request(req) }
result = JSON.parse(res.body)
puts result['message']  # 'Invoice deleted.'`,
        },
    },
    {
        id: 'invoices-pdf',
        group: 'invoices',
        groupLabel: 'Invoices',
        title: 'Download PDF',
        method: 'GET',
        path: '/api/v1/invoices/{id}/pdf',
        description: 'Streams a PDF rendering of the invoice. The response Content-Type is application/pdf. The filename is derived from the invoice number. Rate-limited separately from other endpoints.',
        features: null,
        note: null,
        ability: 'invoices:read',
        params: [
            { name: 'id', in: 'path', type: 'integer', required: true, description: 'ID of the invoice whose PDF to download' },
        ],
        response: `Binary PDF stream

Content-Type: application/pdf
Content-Disposition: attachment; filename="INV-2024-001.pdf"`,
        code: {
            curl: `curl --request GET \\
  --url https://app.invoicly.io/api/v1/invoices/1/pdf \\
  --header 'Authorization: Bearer YOUR_API_TOKEN' \\
  --output INV-2024-001.pdf`,
            php: `<?php

use GuzzleHttp\\Client;

$http = new Client();

$http->request('GET', 'https://app.invoicly.io/api/v1/invoices/1/pdf', [
    'headers' => [
        'Authorization' => 'Bearer YOUR_API_TOKEN',
    ],
    'sink' => '/path/to/INV-2024-001.pdf',
]);

// PDF saved to the specified path`,
            node: `const axios  = require('axios');
const fs     = require('fs');
const stream = require('stream');
const { promisify } = require('util');

const pipeline = promisify(stream.pipeline);

const response = await axios.get(
  'https://app.invoicly.io/api/v1/invoices/1/pdf',
  {
    responseType: 'stream',
    headers: { Authorization: 'Bearer YOUR_API_TOKEN' },
  }
);

await pipeline(response.data, fs.createWriteStream('INV-2024-001.pdf'));
console.log('PDF saved.');`,
            python: `import requests

invoice_id = 1

response = requests.get(
    f'https://app.invoicly.io/api/v1/invoices/{invoice_id}/pdf',
    headers={'Authorization': 'Bearer YOUR_API_TOKEN'},
    stream=True,
)

with open('INV-2024-001.pdf', 'wb') as f:
    for chunk in response.iter_content(chunk_size=8192):
        f.write(chunk)

print('PDF saved.')`,
            ruby: `require 'net/http'

invoice_id = 1
uri = URI("https://app.invoicly.io/api/v1/invoices/#{invoice_id}/pdf")

req = Net::HTTP::Get.new(uri)
req['Authorization'] = 'Bearer YOUR_API_TOKEN'

Net::HTTP.start(uri.host, uri.port, use_ssl: true) do |http|
  http.request(req) do |res|
    File.open('INV-2024-001.pdf', 'wb') do |f|
      res.read_body { |chunk| f.write(chunk) }
    end
  end
end

puts 'PDF saved.'`,
        },
    },

    // ── Clients ───────────────────────────────────────────────────────────────
    {
        id: 'clients-list',
        group: 'clients',
        groupLabel: 'Clients',
        title: 'List Clients',
        method: 'GET',
        path: '/api/v1/clients',
        description: 'Returns a paginated list of all clients belonging to the authenticated user, ordered alphabetically by name. Supports filtering by type and full-name search.',
        features: null,
        note: null,
        ability: 'clients:read',
        params: [
            { name: 'type',     in: 'query', type: 'string',  required: false, description: 'Filter by client type: external or invoicly' },
            { name: 'search',   in: 'query', type: 'string',  required: false, description: 'Search by client name (partial match)' },
            { name: 'per_page', in: 'query', type: 'integer', required: false, description: 'Number of results per page (default: 25)' },
        ],
        response: `{
  "data": [
    {
      "id": 5,
      "name": "Acme Corporation",
      "type": "external",
      "is_business": true,
      "first_name": null,
      "last_name": null,
      "business_name": "Acme Corporation",
      "country": "US",
      "street": "123 Main St",
      "city": "New York",
      "postal_code": "10001",
      "email": "billing@acme.com",
      "vat_number": "US123456789",
      "created_at": "2024-01-10T08:00:00+00:00"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 25,
    "total": 8,
    "last_page": 1
  }
}`,
        code: {
            curl: `curl --request GET \\
  --url 'https://app.invoicly.io/api/v1/clients?type=external' \\
  --header 'Authorization: Bearer YOUR_API_TOKEN' \\
  --header 'Accept: application/json'`,
            php: `<?php

use GuzzleHttp\\Client;

$http = new Client();

$response = $http->request('GET', 'https://app.invoicly.io/api/v1/clients', [
    'headers' => [
        'Authorization' => 'Bearer YOUR_API_TOKEN',
        'Accept'        => 'application/json',
    ],
    'query' => ['type' => 'external'],
]);

$clients = json_decode($response->getBody()->getContents(), true);
// $clients['data'] is the clients array`,
            node: `const axios = require('axios');

const { data } = await axios.get(
  'https://app.invoicly.io/api/v1/clients',
  {
    params: { type: 'external' },
    headers: { Authorization: 'Bearer YOUR_API_TOKEN' },
  }
);

console.log(data.data);`,
            python: `import requests

response = requests.get(
    'https://app.invoicly.io/api/v1/clients',
    headers={'Authorization': 'Bearer YOUR_API_TOKEN'},
    params={'type': 'external'},
)

clients = response.json()['data']
print(clients)`,
            ruby: `require 'net/http'
require 'json'

uri = URI('https://app.invoicly.io/api/v1/clients')
uri.query = URI.encode_www_form(type: 'external')

req = Net::HTTP::Get.new(uri)
req['Authorization'] = 'Bearer YOUR_API_TOKEN'
req['Accept']        = 'application/json'

res     = Net::HTTP.start(uri.host, uri.port, use_ssl: true) { |h| h.request(req) }
clients = JSON.parse(res.body)['data']
puts clients`,
        },
    },
    {
        id: 'clients-get',
        group: 'clients',
        groupLabel: 'Clients',
        title: 'Get Client',
        method: 'GET',
        path: '/api/v1/clients/{id}',
        description: 'Retrieves a single client by ID. Returns 403 if the client does not belong to the authenticated user.',
        features: null,
        note: null,
        ability: 'clients:read',
        params: [
            { name: 'id', in: 'path', type: 'integer', required: true, description: 'The ID of the client to retrieve' },
        ],
        response: `{
  "data": {
    "id": 5,
    "name": "Acme Corporation",
    "type": "external",
    "is_business": true,
    "business_name": "Acme Corporation",
    "country": "US",
    "street": "123 Main St",
    "city": "New York",
    "postal_code": "10001",
    "email": "billing@acme.com",
    "vat_number": "US123456789",
    "created_at": "2024-01-10T08:00:00+00:00",
    "updated_at": "2024-01-10T08:00:00+00:00"
  }
}`,
        code: {
            curl: `curl --request GET \\
  --url https://app.invoicly.io/api/v1/clients/5 \\
  --header 'Authorization: Bearer YOUR_API_TOKEN' \\
  --header 'Accept: application/json'`,
            php: `<?php

use GuzzleHttp\\Client;

$http = new Client();
$clientId = 5;

$response = $http->request('GET',
    "https://app.invoicly.io/api/v1/clients/{$clientId}",
    [
        'headers' => [
            'Authorization' => 'Bearer YOUR_API_TOKEN',
            'Accept'        => 'application/json',
        ],
    ]
);

$client = json_decode($response->getBody()->getContents(), true)['data'];`,
            node: `const axios = require('axios');

const clientId = 5;

const { data } = await axios.get(
  \`https://app.invoicly.io/api/v1/clients/\${clientId}\`,
  { headers: { Authorization: 'Bearer YOUR_API_TOKEN' } }
);

console.log(data.data);`,
            python: `import requests

client_id = 5

response = requests.get(
    f'https://app.invoicly.io/api/v1/clients/{client_id}',
    headers={'Authorization': 'Bearer YOUR_API_TOKEN'},
)

client = response.json()['data']
print(client)`,
            ruby: `require 'net/http'
require 'json'

client_id = 5
uri = URI("https://app.invoicly.io/api/v1/clients/#{client_id}")

req = Net::HTTP::Get.new(uri)
req['Authorization'] = 'Bearer YOUR_API_TOKEN'
req['Accept']        = 'application/json'

res    = Net::HTTP.start(uri.host, uri.port, use_ssl: true) { |h| h.request(req) }
client = JSON.parse(res.body)['data']
puts client`,
        },
    },
    {
        id: 'clients-create',
        group: 'clients',
        groupLabel: 'Clients',
        title: 'Create Client',
        method: 'POST',
        path: '/api/v1/clients',
        description: 'Creates a new client record for the authenticated user. Supply either first_name + last_name (for individuals) or business_name (for businesses) depending on the is_business flag.',
        features: null,
        note: null,
        ability: 'clients:write',
        params: [
            { name: 'type',          in: 'body', type: 'string',  required: true,  description: 'Client type: external (your own clients) or invoicly (Invoicly users)' },
            { name: 'is_business',   in: 'body', type: 'boolean', required: true,  description: 'true for companies, false for individuals' },
            { name: 'business_name', in: 'body', type: 'string',  required: false, description: 'Required when is_business is true' },
            { name: 'first_name',    in: 'body', type: 'string',  required: false, description: 'Required when is_business is false' },
            { name: 'last_name',     in: 'body', type: 'string',  required: false, description: 'Required when is_business is false' },
            { name: 'country',       in: 'body', type: 'string',  required: true,  description: 'ISO 3166-1 alpha-2 country code (e.g. US, DE, GB)' },
            { name: 'street',        in: 'body', type: 'string',  required: true,  description: 'Street address' },
            { name: 'city',          in: 'body', type: 'string',  required: true,  description: 'City' },
            { name: 'postal_code',   in: 'body', type: 'string',  required: true,  description: 'Postal / ZIP code' },
            { name: 'email',         in: 'body', type: 'string',  required: false, description: 'Contact email address' },
            { name: 'vat_number',    in: 'body', type: 'string',  required: false, description: 'VAT registration number (businesses only)' },
        ],
        response: `{
  "data": {
    "id": 12,
    "name": "Acme Corporation",
    "type": "external",
    "is_business": true,
    "business_name": "Acme Corporation",
    "country": "US",
    "street": "123 Main St",
    "city": "New York",
    "postal_code": "10001",
    "email": "billing@acme.com",
    "vat_number": null,
    "created_at": "2024-03-15T12:00:00+00:00",
    "updated_at": "2024-03-15T12:00:00+00:00"
  }
}`,
        code: {
            curl: `curl --request POST \\
  --url https://app.invoicly.io/api/v1/clients \\
  --header 'Authorization: Bearer YOUR_API_TOKEN' \\
  --header 'Accept: application/json' \\
  --header 'Content-Type: application/json' \\
  --data '{
    "type": "external",
    "is_business": true,
    "business_name": "Acme Corporation",
    "country": "US",
    "street": "123 Main St",
    "city": "New York",
    "postal_code": "10001",
    "email": "billing@acme.com"
  }'`,
            php: `<?php

use GuzzleHttp\\Client;

$http = new Client();

$response = $http->request('POST', 'https://app.invoicly.io/api/v1/clients', [
    'headers' => [
        'Authorization' => 'Bearer YOUR_API_TOKEN',
        'Accept'        => 'application/json',
    ],
    'json' => [
        'type'          => 'external',
        'is_business'   => true,
        'business_name' => 'Acme Corporation',
        'country'       => 'US',
        'street'        => '123 Main St',
        'city'          => 'New York',
        'postal_code'   => '10001',
        'email'         => 'billing@acme.com',
    ],
]);

$client = json_decode($response->getBody()->getContents(), true)['data'];`,
            node: `const axios = require('axios');

const { data } = await axios.post(
  'https://app.invoicly.io/api/v1/clients',
  {
    type:          'external',
    is_business:   true,
    business_name: 'Acme Corporation',
    country:       'US',
    street:        '123 Main St',
    city:          'New York',
    postal_code:   '10001',
    email:         'billing@acme.com',
  },
  { headers: { Authorization: 'Bearer YOUR_API_TOKEN' } }
);

console.log(data.data);`,
            python: `import requests

response = requests.post(
    'https://app.invoicly.io/api/v1/clients',
    headers={'Authorization': 'Bearer YOUR_API_TOKEN'},
    json={
        'type':          'external',
        'is_business':   True,
        'business_name': 'Acme Corporation',
        'country':       'US',
        'street':        '123 Main St',
        'city':          'New York',
        'postal_code':   '10001',
        'email':         'billing@acme.com',
    },
)

client = response.json()['data']
print(client)`,
            ruby: `require 'net/http'
require 'json'

uri = URI('https://app.invoicly.io/api/v1/clients')
req = Net::HTTP::Post.new(uri)
req['Authorization'] = 'Bearer YOUR_API_TOKEN'
req['Accept']        = 'application/json'
req['Content-Type']  = 'application/json'
req.body = JSON.generate({
  type:          'external',
  is_business:   true,
  business_name: 'Acme Corporation',
  country:       'US',
  street:        '123 Main St',
  city:          'New York',
  postal_code:   '10001',
  email:         'billing@acme.com',
})

res    = Net::HTTP.start(uri.host, uri.port, use_ssl: true) { |h| h.request(req) }
client = JSON.parse(res.body)['data']
puts client`,
        },
    },
];

// ── Computed helpers ─────────────────────────────────────────────────────────
const apiGroups = computed(() => {
    const seen   = new Set();
    const groups = [];
    for (const ep of apiEndpoints) {
        if (!seen.has(ep.group)) {
            seen.add(ep.group);
            groups.push({ id: ep.group, label: ep.groupLabel });
        }
    }
    return groups;
});

const currentEndpoint = computed(() => apiEndpoints.find(e => e.id === activeEndpoint.value) ?? apiEndpoints[0]);
const currentCode     = computed(() => currentEndpoint.value?.code?.[activeLang.value] ?? '');

const isWriteOp = computed(() => ['POST', 'PUT', 'DELETE'].includes(currentEndpoint.value?.method));

// Try It: params that should be shown (not line_items array — handled separately)
const tryableParams = computed(() =>
    (currentEndpoint.value?.params ?? []).filter(p => p.type !== 'array')
);
const hasLineItems = computed(() =>
    (currentEndpoint.value?.params ?? []).some(p => p.name === 'line_items')
);
</script>

<template>
    <Head title="Invoicly API — Developer Docs" />

    <div class="flex h-screen flex-col overflow-hidden bg-[#0d1117] text-gray-100 antialiased">

        <!-- ── HEADER ────────────────────────────────────────────────── -->
        <header class="flex h-14 flex-shrink-0 items-center justify-between border-b border-gray-800 bg-[#0a0d12] px-6">
            <div class="flex items-center gap-4">
                <Link href="/" class="flex items-center gap-2 text-base font-bold tracking-tight text-white">
                    <span class="inline-flex h-6 w-6 items-center justify-center rounded bg-brand-600 text-xs font-black text-white">i</span>
                    invoicly
                </Link>
                <span class="text-gray-700">/</span>
                <span class="text-sm text-gray-400">API Reference</span>
                <span class="rounded border border-gray-700 bg-gray-800 px-2 py-0.5 text-xs font-bold text-gray-400">v1</span>
                <span class="hidden text-xs text-gray-700 sm:block">OAS 3.0.4</span>
            </div>
            <Link href="/" class="flex items-center gap-1.5 rounded-lg border border-gray-700 px-3 py-1.5 text-xs font-medium text-gray-400 transition hover:border-gray-600 hover:text-gray-200">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5">
                    <path fill-rule="evenodd" d="M17 10a.75.75 0 0 1-.75.75H5.612l4.158 3.96a.75.75 0 1 1-1.04 1.08l-5.5-5.25a.75.75 0 0 1 0-1.08l5.5-5.25a.75.75 0 1 1 1.04 1.08L5.612 9.25H16.25A.75.75 0 0 1 17 10Z" clip-rule="evenodd" />
                </svg>
                Back to Invoicly
            </Link>
        </header>

        <!-- ── THREE-COLUMN BODY ─────────────────────────────────────── -->
        <div class="flex min-h-0 flex-1 overflow-hidden">

            <!-- LEFT SIDEBAR -->
            <aside class="hidden w-56 flex-shrink-0 overflow-y-auto border-r border-gray-800 bg-[#0a0d12] py-5 lg:block">
                <nav>
                    <div v-for="group in apiGroups" :key="group.id" class="mb-5 px-3">
                        <p class="mb-2 px-3 text-xs font-semibold uppercase tracking-widest text-gray-600">{{ group.label }}</p>
                        <ul class="space-y-0.5">
                            <li v-for="ep in apiEndpoints.filter(e => e.group === group.id)" :key="ep.id">
                                <button
                                    @click="activeEndpoint = ep.id"
                                    class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm transition"
                                    :class="activeEndpoint === ep.id
                                        ? 'bg-gray-800 text-white'
                                        : 'text-gray-500 hover:bg-gray-800/40 hover:text-gray-200'"
                                >
                                    <span
                                        v-if="ep.method"
                                        class="w-14 flex-shrink-0 text-right text-xs font-bold"
                                        :class="{
                                            'text-blue-400':   ep.method === 'GET',
                                            'text-green-400':  ep.method === 'POST',
                                            'text-yellow-400': ep.method === 'PUT',
                                            'text-red-400':    ep.method === 'DELETE',
                                        }"
                                    >{{ ep.method }}</span>
                                    <span v-else class="w-14 flex-shrink-0"></span>
                                    <span class="truncate">{{ ep.title }}</span>
                                </button>
                            </li>
                        </ul>
                    </div>
                </nav>
            </aside>

            <!-- CENTER: docs content -->
            <div class="flex-1 overflow-y-auto p-8">

                <!-- Mobile: endpoint selector pills -->
                <div class="mb-6 flex flex-wrap gap-2 lg:hidden">
                    <button
                        v-for="ep in apiEndpoints.filter(e => e.method)"
                        :key="ep.id"
                        @click="activeEndpoint = ep.id"
                        class="flex items-center gap-1.5 rounded-lg border border-gray-800 bg-gray-900 px-3 py-1.5 text-xs transition"
                        :class="activeEndpoint === ep.id ? 'border-brand-700 text-white' : 'text-gray-400 hover:border-gray-700 hover:text-gray-200'"
                    >
                        <span class="font-bold" :class="{
                            'text-blue-400': ep.method === 'GET',
                            'text-green-400': ep.method === 'POST',
                            'text-yellow-400': ep.method === 'PUT',
                            'text-red-400': ep.method === 'DELETE',
                        }">{{ ep.method }}</span>
                        <span>{{ ep.title }}</span>
                    </button>
                </div>

                <template v-if="currentEndpoint">
                    <!-- Title row -->
                    <div class="mb-5 flex flex-wrap items-center gap-3">
                        <span
                            v-if="currentEndpoint.method"
                            class="rounded px-3 py-1.5 text-sm font-bold"
                            :class="{
                                'bg-blue-950 text-blue-300':     currentEndpoint.method === 'GET',
                                'bg-green-950 text-green-300':   currentEndpoint.method === 'POST',
                                'bg-yellow-950 text-yellow-300': currentEndpoint.method === 'PUT',
                                'bg-red-950 text-red-300':       currentEndpoint.method === 'DELETE',
                            }"
                        >{{ currentEndpoint.method }}</span>
                        <code v-if="currentEndpoint.path" class="font-mono text-base text-white">{{ currentEndpoint.path }}</code>
                        <h1 v-else class="text-2xl font-bold text-white">{{ currentEndpoint.title }}</h1>
                        <span
                            v-if="currentEndpoint.ability"
                            class="ml-auto rounded-full border border-purple-800 bg-purple-950/40 px-2.5 py-0.5 font-mono text-xs text-purple-400"
                        >{{ currentEndpoint.ability }}</span>
                    </div>

                    <h2 v-if="currentEndpoint.path" class="mb-4 text-xl font-bold text-white">{{ currentEndpoint.title }}</h2>

                    <p class="mb-6 max-w-2xl leading-relaxed text-gray-400">{{ currentEndpoint.description }}</p>

                    <!-- Feature list (overview only) -->
                    <ul v-if="currentEndpoint.features" class="mb-8 space-y-2.5">
                        <li v-for="f in currentEndpoint.features" :key="f" class="flex items-center gap-2.5 text-sm text-gray-400">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 flex-shrink-0 text-green-500">
                                <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                            </svg>
                            {{ f }}
                        </li>
                    </ul>

                    <!-- Note box -->
                    <div v-if="currentEndpoint.note" class="mb-6 rounded-lg border border-blue-800/60 bg-blue-950/30 px-4 py-3">
                        <div class="mb-1.5 flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 flex-shrink-0 text-blue-400">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Zm-7-4a1 1 0 1 1-2 0 1 1 0 0 1 2 0ZM9 9a.75.75 0 0 0 0 1.5h.253a.25.25 0 0 1 .244.304l-.459 2.066A1.75 1.75 0 0 0 10.747 15H11a.75.75 0 0 0 0-1.5h-.253a.25.25 0 0 1-.244-.304l.459-2.066A1.75 1.75 0 0 0 9.253 9H9Z" clip-rule="evenodd" />
                            </svg>
                            <span class="text-xs font-semibold uppercase tracking-widest text-blue-400">Note</span>
                        </div>
                        <p class="text-sm text-blue-300">{{ currentEndpoint.note }}</p>
                    </div>

                    <!-- Auth example header block -->
                    <div v-if="currentEndpoint.id === 'auth'" class="mb-6">
                        <h3 class="mb-3 text-xs font-semibold uppercase tracking-widest text-gray-600">Example Authorization Header</h3>
                        <div class="rounded-lg border border-gray-800 bg-gray-900 px-4 py-3 font-mono text-xs text-gray-300">
                            Authorization: Bearer YOUR_API_TOKEN
                        </div>
                    </div>

                    <!-- Parameters table -->
                    <div v-if="currentEndpoint.params.length > 0" class="mb-8">
                        <h3 class="mb-3 text-xs font-semibold uppercase tracking-widest text-gray-600">Parameters</h3>
                        <div class="overflow-hidden rounded-lg border border-gray-800">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-gray-800 bg-gray-900/80">
                                        <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Name</th>
                                        <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">In</th>
                                        <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Type</th>
                                        <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Required</th>
                                        <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Description</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-800/60">
                                    <tr v-for="param in currentEndpoint.params" :key="param.name" class="transition hover:bg-gray-900/30">
                                        <td class="px-4 py-3 font-mono text-xs text-white">{{ param.name }}</td>
                                        <td class="px-4 py-3 text-xs text-gray-500">{{ param.in }}</td>
                                        <td class="px-4 py-3 text-xs text-gray-400">{{ param.type }}</td>
                                        <td class="px-4 py-3">
                                            <span v-if="param.required" class="rounded-full bg-red-950/60 px-2 py-0.5 text-xs font-medium text-red-400">required</span>
                                            <span v-else class="text-xs text-gray-700">optional</span>
                                        </td>
                                        <td class="px-4 py-3 text-xs leading-relaxed text-gray-400">{{ param.description }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Response example -->
                    <div v-if="currentEndpoint.response">
                        <h3 class="mb-3 text-xs font-semibold uppercase tracking-widest text-gray-600">Response</h3>
                        <div class="overflow-hidden rounded-lg border border-gray-800">
                            <div class="flex items-center gap-2 border-b border-gray-800 bg-gray-900/80 px-4 py-2.5">
                                <span class="rounded-full bg-green-900/60 px-2.5 py-0.5 text-xs font-semibold text-green-400">200 OK</span>
                                <span class="text-xs text-gray-600">application/json</span>
                            </div>
                            <pre class="overflow-x-auto bg-gray-900/40 p-4 font-mono text-xs leading-relaxed text-gray-300">{{ currentEndpoint.response }}</pre>
                        </div>
                    </div>
                </template>
            </div>

            <!-- RIGHT PANEL: Code + Try It -->
            <aside class="hidden w-88 flex-shrink-0 overflow-y-auto border-l border-gray-800 bg-[#0a0d12] xl:block" style="width: 22rem;">

                <!-- Server info -->
                <div class="border-b border-gray-800 p-4">
                    <div class="mb-3">
                        <p class="mb-1.5 text-xs font-semibold uppercase tracking-widest text-gray-600">Server</p>
                        <div class="rounded border border-gray-700 bg-gray-900 px-3 py-2.5 font-mono text-xs text-gray-300">
                            https://app.invoicly.io
                        </div>
                    </div>
                    <div>
                        <p class="mb-1.5 text-xs font-semibold uppercase tracking-widest text-gray-600">API Server</p>
                        <div class="rounded border border-gray-700 bg-gray-900 px-3 py-2.5 font-mono text-xs text-gray-500">
                            /api/v1
                        </div>
                    </div>
                </div>

                <!-- Tab switcher -->
                <div class="flex border-b border-gray-800">
                    <button
                        @click="rightTab = 'code'"
                        class="flex-1 py-2.5 text-xs font-semibold transition"
                        :class="rightTab === 'code'
                            ? 'border-b-2 border-brand-500 text-white'
                            : 'text-gray-500 hover:text-gray-300'"
                    >Code</button>
                    <button
                        @click="rightTab = 'try'"
                        class="flex-1 py-2.5 text-xs font-semibold transition"
                        :class="rightTab === 'try'
                            ? 'border-b-2 border-brand-500 text-white'
                            : 'text-gray-500 hover:text-gray-300'"
                    >Try It</button>
                </div>

                <!-- CODE TAB ───────────────────────────────────────────── -->
                <div v-if="rightTab === 'code'" class="p-4">
                    <!-- Language pills -->
                    <div class="mb-3">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-widest text-gray-600">Client Libraries</p>
                        <div class="flex flex-wrap gap-1">
                            <button
                                v-for="lang in langs"
                                :key="lang.id"
                                @click="activeLang = lang.id"
                                class="rounded px-3 py-1.5 text-xs font-medium transition"
                                :class="activeLang === lang.id
                                    ? 'bg-gray-700 text-white'
                                    : 'text-gray-500 hover:text-gray-300'"
                            >{{ lang.label }}</button>
                        </div>
                    </div>

                    <!-- Code block with copy button -->
                    <div v-if="currentCode" class="relative rounded-lg border border-gray-800 bg-gray-900">
                        <div class="flex items-center justify-between border-b border-gray-800 px-3 py-2">
                            <span class="text-xs text-gray-600">{{ langs.find(l => l.id === activeLang)?.label }}</span>
                            <button
                                @click="copyCode(currentCode)"
                                class="flex items-center gap-1.5 rounded px-2 py-1 text-xs transition"
                                :class="copied ? 'text-green-400' : 'text-gray-500 hover:text-gray-300'"
                            >
                                <svg v-if="!copied" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5">
                                    <path d="M7 3.5A1.5 1.5 0 0 1 8.5 2h3.879a1.5 1.5 0 0 1 1.06.44l3.122 3.12A1.5 1.5 0 0 1 17 6.622V12.5a1.5 1.5 0 0 1-1.5 1.5h-1v-3.379a3 3 0 0 0-.879-2.121L10.5 5.379A3 3 0 0 0 8.379 4.5H7v-1Z" />
                                    <path d="M4.5 6A1.5 1.5 0 0 0 3 7.5v9A1.5 1.5 0 0 0 4.5 18h7a1.5 1.5 0 0 0 1.5-1.5v-5.879a1.5 1.5 0 0 0-.44-1.06L9.44 6.439A1.5 1.5 0 0 0 8.378 6H4.5Z" />
                                </svg>
                                <svg v-else xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5">
                                    <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                                </svg>
                                {{ copied ? 'Copied!' : 'Copy' }}
                            </button>
                        </div>
                        <pre class="overflow-x-auto p-4 font-mono text-xs leading-relaxed text-gray-300 whitespace-pre-wrap break-words">{{ currentCode }}</pre>
                    </div>
                    <div v-else class="rounded-lg border border-gray-800 bg-gray-900 p-6 text-center">
                        <p class="text-xs text-gray-600">Select an endpoint to see a code example</p>
                    </div>
                </div>

                <!-- TRY IT TAB ──────────────────────────────────────────── -->
                <div v-if="rightTab === 'try'" class="p-4">

                    <!-- No path = not executable -->
                    <div v-if="!currentEndpoint.path" class="rounded-lg border border-gray-800 bg-gray-900 p-5 text-center">
                        <p class="text-xs text-gray-500">Select an endpoint from the sidebar to try it live.</p>
                    </div>

                    <template v-else>
                        <!-- Write op warning -->
                        <div v-if="isWriteOp" class="mb-4 rounded-lg border border-yellow-800/60 bg-yellow-950/30 px-3 py-2.5">
                            <div class="flex items-start gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="mt-0.5 h-4 w-4 flex-shrink-0 text-yellow-400">
                                    <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495ZM10 5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 5Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd" />
                                </svg>
                                <p class="text-xs text-yellow-300">This request will make real changes to your account.</p>
                            </div>
                        </div>

                        <!-- Token input -->
                        <div class="mb-4">
                            <label class="mb-1.5 block text-xs font-semibold uppercase tracking-widest text-gray-600">
                                API Token
                            </label>
                            <input
                                v-model="tryToken"
                                type="password"
                                placeholder="Bearer token from Settings → API Tokens"
                                class="w-full rounded-lg border border-gray-700 bg-gray-900 px-3 py-2 font-mono text-xs text-gray-300 placeholder-gray-700 outline-none transition focus:border-brand-600 focus:ring-1 focus:ring-brand-600"
                            />
                        </div>

                        <!-- Parameter inputs -->
                        <div v-if="tryableParams.length > 0" class="mb-4 space-y-3">
                            <p class="text-xs font-semibold uppercase tracking-widest text-gray-600">Parameters</p>
                            <div v-for="param in tryableParams" :key="param.name">
                                <label class="mb-1 flex items-center gap-1.5 text-xs text-gray-500">
                                    <span class="font-mono text-gray-300">{{ param.name }}</span>
                                    <span class="text-gray-700">{{ param.type }}</span>
                                    <span v-if="param.required" class="text-red-500">*</span>
                                    <span class="ml-auto rounded-full border border-gray-800 px-1.5 text-gray-700">{{ param.in }}</span>
                                </label>
                                <input
                                    v-model="tryParams[param.name]"
                                    type="text"
                                    :placeholder="param.description"
                                    class="w-full rounded-lg border border-gray-700 bg-gray-900 px-3 py-2 font-mono text-xs text-gray-300 placeholder-gray-700 outline-none transition focus:border-brand-600 focus:ring-1 focus:ring-brand-600"
                                />
                            </div>
                        </div>

                        <!-- line_items textarea (special-cased) -->
                        <div v-if="hasLineItems" class="mb-4">
                            <label class="mb-1 flex items-center gap-1.5 text-xs text-gray-500">
                                <span class="font-mono text-gray-300">line_items</span>
                                <span class="text-gray-700">array</span>
                                <span class="text-red-500">*</span>
                                <span class="ml-auto rounded-full border border-gray-800 px-1.5 text-gray-700">body</span>
                            </label>
                            <textarea
                                v-model="tryParams['line_items_raw']"
                                rows="5"
                                placeholder='[{"description":"Service","quantity":1,"unit_price":100}]'
                                class="w-full rounded-lg border border-gray-700 bg-gray-900 px-3 py-2 font-mono text-xs text-gray-300 placeholder-gray-700 outline-none transition focus:border-brand-600 focus:ring-1 focus:ring-brand-600"
                            ></textarea>
                        </div>

                        <!-- Send button -->
                        <button
                            @click="sendRequest"
                            :disabled="tryLoading"
                            class="w-full rounded-lg bg-brand-600 py-2.5 text-xs font-semibold text-white transition hover:bg-brand-700 disabled:opacity-60"
                        >
                            <span v-if="!tryLoading">Send Request</span>
                            <span v-else class="flex items-center justify-center gap-2">
                                <svg class="h-3.5 w-3.5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                                </svg>
                                Sending…
                            </span>
                        </button>

                        <!-- Response area -->
                        <div v-if="tryResponse" class="mt-4 overflow-hidden rounded-lg border border-gray-800">
                            <div class="flex items-center justify-between border-b border-gray-800 bg-gray-900 px-3 py-2">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                        :class="{
                                            'bg-green-900/60 text-green-400': tryResponse.status >= 200 && tryResponse.status < 300,
                                            'bg-yellow-900/60 text-yellow-400': tryResponse.status >= 300 && tryResponse.status < 400,
                                            'bg-red-900/60 text-red-400': tryResponse.status >= 400 || tryResponse.status === 0,
                                        }"
                                    >{{ tryResponse.status || 'ERR' }}</span>
                                    <span class="text-xs text-gray-500">{{ tryResponse.statusText }}</span>
                                </div>
                                <span class="text-xs text-gray-600">{{ tryResponse.ms }}ms</span>
                            </div>
                            <pre class="max-h-72 overflow-y-auto overflow-x-auto bg-gray-900/40 p-3 font-mono text-xs leading-relaxed text-gray-300 whitespace-pre-wrap break-words">{{ tryResponse.body }}</pre>
                        </div>
                    </template>
                </div>

            </aside>
        </div>
    </div>
</template>
