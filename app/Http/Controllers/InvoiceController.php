<?php

namespace App\Http\Controllers;

use App\Enums\ClientType;
use App\Enums\InvoiceStatus;
use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Requests\UpdateInvoiceRequest;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\PaymentMethod;
use App\Support\PdfAsset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\LaravelPdf\Facades\Pdf;

class InvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Invoice::class);

        $segment = $request->string('segment', 'external')->toString();
        if (! in_array($segment, ['invoicly', 'external'], true)) {
            $segment = 'external';
        }

        $segmentType = $segment === 'invoicly' ? ClientType::Invoicly : ClientType::External;

        $search = $request->string('search')->trim()->toString();
        $statusFilter = $request->string('status')->toString();
        $dateFrom = $request->date('date_from');
        $dateTo = $request->date('date_to');

        $scoped = Invoice::query()
            ->where('user_id', $request->user()->id)
            ->whereHas('client', fn($q) => $q->where('type', $segmentType->value));

        if ($search !== '') {
            $scoped->where(function ($q) use ($search) {
                $q->where('number', 'like', '%' . $search . '%')
                    ->orWhereHas('client', fn($c) => $c->where('name', 'like', '%' . $search . '%'));
            });
        }

        if ($dateFrom) {
            $scoped->whereDate('issue_date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $scoped->whereDate('issue_date', '<=', $dateTo);
        }

        $balanceBase = clone $scoped;

        $paidTotal = (string) (clone $balanceBase)
            ->where('status', InvoiceStatus::Paid)
            ->sum('amount');

        $awaitingTotal = (string) (clone $balanceBase)
            ->where('status', InvoiceStatus::AwaitingPayment)
            ->sum('amount');

        if ($statusFilter !== '' && in_array($statusFilter, [InvoiceStatus::Paid->value, InvoiceStatus::AwaitingPayment->value], true)) {
            $scoped->where('status', $statusFilter);
        }

        $invoices = $scoped
            ->with('client')
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn(Invoice $invoice) => [
                'id' => $invoice->id,
                'uuid' => $invoice->uuid,
                'number' => $invoice->number,
                'client' => [
                    'id' => $invoice->client->id,
                    'name' => $invoice->client->name,
                ],
                'issue_date' => $invoice->issue_date->format('Y-m-d'),
                'status' => $invoice->status->value,
                'currency' => $invoice->currency,
                'amount' => $invoice->amount,
                'amount_secondary' => $invoice->amount_secondary,
                'currency_secondary' => $invoice->currency_secondary,
                'has_attachment' => (bool) $invoice->attachment_path,
                'is_template' => (bool) $invoice->is_template,
            ]);

        return Inertia::render('Invoices/Index', [
            'invoices' => $invoices,
            'segment' => $segment,
            'filters' => [
                'search' => $search,
                'status' => $statusFilter,
                'date_from' => $dateFrom?->format('Y-m-d'),
                'date_to' => $dateTo?->format('Y-m-d'),
            ],
            'balance' => [
                'paid_total' => $paidTotal,
                'awaiting_total' => $awaitingTotal,
                'currency' => $request->user()->preferred_currency,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Invoice::class);

        $segment = $request->string('segment', 'external')->toString();
        if (! in_array($segment, ['invoicly', 'external'], true)) {
            $segment = 'external';
        }

        $segmentType = $segment === 'invoicly' ? ClientType::Invoicly : ClientType::External;

        $clients = Client::query()
            ->where('user_id', $request->user()->id)
            ->where('type', $segmentType->value)
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'type',
                'is_business',
                'first_name',
                'last_name',
                'business_name',
                'country',
                'street',
                'city',
                'postal_code',
                'email',
                'vat_number'
            ]);

        $countries = collect(config('countries'))
            ->map(fn(string $name, string $code) => ['code' => $code, 'name' => $name])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();

        $currencies = collect(config('currencies'))
            ->map(fn(array $data, string $code) => ['code' => $code, 'name' => $data['name'], 'symbol' => $data['symbol']])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();

        $nextInvoiceNumber = Invoice::nextNumberForUser($request->user(), $segmentType, null);

        $paymentMethods = PaymentMethod::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('name')
            ->get(['id', 'name', 'details']);

        return Inertia::render('Invoices/Create', [
            'segment' => $segment,
            'clients' => $clients,
            'countries' => $countries,
            'currencies' => $currencies,
            'nextInvoiceNumber' => $nextInvoiceNumber,
            'paymentMethods' => $paymentMethods,
        ]);
    }

    public function store(StoreInvoiceRequest $request): RedirectResponse
    {
        $this->authorize('create', Invoice::class);

        $data = $request->validated();
        $user = $request->user();

        if (empty($data['client_id'])) {
            $segmentType = ($data['segment'] ?? '') === 'invoicly' ? ClientType::Invoicly : ClientType::External;
            $isBusiness = (bool) $data['new_client_is_business'];
            $name = $isBusiness
                ? (string) $data['new_client_business_name']
                : trim((string) $data['new_client_first_name'] . ' ' . (string) $data['new_client_last_name']);

            $client = Client::query()->create([
                'user_id' => $user->id,
                'name' => $name,
                'type' => $segmentType->value,
                'is_business' => $isBusiness,
                'first_name' => $isBusiness ? null : $data['new_client_first_name'],
                'last_name' => $isBusiness ? null : $data['new_client_last_name'],
                'business_name' => $isBusiness ? $data['new_client_business_name'] : null,
                'country' => $data['new_client_country'],
                'street' => $data['new_client_street'],
                'city' => $data['new_client_city'],
                'postal_code' => $data['new_client_postal_code'],
                'email' => $data['new_client_email'] ?? null,
                'vat_number' => $isBusiness ? ($data['new_client_vat_number'] ?? null) : null,
            ]);
        } else {
            $client = Client::query()
                ->where('user_id', $user->id)
                ->findOrFail($data['client_id']);
        }

        $amount = $this->lineItemsTotal($data['line_items']);

        DB::transaction(function () use ($request, $data, $client, $amount, $user) {
            if ($request->hasFile('identity_logo')) {
                if ($user->logo_path) {
                    Storage::disk('public')->delete($user->logo_path);
                }
                $user->logo_path = $request->file('identity_logo')->store('profile-logos', 'public');
                $user->save();
            }

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
            $invoice->number = Invoice::nextNumberForUser(
                $user,
                $client->type,
                $invoice->issue_date
            );

            if ($request->hasFile('attachment')) {
                $invoice->attachment_path = $request->file('attachment')->store('invoices/attachments', 'local');
            }

            $invoice->save();

            foreach ($data['line_items'] as $index => $row) {
                $invoice->lineItems()->create([
                    'description' => $row['description'],
                    'quantity' => $row['quantity'],
                    'unit_price' => $row['unit_price'],
                    'sort_order' => $index,
                ]);
            }
        });

        return redirect()
            ->route('invoices.index', ['segment' => $client->type === ClientType::Invoicly ? 'invoicly' : 'external'])
            ->with('success', 'Invoice created.');
    }

    public function edit(Request $request, Invoice $invoice): Response
    {
        $this->authorize('update', $invoice);

        $invoice->load(['client', 'lineItems']);

        $clients = Client::query()
            ->where('user_id', $request->user()->id)
            ->where('type', $invoice->client->type->value)
            ->orderBy('name')
            ->get(['id', 'name', 'type']);

        $paymentMethods = PaymentMethod::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('name')
            ->get(['id', 'name', 'details']);

        return Inertia::render('Invoices/Edit', [
            'invoice' => [
                'id' => $invoice->id,
                'uuid' => $invoice->uuid,
                'number' => $invoice->number,
                'client_id' => $invoice->client_id,
                'issue_date' => $invoice->issue_date->format('Y-m-d'),
                'status' => $invoice->status->value,
                'currency' => $invoice->currency,
                'amount_secondary' => $invoice->amount_secondary,
                'currency_secondary' => $invoice->currency_secondary,
                'payment_details' => $invoice->payment_details,
                'has_attachment' => (bool) $invoice->attachment_path,
                'is_template' => (bool) $invoice->is_template,
                'line_items' => $invoice->lineItems->map(fn(InvoiceLineItem $li) => [
                    'description' => $li->description,
                    'quantity' => (string) $li->quantity,
                    'unit_price' => (string) $li->unit_price,
                ]),
            ],
            'segment' => $invoice->client->type === ClientType::Invoicly ? 'invoicly' : 'external',
            'clients' => $clients,
            'paymentMethods' => $paymentMethods,
        ]);
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('update', $invoice);

        $data = $request->validated();
        $client = Client::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($data['client_id']);

        if ($client->type !== $invoice->client->type) {
            abort(422, 'Client segment cannot be changed for this invoice.');
        }

        $amount = $this->lineItemsTotal($data['line_items']);

        DB::transaction(function () use ($request, $invoice, $data, $amount) {
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

            if (! empty($data['remove_attachment']) && $invoice->attachment_path) {
                Storage::disk('local')->delete($invoice->attachment_path);
                $invoice->attachment_path = null;
            }

            if ($request->hasFile('attachment')) {
                if ($invoice->attachment_path) {
                    Storage::disk('local')->delete($invoice->attachment_path);
                }
                $invoice->attachment_path = $request->file('attachment')->store('invoices/attachments', 'local');
            }

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

        $invoice->refresh();
        $invoice->load('client');

        return redirect()
            ->route('invoices.index', ['segment' => $invoice->client->type === ClientType::Invoicly ? 'invoicly' : 'external'])
            ->with('success', 'Invoice updated.');
    }

    public function destroy(Invoice $invoice): RedirectResponse
    {
        $this->authorize('delete', $invoice);

        $segment = $invoice->client->type === ClientType::Invoicly ? 'invoicly' : 'external';

        if ($invoice->attachment_path) {
            Storage::disk('local')->delete($invoice->attachment_path);
        }

        $invoice->delete();

        return redirect()
            ->route('invoices.index', ['segment' => $segment])
            ->with('success', 'Invoice deleted.');
    }

    public function previewInvoice(Request $request)
    {
        $this->authorize('create', Invoice::class);

        $user = $request->user();

        $lineItemsData = $request->input('line_items', []);
        $amount = $this->lineItemsTotal(
            array_filter((array) $lineItemsData, fn($r) => isset($r['quantity'], $r['unit_price']))
        );

        $clientId = $request->input('client_id');
        if ($clientId) {
            $client = Client::query()
                ->where('user_id', $user->id)
                ->find($clientId);
            if (! $client) {
                $client = new Client(['name' => 'Client']);
            }
        } else {
            $isBusiness = (bool) $request->input('new_client_is_business', false);
            $name = $isBusiness
                ? (string) $request->input('new_client_business_name', 'Client')
                : trim(
                    (string) $request->input('new_client_first_name', '')
                        . ' ' .
                        (string) $request->input('new_client_last_name', '')
                );
            $client = new Client([
                'name' => $name ?: 'Client',
                'country' => $request->input('new_client_country'),
                'street' => $request->input('new_client_street'),
                'city' => $request->input('new_client_city'),
                'postal_code' => $request->input('new_client_postal_code'),
                'vat_number' => $isBusiness ? $request->input('new_client_vat_number') : null,
            ]);
        }

        $currency = strtoupper((string) $request->input('currency', $user->preferred_currency ?: 'USD'));

        $invoice = new Invoice([
            'number' => $request->input('invoice_number', Invoice::nextNumberForUser($user, \App\Enums\ClientType::External, null)),
            'issue_date' => $request->input('issue_date', now()->toDateString()),
            'due_date' => $request->input('due_date'),
            'period_from' => $request->input('period_from'),
            'period_to' => $request->input('period_to'),
            'status' => InvoiceStatus::AwaitingPayment,
            'currency' => $currency,
            'amount' => $amount,
            'vat_amount' => $request->input('vat_amount'),
            'payer_memo' => $request->input('payer_memo'),
            'payment_details' => $request->input('payment_details'),
            'invoice_type' => $request->input('invoice_type', 'Service'),
            'vat_id' => $request->input('vat_id'),
            'tax_id' => $request->input('tax_id'),
        ]);

        $lineItems = collect($lineItemsData)
            ->filter(fn($r) => isset($r['description'], $r['quantity'], $r['unit_price']))
            ->values()
            ->map(fn($r, $i) => new InvoiceLineItem([
                'description' => $r['description'],
                'quantity' => $r['quantity'],
                'unit_price' => $r['unit_price'],
                'sort_order' => $i,
            ]));

        $invoice->setRelation('client', $client);
        $invoice->setRelation('lineItems', $lineItems);
        $invoice->setRelation('user', $user);

        return Pdf::view('pdfs.invoice', [
            'invoice' => $invoice,
            'issuer' => $user,
            'issuerLogoUri' => PdfAsset::dataUriFromPublicPath($user->logo_path),
            'issuerSignatureUri' => PdfAsset::dataUriFromPublicPath($user->invoice_signature_path),
            'isPreview' => true,
        ])
            ->format('a4')
            ->inline();
    }

    public function pdf(Invoice $invoice)
    {
        $this->authorize('view', $invoice);

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
            ->name($invoice->number . '.pdf');
    }

    public function downloadAttachment(Request $request, Invoice $invoice)
    {
        $this->authorize('view', $invoice);

        if (! $invoice->attachment_path || ! Storage::disk('local')->exists($invoice->attachment_path)) {
            abort(404);
        }

        return Storage::disk('local')->download($invoice->attachment_path);
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
