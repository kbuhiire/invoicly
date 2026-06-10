<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Enums\QuoteStatus;
use App\Http\Requests\StoreQuoteRequest;
use App\Http\Requests\UpdateQuoteRequest;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Quote;
use App\Services\DocumentNumberService;
use App\Support\LineItems;
use App\Support\PdfAsset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\LaravelPdf\Facades\Pdf;

class QuoteController extends Controller
{
    public function __construct(private readonly DocumentNumberService $numbers) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Quote::class);

        $search = $request->string('search')->trim()->toString();
        $statusFilter = $request->string('status')->toString();

        $perPage = $request->integer('per_page', 10);
        if (! in_array($perPage, [10, 25, 50], true)) {
            $perPage = 10;
        }

        $scoped = Quote::query()
            ->where('user_id', $request->user()->id);

        if ($search !== '') {
            $scoped->where(function ($q) use ($search) {
                $q->where('number', 'like', '%'.$search.'%')
                    ->orWhereHas('client', fn ($c) => $c->where('name', 'like', '%'.$search.'%'));
            });
        }

        // "expired" filters sent quotes past expiry; stored statuses filter directly.
        if ($statusFilter === 'expired') {
            $scoped->where('status', QuoteStatus::Sent->value)
                ->whereNotNull('expiry_date')
                ->whereDate('expiry_date', '<', now()->toDateString());
        } elseif (in_array($statusFilter, array_map(fn (QuoteStatus $c) => $c->value, QuoteStatus::cases()), true)) {
            $scoped->where('status', $statusFilter);
        }

        $quotes = $scoped
            ->with(['client:id,uuid,name', 'convertedInvoice:id,uuid,number'])
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Quote $quote) => [
                'id' => $quote->id,
                'uuid' => $quote->uuid,
                'number' => $quote->number,
                'status' => $quote->effectiveStatus(),
                'issue_date' => $quote->issue_date->format('Y-m-d'),
                'expiry_date' => $quote->expiry_date?->format('Y-m-d'),
                'currency' => $quote->currency,
                'amount' => $quote->amount,
                'client' => [
                    'id' => $quote->client?->id,
                    'name' => $quote->client?->name ?? '—',
                ],
                'converted_invoice' => $quote->convertedInvoice ? [
                    'uuid' => $quote->convertedInvoice->uuid,
                    'number' => $quote->convertedInvoice->number,
                ] : null,
            ]);

        return Inertia::render('Quotes/Index', [
            'quotes' => $quotes,
            'filters' => [
                'search' => $search !== '' ? $search : null,
                'status' => $statusFilter !== '' ? $statusFilter : null,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Quote::class);

        return Inertia::render('Quotes/Create', [
            'clients' => $this->clientsFor($request),
            'taxRates' => $this->taxRatesFor($request),
            'nextQuoteNumber' => $this->numbers->preview($request->user(), DocumentNumberService::TYPE_QUOTE),
            'preferredCurrency' => $request->user()->preferred_currency ?? 'UGX',
        ]);
    }

    public function store(StoreQuoteRequest $request): RedirectResponse
    {
        $this->authorize('create', Quote::class);

        $data = $request->validated();
        $user = $request->user();

        DB::transaction(function () use ($data, $user) {
            $quote = new Quote([
                'client_id' => $data['client_id'],
                'status' => QuoteStatus::Draft,
                'issue_date' => $data['issue_date'],
                'expiry_date' => $data['expiry_date'] ?? null,
                'currency' => strtoupper($data['currency']),
                'amount' => LineItems::total($data['line_items']),
                'vat_amount' => $data['vat_amount'] ?? null,
                'tax_rate_id' => $data['tax_rate_id'] ?? null,
                'payer_memo' => $data['payer_memo'] ?? null,
            ]);

            $quote->user()->associate($user);
            $quote->number = $this->numbers->next($user, DocumentNumberService::TYPE_QUOTE, $quote->issue_date);
            $quote->save();

            foreach ($data['line_items'] as $index => $row) {
                $quote->lineItems()->create([
                    'description' => $row['description'],
                    'quantity' => $row['quantity'],
                    'unit_price' => $row['unit_price'],
                    'sort_order' => $index,
                ]);
            }
        });

        return redirect()->route('quotes.index')->with('success', 'Quote created.');
    }

    public function edit(Request $request, Quote $quote): Response
    {
        $this->authorize('update', $quote);

        $quote->load(['client', 'lineItems']);

        return Inertia::render('Quotes/Edit', [
            'quote' => [
                'id' => $quote->id,
                'uuid' => $quote->uuid,
                'number' => $quote->number,
                'status' => $quote->status->value,
                'effective_status' => $quote->effectiveStatus(),
                'client_id' => $quote->client_id,
                'issue_date' => $quote->issue_date->format('Y-m-d'),
                'expiry_date' => $quote->expiry_date?->format('Y-m-d'),
                'currency' => $quote->currency,
                'vat_amount' => $quote->vat_amount,
                'tax_rate_id' => $quote->tax_rate_id,
                'payer_memo' => $quote->payer_memo,
                'converted_invoice_id' => $quote->converted_invoice_id,
                'line_items' => $quote->lineItems->map(fn ($li) => [
                    'description' => $li->description,
                    'quantity' => (string) $li->quantity,
                    'unit_price' => (string) $li->unit_price,
                ]),
            ],
            'clients' => $this->clientsFor($request),
            'taxRates' => $this->taxRatesFor($request),
        ]);
    }

    public function update(UpdateQuoteRequest $request, Quote $quote): RedirectResponse
    {
        $this->authorize('update', $quote);

        if ($quote->converted_invoice_id !== null) {
            throw ValidationException::withMessages(['quote' => 'A converted quote can no longer be edited.']);
        }

        $data = $request->validated();

        DB::transaction(function () use ($quote, $data) {
            $quote->fill([
                'client_id' => $data['client_id'],
                'issue_date' => $data['issue_date'],
                'expiry_date' => $data['expiry_date'] ?? null,
                'currency' => strtoupper($data['currency']),
                'amount' => LineItems::total($data['line_items']),
                'vat_amount' => $data['vat_amount'] ?? null,
                'tax_rate_id' => $data['tax_rate_id'] ?? null,
                'payer_memo' => $data['payer_memo'] ?? null,
            ])->save();

            $quote->lineItems()->delete();
            foreach ($data['line_items'] as $index => $row) {
                $quote->lineItems()->create([
                    'description' => $row['description'],
                    'quantity' => $row['quantity'],
                    'unit_price' => $row['unit_price'],
                    'sort_order' => $index,
                ]);
            }
        });

        return redirect()->route('quotes.index')->with('success', 'Quote updated.');
    }

    public function destroy(Quote $quote): RedirectResponse
    {
        $this->authorize('delete', $quote);

        $quote->delete();

        return redirect()->route('quotes.index')->with('success', 'Quote deleted.');
    }

    public function markSent(Quote $quote): RedirectResponse
    {
        $this->authorize('update', $quote);

        if ($quote->status !== QuoteStatus::Draft) {
            throw ValidationException::withMessages(['quote' => 'Only draft quotes can be marked as sent.']);
        }

        $quote->forceFill([
            'status' => QuoteStatus::Sent,
            'sent_at' => now(),
        ])->save();

        return back()->with('success', "Quote {$quote->number} marked as sent.");
    }

    public function accept(Quote $quote): RedirectResponse
    {
        $this->authorize('update', $quote);

        if (! in_array($quote->status, [QuoteStatus::Draft, QuoteStatus::Sent], true)) {
            throw ValidationException::withMessages(['quote' => 'Only open quotes can be accepted.']);
        }

        $quote->forceFill(['status' => QuoteStatus::Accepted])->save();

        return back()->with('success', "Quote {$quote->number} accepted.");
    }

    public function decline(Quote $quote): RedirectResponse
    {
        $this->authorize('update', $quote);

        if (! in_array($quote->status, [QuoteStatus::Draft, QuoteStatus::Sent], true)) {
            throw ValidationException::withMessages(['quote' => 'Only open quotes can be declined.']);
        }

        $quote->forceFill(['status' => QuoteStatus::Declined])->save();

        return back()->with('success', "Quote {$quote->number} declined.");
    }

    /**
     * Convert an accepted (or open) quote into a draft invoice carrying the
     * quote's client, amounts, and line items.
     */
    public function convert(Request $request, Quote $quote): RedirectResponse
    {
        $this->authorize('update', $quote);

        if ($quote->converted_invoice_id !== null) {
            throw ValidationException::withMessages(['quote' => 'This quote has already been converted.']);
        }

        if ($quote->status === QuoteStatus::Declined) {
            throw ValidationException::withMessages(['quote' => 'A declined quote cannot be converted.']);
        }

        $quote->load(['client', 'lineItems']);

        $invoice = DB::transaction(function () use ($request, $quote) {
            $invoice = new Invoice([
                'issue_date' => now()->toDateString(),
                'due_date' => $quote->expiry_date?->isFuture() ? $quote->expiry_date->toDateString() : null,
                'status' => InvoiceStatus::Draft,
                'currency' => $quote->currency,
                'amount' => $quote->amount,
                'vat_amount' => $quote->vat_amount,
                'tax_rate_id' => $quote->tax_rate_id,
                'payer_memo' => $quote->payer_memo,
                'invoice_type' => 'Service',
            ]);

            $invoice->user()->associate($request->user());
            $invoice->client()->associate($quote->client);
            $invoice->number = Invoice::nextNumberForUser($request->user(), $quote->client->type, $invoice->issue_date);
            $invoice->save();

            foreach ($quote->lineItems as $item) {
                $invoice->lineItems()->create([
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'sort_order' => $item->sort_order,
                ]);
            }

            $quote->forceFill([
                'status' => QuoteStatus::Accepted,
                'converted_invoice_id' => $invoice->id,
            ])->save();

            return $invoice;
        });

        return redirect()
            ->route('invoices.edit', $invoice)
            ->with('success', "Quote {$quote->number} converted to draft invoice {$invoice->number}.");
    }

    public function pdf(Quote $quote)
    {
        $this->authorize('view', $quote);

        $quote->load(['client', 'lineItems', 'user']);
        $issuer = $quote->user;

        return Pdf::view('pdfs.quote', [
            'quote' => $quote,
            'issuer' => $issuer,
            'issuerLogoUri' => PdfAsset::dataUriFromPublicPath($issuer?->logo_path),
        ])
            ->format('a4')
            ->name($quote->number.'.pdf');
    }

    private function clientsFor(Request $request)
    {
        return Client::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('name')
            ->get(['id', 'name', 'type']);
    }

    private function taxRatesFor(Request $request)
    {
        return $request->user()->taxRates()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get(['id', 'uuid', 'name', 'rate', 'is_default']);
    }
}
