<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMatchStatus;
use App\Enums\PaymentSource;
use App\Events\InvoiceReconciled;
use App\Events\PaymentReceived;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Rule-based, tiered payment-to-invoice matcher (no ML).
 *
 * An inbound payment (API push or gateway webhook) is matched against the
 * user's still-open invoices in descending confidence:
 *   Tier 1 — the invoice number appears in the payment reference  → auto-match
 *   Tier 2 — exact amount+currency on exactly one open invoice    → auto-match
 *   Tier 3 — a weaker signal (amount on several, or a known payer) → review
 *   else   — nothing plausible                                    → unmatched
 *
 * Auto-matches are applied immediately; everything else is persisted with a
 * null invoice for a human to resolve in the reconciliation queue. Matching on
 * amount alone is deliberately NOT auto-applied when it is ambiguous.
 */
class PaymentReconciliationService
{
    /** Invoice number format produced by Invoice::nextNumberForUser(). */
    private const INVOICE_NUMBER_PATTERN = '/\b([ED]INV-\d{4}-\d+)\b/i';

    public function __construct(private readonly PaymentService $payments) {}

    /**
     * Ingest an inbound payment, attempt reconciliation, and persist the result.
     *
     * @param  array<string, mixed>  $data  amount, currency, paid_at?, reference?,
     *                                      gateway?, external_id?, payer_name?,
     *                                      payer_email?, metadata?, source?
     */
    public function ingest(User $user, array $data): Payment
    {
        // Idempotency: a gateway that retries the same external_id must not
        // double-record. Return the payment already on file.
        if (! empty($data['external_id'])) {
            $existing = $user->payments()
                ->where('external_id', $data['external_id'])
                ->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        $source = $data['source'] ?? PaymentSource::Webhook;
        [$invoice, $status] = $this->findMatch($user, $data);

        if ($invoice !== null && $status === PaymentMatchStatus::Matched) {
            $payment = $this->payments->recordPayment($invoice, [
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? $invoice->currency,
                'paid_at' => $data['paid_at'] ?? now(),
                'reference' => $data['reference'] ?? null,
                'gateway' => $data['gateway'] ?? null,
                'external_id' => $data['external_id'] ?? null,
                'source' => $source instanceof PaymentSource ? $source : PaymentSource::from($source),
                'match_status' => PaymentMatchStatus::Matched,
                'metadata' => $this->buildMetadata($data),
            ]);

            // recordPayment() already dispatched PaymentReceived; only the
            // reconciliation signal is new here. Re-dispatching PaymentReceived
            // would double-deliver the payment.received webhook.
            event(new InvoiceReconciled($payment, $invoice));

            return $payment;
        }

        // No confident match — land it for the review queue.
        $payment = $user->payments()->create([
            'invoice_id' => null,
            'amount' => $data['amount'],
            'currency' => strtoupper((string) ($data['currency'] ?? $user->preferred_currency ?? 'USD')),
            'paid_at' => $data['paid_at'] ?? now(),
            'reference' => $data['reference'] ?? null,
            'gateway' => $data['gateway'] ?? null,
            'external_id' => $data['external_id'] ?? null,
            'source' => $source instanceof PaymentSource ? $source : PaymentSource::from($source),
            'match_status' => $status,
            'metadata' => $this->buildMetadata($data),
        ]);

        event(new PaymentReceived($payment));

        return $payment;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: Invoice|null, 1: PaymentMatchStatus}
     */
    private function findMatch(User $user, array $data): array
    {
        $open = $this->openInvoices($user);

        if ($open->isEmpty()) {
            return [null, PaymentMatchStatus::Unmatched];
        }

        $currency = strtoupper((string) ($data['currency'] ?? ''));
        $amount = (string) $data['amount'];
        $reference = (string) ($data['reference'] ?? '');
        $softSignal = false;

        // Tier 1 — explicit invoice number in the reference.
        if ($reference !== '' && preg_match_all(self::INVOICE_NUMBER_PATTERN, $reference, $matches)) {
            foreach (array_unique(array_map('strtoupper', $matches[1])) as $number) {
                $invoice = $open->firstWhere('number', $number);
                if ($invoice === null) {
                    continue;
                }
                // Strong signal, but never mix currencies on one invoice.
                if ($currency !== '' && $invoice->currency !== $currency) {
                    $softSignal = true;

                    continue;
                }

                return [$invoice, PaymentMatchStatus::Matched];
            }
        }

        // Tier 2 — exact outstanding amount + currency on a single open invoice.
        $exact = $open->filter(
            fn (Invoice $i) => $i->currency === $currency
                && bccomp($i->outstandingAmount(), $amount, 2) === 0
        )->values();

        if ($exact->count() === 1) {
            return [$exact->first(), PaymentMatchStatus::Matched];
        }

        if ($exact->count() > 1) {
            // Amount fits several invoices — ambiguous, needs a human.
            return [null, PaymentMatchStatus::Review];
        }

        // Tier 3 — weaker signals route to review rather than auto-applying.
        if ($softSignal || $this->payerMatchesKnownClient($user, $data)) {
            return [null, PaymentMatchStatus::Review];
        }

        return [null, PaymentMatchStatus::Unmatched];
    }

    /**
     * @return Collection<int, Invoice>
     */
    private function openInvoices(User $user): Collection
    {
        return Invoice::query()
            ->where('user_id', $user->id)
            ->where('is_template', false)
            ->where('status', '!=', InvoiceStatus::Paid->value)
            ->whereRaw('amount_paid < amount')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function payerMatchesKnownClient(User $user, array $data): bool
    {
        $email = isset($data['payer_email']) ? trim((string) $data['payer_email']) : '';
        $name = isset($data['payer_name']) ? trim((string) $data['payer_name']) : '';

        if ($email === '' && $name === '') {
            return false;
        }

        return $user->clients()
            ->when($email !== '', fn ($q) => $q->where('email', $email))
            ->when($email === '' && $name !== '', fn ($q) => $q->where('name', $name))
            ->exists();
    }

    /**
     * Preserve the raw payer hints alongside any caller-supplied metadata so the
     * review queue can show who appeared to pay.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    private function buildMetadata(array $data): ?array
    {
        $meta = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];

        foreach (['payer_name', 'payer_email'] as $key) {
            if (! empty($data[$key])) {
                $meta[$key] = $data[$key];
            }
        }

        return $meta === [] ? null : $meta;
    }
}
