<?php

namespace App\Services\Webhooks;

use App\Models\Invoice;
use App\Models\Payment;

/**
 * Builds the public-facing `data` body for outbound webhook events. Uses uuids
 * (never internal ids) so payloads match the public API surface.
 */
class WebhookPayload
{
    /**
     * @return array<string, mixed>
     */
    public static function forPayment(Payment $payment): array
    {
        return [
            'id' => $payment->uuid,
            'amount' => (float) $payment->amount,
            'currency' => $payment->currency,
            'paid_at' => $payment->paid_at?->toIso8601String(),
            'reference' => $payment->reference,
            'source' => $payment->source?->value,
            'match_status' => $payment->match_status?->value,
            'invoice' => $payment->invoice ? self::invoiceSummary($payment->invoice) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function forReconciliation(Payment $payment, Invoice $invoice): array
    {
        return [
            'payment' => self::forPayment($payment),
            'invoice' => self::invoiceSummary($invoice),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function invoiceSummary(Invoice $invoice): array
    {
        return [
            'id' => $invoice->uuid,
            'number' => $invoice->number,
            'status' => $invoice->status?->value,
            'currency' => $invoice->currency,
            'amount' => (float) $invoice->amount,
            'amount_paid' => (float) $invoice->amount_paid,
            'outstanding' => (float) $invoice->outstandingAmount(),
        ];
    }
}
