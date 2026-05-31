<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentMatchStatus;
use App\Enums\PaymentSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Invoice;
use App\Services\PaymentReconciliationService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentReconciliationService $reconciler,
        private readonly PaymentService $payments,
    ) {}

    /**
     * List the authenticated user's payments (optionally filtered by
     * reconciliation state or invoice).
     */
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->tokenCan('payments:read'), 403, 'Token missing payments:read ability.');

        $query = $request->user()->payments()->with('invoice');

        if ($request->filled('match_status')) {
            $query->where('match_status', $request->string('match_status')->toString());
        }

        if ($request->filled('invoice_id')) {
            $query->where('invoice_id', $request->integer('invoice_id'));
        }

        $payments = $query
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return PaymentResource::collection($payments)->response();
    }

    /**
     * Ingest an inbound payment and run auto-reconciliation against open
     * invoices. Returns the (possibly auto-matched) payment.
     */
    public function store(StorePaymentRequest $request): JsonResponse
    {
        abort_unless($request->user()->tokenCan('payments:write'), 403, 'Token missing payments:write ability.');

        $payment = $this->reconciler->ingest($request->user(), [
            ...$request->validated(),
            'source' => PaymentSource::Api,
        ]);

        $payment->load('invoice');

        return (new PaymentResource($payment))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Record a payment explicitly against a known invoice (no matching needed).
     */
    public function storeForInvoice(StorePaymentRequest $request, Invoice $invoice): JsonResponse
    {
        Gate::authorize('update', $invoice);

        abort_unless($request->user()->tokenCan('payments:write'), 403, 'Token missing payments:write ability.');

        // Honour idempotency even on the explicit endpoint.
        if ($request->filled('external_id')) {
            $existing = $request->user()->payments()
                ->where('external_id', $request->string('external_id')->toString())
                ->first();

            if ($existing !== null) {
                return (new PaymentResource($existing->load('invoice')))->response();
            }
        }

        $data = $request->validated();

        $payment = $this->payments->recordPayment($invoice, [
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? $invoice->currency,
            'paid_at' => $data['paid_at'] ?? now(),
            'reference' => $data['reference'] ?? null,
            'gateway' => $data['gateway'] ?? null,
            'external_id' => $data['external_id'] ?? null,
            'source' => PaymentSource::Api,
            'match_status' => PaymentMatchStatus::Matched,
            'metadata' => $data['metadata'] ?? null,
        ]);

        $payment->load('invoice');

        return (new PaymentResource($payment))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Payments recorded against a specific invoice.
     */
    public function forInvoice(Request $request, Invoice $invoice): JsonResponse
    {
        Gate::authorize('view', $invoice);

        abort_unless($request->user()->tokenCan('payments:read'), 403, 'Token missing payments:read ability.');

        return PaymentResource::collection($invoice->payments)->response();
    }
}
