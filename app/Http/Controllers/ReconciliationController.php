<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMatchStatus;
use App\Events\InvoiceReconciled;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ReconciliationController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    /**
     * The reconciliation queue: payments that need attention (under review or
     * unmatched) plus recently auto-matched payments, and the open invoices a
     * payment can be matched against.
     */
    public function index(Request $request): Response
    {
        $userId = $request->user()->id;

        $needsAttention = Payment::query()
            ->where('user_id', $userId)
            ->whereIn('match_status', [PaymentMatchStatus::Review->value, PaymentMatchStatus::Unmatched->value])
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Payment $p) => $this->transformPayment($p));

        $recentMatched = Payment::query()
            ->where('user_id', $userId)
            ->where('match_status', PaymentMatchStatus::Matched->value)
            ->with('invoice')
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(fn (Payment $p) => $this->transformPayment($p));

        $openInvoices = Invoice::query()
            ->where('user_id', $userId)
            ->where('is_template', false)
            ->where('status', '!=', InvoiceStatus::Paid->value)
            ->whereRaw('amount_paid < amount')
            ->with('client')
            ->orderByDesc('issue_date')
            ->get()
            ->map(fn (Invoice $i) => [
                'id' => $i->id,
                'number' => $i->number,
                'client_name' => $i->client?->name,
                'currency' => $i->currency,
                'outstanding' => $i->outstandingAmount(),
            ]);

        return Inertia::render('Payments/Reconciliation', [
            'needsAttention' => $needsAttention,
            'recentMatched' => $recentMatched,
            'openInvoices' => $openInvoices,
            'currency' => $request->user()->preferred_currency,
        ]);
    }

    /**
     * Confirm a match by linking a payment to an invoice chosen by the user.
     */
    public function match(Request $request, Payment $payment): RedirectResponse
    {
        $this->authorize('update', $payment);

        $data = $request->validate([
            'invoice_id' => [
                'required',
                'integer',
                Rule::exists('invoices', 'id')->where('user_id', $request->user()->id),
            ],
        ]);

        $invoice = Invoice::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($data['invoice_id']);

        if ($payment->currency !== $invoice->currency) {
            return back()->withErrors(['invoice_id' => 'Payment and invoice currencies must match.']);
        }

        $this->payments->attachToInvoice($payment, $invoice);

        event(new InvoiceReconciled($payment->refresh(), $invoice));

        return back()->with('success', 'Payment matched to '.$invoice->number.'.');
    }

    /**
     * Dismiss an unresolved payment (removes it from the queue).
     */
    public function dismiss(Payment $payment): RedirectResponse
    {
        $this->authorize('delete', $payment);

        $this->payments->deletePayment($payment);

        return back()->with('success', 'Payment dismissed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function transformPayment(Payment $payment): array
    {
        return [
            'id' => $payment->id,
            'uuid' => $payment->uuid,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'paid_at' => $payment->paid_at?->format('Y-m-d'),
            'reference' => $payment->reference,
            'gateway' => $payment->gateway,
            'source' => $payment->source?->value,
            'match_status' => $payment->match_status?->value,
            'payer_name' => $payment->metadata['payer_name'] ?? null,
            'payer_email' => $payment->metadata['payer_email'] ?? null,
            'invoice' => $payment->invoice ? [
                'id' => $payment->invoice->id,
                'number' => $payment->invoice->number,
            ] : null,
        ];
    }
}
