<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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
     * Remove a recorded payment; the linked invoice's status is recomputed.
     */
    public function destroy(Payment $payment): RedirectResponse
    {
        $this->authorize('delete', $payment);

        $this->payments->deletePayment($payment);

        return back()->with('success', 'Payment removed.');
    }
}
