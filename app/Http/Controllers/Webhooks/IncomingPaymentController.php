<?php

namespace App\Http\Controllers\Webhooks;

use App\Enums\PaymentSource;
use App\Http\Controllers\Controller;
use App\Models\Integration;
use App\Services\PaymentReconciliationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives a payment event from an external integration and feeds it straight
 * into Phase 2 auto-reconciliation. Signature is already verified upstream by
 * the webhook.signature middleware; idempotency (external_id) is handled by the
 * reconciler, so safe to retry.
 */
class IncomingPaymentController extends Controller
{
    public function __construct(private readonly PaymentReconciliationService $reconciler) {}

    public function __invoke(Request $request, Integration $integration): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999999.99'],
            'currency' => ['required', 'string', 'size:3'],
            'paid_at' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:255'],
            'external_id' => ['nullable', 'string', 'max:255'],
            'gateway' => ['nullable', 'string', 'max:100'],
            'payer_name' => ['nullable', 'string', 'max:255'],
            'payer_email' => ['nullable', 'email', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ]);

        $payment = $this->reconciler->ingest($integration->user, [
            ...$data,
            'currency' => strtoupper($data['currency']),
            'gateway' => $data['gateway'] ?? $integration->provider,
            'source' => PaymentSource::Webhook,
        ]);

        $integration->forceFill(['last_event_at' => now()])->save();

        return response()->json([
            'payment_id' => $payment->uuid,
            'match_status' => $payment->match_status?->value,
            'invoice_id' => $payment->invoice?->uuid,
        ], 202);
    }
}
