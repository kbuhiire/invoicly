<?php

namespace App\Services;

use App\Enums\CreditNoteStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentSource;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Issues and settles credit notes. Applying a credit creates a bridge Payment
 * (source=credit_note) through PaymentService so the invoice's amount_paid,
 * status, and paid_at stay derived from one source of truth; voiding deletes
 * that bridge payment and the recompute restores the invoice.
 */
class CreditNoteService
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly DocumentNumberService $numbers,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes  client_id, invoice_id?, currency, amount, issue_date?, memo?
     */
    public function issue(User $user, array $attributes): CreditNote
    {
        return DB::transaction(function () use ($user, $attributes) {
            return CreditNote::query()->create([
                'user_id' => $user->id,
                'client_id' => $attributes['client_id'],
                'invoice_id' => $attributes['invoice_id'] ?? null,
                'number' => $this->numbers->next($user, DocumentNumberService::TYPE_CREDIT_NOTE),
                'status' => CreditNoteStatus::Issued,
                'issue_date' => $attributes['issue_date'] ?? now()->toDateString(),
                'currency' => strtoupper((string) $attributes['currency']),
                'amount' => $attributes['amount'],
                'memo' => $attributes['memo'] ?? null,
            ]);
        });
    }

    public function applyToInvoice(CreditNote $creditNote, Invoice $invoice): void
    {
        if ($creditNote->user_id !== $invoice->user_id) {
            throw ValidationException::withMessages(['invoice' => 'Invoice does not belong to the same account.']);
        }

        if ($creditNote->status !== CreditNoteStatus::Issued) {
            throw ValidationException::withMessages(['credit_note' => 'Only issued credit notes can be applied.']);
        }

        if ($invoice->status === InvoiceStatus::Draft) {
            throw ValidationException::withMessages(['invoice' => 'Credit notes cannot be applied to draft invoices.']);
        }

        if ($creditNote->currency !== $invoice->currency) {
            throw ValidationException::withMessages(['invoice' => 'Credit note and invoice currencies must match.']);
        }

        $outstanding = $invoice->outstandingAmount();
        if (bccomp($creditNote->amount, $outstanding, 2) === 1) {
            throw ValidationException::withMessages([
                'invoice' => "Credit ({$creditNote->currency} {$creditNote->amount}) exceeds the invoice's outstanding balance ({$creditNote->currency} {$outstanding}).",
            ]);
        }

        DB::transaction(function () use ($creditNote, $invoice) {
            $this->payments->recordPayment($invoice, [
                'amount' => $creditNote->amount,
                'currency' => $creditNote->currency,
                'paid_at' => now(),
                'reference' => "Credit note {$creditNote->number}",
                'source' => PaymentSource::CreditNote,
                'credit_note_id' => $creditNote->id,
            ]);

            $creditNote->forceFill([
                'invoice_id' => $invoice->id,
                'status' => CreditNoteStatus::Applied,
                'applied_at' => now(),
            ])->save();
        });
    }

    public function void(CreditNote $creditNote): void
    {
        if ($creditNote->status === CreditNoteStatus::Void) {
            throw ValidationException::withMessages(['credit_note' => 'This credit note is already void.']);
        }

        DB::transaction(function () use ($creditNote) {
            $payment = $creditNote->payment;
            if ($payment !== null) {
                // Deleting the bridge payment recomputes the invoice back.
                $this->payments->deletePayment($payment);
            }

            $creditNote->forceFill([
                'status' => CreditNoteStatus::Void,
                'applied_at' => null,
            ])->save();
        });
    }
}
