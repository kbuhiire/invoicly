<?php

namespace App\Events;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a payment is confidently linked to an invoice (auto-matched by the
 * reconciler or confirmed by hand). Phase 4 reminders and Phase 5 webhooks
 * subscribe to this.
 */
class InvoiceReconciled
{
    use Dispatchable, SerializesModels;

    public function __construct(public Payment $payment, public Invoice $invoice) {}
}
