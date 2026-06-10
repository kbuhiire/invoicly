<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\InvoiceStatus;
use App\Enums\QuoteStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreQuoteRequest;
use App\Http\Resources\QuoteResource;
use App\Models\Invoice;
use App\Models\Quote;
use App\Services\DocumentNumberService;
use App\Support\LineItems;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class QuoteController extends Controller
{
    public function __construct(private readonly DocumentNumberService $numbers) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Quote::class);

        $user = $request->user();

        abort_unless($user->tokenCan('quotes:read'), 403, 'Token missing quotes:read ability.');

        $query = Quote::query()
            ->where('user_id', $user->id)
            ->with(['client', 'lineItems']);

        $statusFilter = $request->string('status')->toString();

        // "expired" filters sent quotes past expiry; stored statuses filter directly.
        if ($statusFilter === 'expired') {
            $query->where('status', QuoteStatus::Sent->value)
                ->whereNotNull('expiry_date')
                ->whereDate('expiry_date', '<', now()->toDateString());
        } elseif (in_array($statusFilter, array_map(fn (QuoteStatus $c) => $c->value, QuoteStatus::cases()), true)) {
            $query->where('status', $statusFilter);
        }

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->integer('client_id'));
        }

        $quotes = $query
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return QuoteResource::collection($quotes)->response();
    }

    public function show(Request $request, Quote $quote): JsonResponse
    {
        Gate::authorize('view', $quote);

        abort_unless($request->user()->tokenCan('quotes:read'), 403, 'Token missing quotes:read ability.');

        $quote->load(['client', 'lineItems']);

        return (new QuoteResource($quote))->response();
    }

    public function store(StoreQuoteRequest $request): JsonResponse
    {
        Gate::authorize('create', Quote::class);

        abort_unless($request->user()->tokenCan('quotes:write'), 403, 'Token missing quotes:write ability.');

        $data = $request->validated();
        $user = $request->user();

        $quote = DB::transaction(function () use ($data, $user) {
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

            return $quote;
        });

        $quote->load(['client', 'lineItems']);

        return (new QuoteResource($quote))
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(Request $request, Quote $quote): JsonResponse
    {
        Gate::authorize('delete', $quote);

        abort_unless($request->user()->tokenCan('quotes:write'), 403, 'Token missing quotes:write ability.');

        $quote->delete();

        return response()->json(['message' => 'Quote deleted.']);
    }

    /**
     * Convert an open quote into a draft invoice carrying the quote's client,
     * amounts, and line items.
     */
    public function convert(Request $request, Quote $quote): JsonResponse
    {
        Gate::authorize('update', $quote);

        abort_unless($request->user()->tokenCan('quotes:write'), 403, 'Token missing quotes:write ability.');

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

        $quote->refresh()->load(['client', 'lineItems']);

        return response()->json([
            'message' => "Quote {$quote->number} converted to draft invoice {$invoice->number}.",
            'invoice' => [
                'id' => $invoice->id,
                'uuid' => $invoice->uuid,
                'number' => $invoice->number,
                'status' => $invoice->status?->value ?? $invoice->status,
            ],
            'quote' => new QuoteResource($quote),
        ], 201);
    }
}
