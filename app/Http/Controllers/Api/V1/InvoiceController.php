<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ClientType;
use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Support\PdfAsset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Spatie\LaravelPdf\Facades\Pdf;

class InvoiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Invoice::class);

        $user = $request->user();

        abort_unless($user->tokenCan('invoices:read'), 403, 'Token missing invoices:read ability.');

        $query = Invoice::query()
            ->where('user_id', $user->id)
            ->with(['client', 'lineItems']);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->integer('client_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('issue_date', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('issue_date', '<=', $request->input('date_to'));
        }

        $invoices = $query
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return InvoiceResource::collection($invoices)->response();
    }

    public function show(Request $request, Invoice $invoice): JsonResponse
    {
        Gate::authorize('view', $invoice);

        abort_unless($request->user()->tokenCan('invoices:read'), 403, 'Token missing invoices:read ability.');

        $invoice->load(['client', 'lineItems']);

        return (new InvoiceResource($invoice))->response();
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Invoice::class);

        abort_unless($request->user()->tokenCan('invoices:write'), 403, 'Token missing invoices:write ability.');

        $user = $request->user();
        $userId = (int) $user->id;
        $countryCodes = array_keys(config('countries'));

        $data = $request->validate([
            'client_id' => [
                'required',
                'integer',
                Rule::exists('clients', 'id')->where('user_id', $userId),
            ],
            'issue_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'period_from' => ['nullable', 'date'],
            'period_to' => ['nullable', 'date'],
            'status' => ['required', Rule::enum(InvoiceStatus::class)],
            'currency' => ['required', 'string', 'size:3'],
            'vat_amount' => ['nullable', 'numeric', 'min:0'],
            'amount_secondary' => ['nullable', 'numeric', 'min:0'],
            'currency_secondary' => ['nullable', 'string', 'size:3', 'required_with:amount_secondary'],
            'payer_memo' => ['nullable', 'string', 'max:300'],
            'payment_details' => ['nullable', 'string', 'max:250'],
            'invoice_type' => ['nullable', 'string', 'max:100'],
            'vat_id' => ['nullable', 'string', 'max:100'],
            'tax_id' => ['nullable', 'string', 'max:100'],
            'is_template' => ['nullable', 'boolean'],
            'line_items' => ['required', 'array', 'min:1'],
            'line_items.*.description' => ['required', 'string', 'max:255'],
            'line_items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'line_items.*.unit_price' => ['required', 'numeric', 'min:0'],
            // Optional inline new-client fields
            'new_client_is_business' => ['sometimes', 'boolean'],
            'new_client_first_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'new_client_last_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'new_client_business_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'new_client_country' => ['sometimes', 'string', 'size:2', Rule::in($countryCodes)],
            'new_client_street' => ['sometimes', 'string', 'max:255'],
            'new_client_city' => ['sometimes', 'string', 'max:100'],
            'new_client_postal_code' => ['sometimes', 'string', 'max:32'],
            'new_client_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'new_client_vat_number' => ['sometimes', 'nullable', 'string', 'max:64'],
        ]);

        $client = Client::query()
            ->where('user_id', $userId)
            ->findOrFail($data['client_id']);

        $amount = $this->lineItemsTotal($data['line_items']);

        $invoice = DB::transaction(function () use ($data, $client, $amount, $user) {
            $invoice = new Invoice([
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'] ?? null,
                'period_from' => $data['period_from'] ?? null,
                'period_to' => $data['period_to'] ?? null,
                'status' => $data['status'],
                'currency' => strtoupper($data['currency']),
                'amount' => $amount,
                'vat_amount' => $data['vat_amount'] ?? null,
                'payer_memo' => $data['payer_memo'] ?? null,
                'payment_details' => $data['payment_details'] ?? null,
                'invoice_type' => $data['invoice_type'] ?? 'Service',
                'vat_id' => $data['vat_id'] ?? null,
                'tax_id' => $data['tax_id'] ?? null,
                'amount_secondary' => $data['amount_secondary'] ?? null,
                'currency_secondary' => isset($data['currency_secondary']) ? strtoupper($data['currency_secondary']) : null,
                'is_template' => (bool) ($data['is_template'] ?? false),
            ]);

            $invoice->user()->associate($user);
            $invoice->client()->associate($client);
            $invoice->number = Invoice::nextNumberForUser($user, $client->type, $invoice->issue_date);
            $invoice->save();

            foreach ($data['line_items'] as $index => $row) {
                $invoice->lineItems()->create([
                    'description' => $row['description'],
                    'quantity' => $row['quantity'],
                    'unit_price' => $row['unit_price'],
                    'sort_order' => $index,
                ]);
            }

            return $invoice;
        });

        $invoice->load(['client', 'lineItems']);

        return (new InvoiceResource($invoice))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, Invoice $invoice): JsonResponse
    {
        Gate::authorize('update', $invoice);

        abort_unless($request->user()->tokenCan('invoices:write'), 403, 'Token missing invoices:write ability.');

        $userId = (int) $request->user()->id;

        $data = $request->validate([
            'client_id' => [
                'required',
                'integer',
                Rule::exists('clients', 'id')->where('user_id', $userId),
            ],
            'issue_date' => ['required', 'date'],
            'status' => ['required', Rule::enum(InvoiceStatus::class)],
            'currency' => ['required', 'string', 'size:3'],
            'amount_secondary' => ['nullable', 'numeric', 'min:0'],
            'currency_secondary' => ['nullable', 'string', 'size:3', 'required_with:amount_secondary'],
            'is_template' => ['sometimes', 'boolean'],
            'payment_details' => ['nullable', 'string', 'max:500'],
            'line_items' => ['required', 'array', 'min:1'],
            'line_items.*.description' => ['required', 'string', 'max:255'],
            'line_items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'line_items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        $client = Client::query()
            ->where('user_id', $userId)
            ->findOrFail($data['client_id']);

        if ($client->type !== $invoice->client->type) {
            abort(422, 'Client segment cannot be changed for this invoice.');
        }

        $amount = $this->lineItemsTotal($data['line_items']);

        DB::transaction(function () use ($invoice, $data, $amount) {
            $invoice->fill([
                'client_id' => $data['client_id'],
                'issue_date' => $data['issue_date'],
                'status' => $data['status'],
                'currency' => strtoupper($data['currency']),
                'amount' => $amount,
                'amount_secondary' => $data['amount_secondary'] ?? null,
                'currency_secondary' => isset($data['currency_secondary']) ? strtoupper($data['currency_secondary']) : null,
                'payment_details' => $data['payment_details'] ?? null,
                'is_template' => (bool) ($data['is_template'] ?? false),
            ]);
            $invoice->save();

            $invoice->lineItems()->delete();
            foreach ($data['line_items'] as $index => $row) {
                $invoice->lineItems()->create([
                    'description' => $row['description'],
                    'quantity' => $row['quantity'],
                    'unit_price' => $row['unit_price'],
                    'sort_order' => $index,
                ]);
            }
        });

        $invoice->refresh()->load(['client', 'lineItems']);

        return (new InvoiceResource($invoice))->response();
    }

    public function destroy(Request $request, Invoice $invoice): JsonResponse
    {
        Gate::authorize('delete', $invoice);

        abort_unless($request->user()->tokenCan('invoices:write'), 403, 'Token missing invoices:write ability.');

        $invoice->delete();

        return response()->json(['message' => 'Invoice deleted.']);
    }

    public function pdf(Request $request, Invoice $invoice)
    {
        Gate::authorize('view', $invoice);

        abort_unless($request->user()->tokenCan('invoices:read'), 403, 'Token missing invoices:read ability.');

        $invoice->load(['client', 'lineItems', 'user']);
        $issuer = $invoice->user;

        return Pdf::view('pdfs.invoice', [
            'invoice' => $invoice,
            'issuer' => $issuer,
            'issuerLogoUri' => PdfAsset::dataUriFromPublicPath($issuer?->logo_path),
            'issuerSignatureUri' => PdfAsset::dataUriFromPublicPath($issuer?->invoice_signature_path),
            'isPreview' => false,
        ])
            ->format('a4')
            ->name($invoice->number.'.pdf');
    }

    /**
     * @param  array<int, array{description: string, quantity: mixed, unit_price: mixed}>  $lineItems
     */
    private function lineItemsTotal(array $lineItems): string
    {
        $sum = '0.00';
        foreach ($lineItems as $row) {
            $line = bcmul((string) $row['quantity'], (string) $row['unit_price'], 2);
            $sum = bcadd($sum, $line, 2);
        }

        return $sum;
    }
}
