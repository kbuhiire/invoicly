<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMatchStatus;
use App\Enums\PaymentSource;
use App\Events\PaymentReceived;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Single source of truth for turning payment records into invoice state.
 *
 * Every other feature (reconciliation, forecasting, credit, reminders) records
 * money through here so an invoice's amount_paid / status / paid_at always
 * reflect the sum of its payments rather than a hand-toggled flag.
 */
class PaymentService
{
    /**
     * Record a payment against an invoice and recompute the invoice's derived
     * payment state. Returns the created Payment.
     *
     * @param  array<string, mixed>  $attributes  amount, paid_at, currency?, reference?,
     *                                            gateway?, external_id?, source?, match_status?, metadata?
     */
    public function recordPayment(Invoice $invoice, array $attributes): Payment
    {
        $this->rejectDraft($invoice);

        $payment = DB::transaction(function () use ($invoice, $attributes) {
            $payment = $invoice->payments()->create([
                'user_id' => $invoice->user_id,
                'amount' => $attributes['amount'],
                'currency' => strtoupper($attributes['currency'] ?? $invoice->currency),
                'paid_at' => $attributes['paid_at'] ?? now(),
                'reference' => $attributes['reference'] ?? null,
                'gateway' => $attributes['gateway'] ?? null,
                'external_id' => $attributes['external_id'] ?? null,
                'source' => $attributes['source'] ?? PaymentSource::Manual,
                'match_status' => $attributes['match_status'] ?? PaymentMatchStatus::Matched,
                'metadata' => $attributes['metadata'] ?? null,
            ]);

            $this->recompute($invoice);

            return $payment;
        });

        // Fired after commit so listeners (e.g. behaviour recompute) see final state.
        event(new PaymentReceived($payment));

        return $payment;
    }

    /**
     * Link an existing (unmatched / under-review) payment to an invoice and
     * recompute that invoice. Used when a human confirms a match from the
     * reconciliation review queue.
     */
    public function attachToInvoice(Payment $payment, Invoice $invoice): void
    {
        $this->rejectDraft($invoice);

        DB::transaction(function () use ($payment, $invoice) {
            $payment->forceFill([
                'invoice_id' => $invoice->id,
                'match_status' => PaymentMatchStatus::Matched,
            ])->save();

            $this->recompute($invoice);
        });

        event(new PaymentReceived($payment));
    }

    /**
     * Remove a payment and recompute its (still-linked) invoice.
     */
    public function deletePayment(Payment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $invoice = $payment->invoice;
            $payment->delete();

            if ($invoice !== null) {
                $this->recompute($invoice->refresh());
            }
        });
    }

    /**
     * Recompute amount_paid / status / paid_at from the invoice's payments.
     *
     * Status is derived: fully covered => Paid, some money in => PartiallyPaid,
     * nothing => AwaitingPayment. Overdue is intentionally NOT stored (it's
     * derived from due_date at read time), so it never collides with this.
     */
    public function recompute(Invoice $invoice): void
    {
        // A draft has no payments by construction; never let payment math
        // flip it out of draft.
        if ($invoice->status === InvoiceStatus::Draft) {
            return;
        }

        $paid = (string) $invoice->payments()->sum('amount');
        $paid = bcadd($paid, '0', 2);

        $total = bcadd((string) $invoice->amount, '0', 2);

        if (bccomp($paid, '0', 2) <= 0) {
            $status = InvoiceStatus::AwaitingPayment;
            $paidAt = null;
        } elseif (bccomp($paid, $total, 2) >= 0) {
            $status = InvoiceStatus::Paid;
            $paidAt = $invoice->payments()->max('paid_at');
        } else {
            $status = InvoiceStatus::PartiallyPaid;
            $paidAt = null;
        }

        $invoice->forceFill([
            'amount_paid' => $paid,
            'status' => $status,
            'paid_at' => $paidAt,
        ])->save();
    }

    private function rejectDraft(Invoice $invoice): void
    {
        if ($invoice->status === InvoiceStatus::Draft) {
            throw ValidationException::withMessages([
                'invoice' => 'Payments cannot be recorded against draft invoices. Finalize the invoice first.',
            ]);
        }
    }
}
