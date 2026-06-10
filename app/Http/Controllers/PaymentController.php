<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    /**
     * Manually record a payment received against an invoice.
     */
    public function store(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('update', $invoice);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999999.99'],
            'paid_at' => ['nullable', 'date', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);

        $this->payments->recordPayment($invoice, [
            'amount' => $validated['amount'],
            'paid_at' => $validated['paid_at'] ?? now(),
            'reference' => $validated['reference'] ?? null,
        ]);

        return back()->with('success', 'Payment recorded.');
    }

    /**
     * Stream all of the user's payments as CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        $query = Payment::query()
            ->where('user_id', $request->user()->id)
            ->with('invoice:id,number');

        $filename = 'payments-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Paid at', 'Invoice', 'Amount', 'Currency', 'Source', 'Match status',
                'Gateway', 'Reference', 'External ID',
            ]);

            foreach ($query->lazyByIdDesc(500) as $payment) {
                fputcsv($out, [
                    $payment->paid_at?->format('Y-m-d H:i'),
                    $payment->invoice?->number,
                    $payment->amount,
                    $payment->currency,
                    $payment->source?->value,
                    $payment->match_status?->value,
                    $payment->gateway,
                    $payment->reference,
                    $payment->external_id,
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Remove a recorded payment; the linked invoice's status is recomputed.
     */
    public function destroy(Payment $payment): RedirectResponse
    {
        $this->authorize('delete', $payment);

        $this->payments->deletePayment($payment);

        return back()->with('success', 'Payment removed.');
    }
}
