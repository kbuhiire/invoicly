<?php

namespace App\Events;

use App\Models\Payment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired whenever a payment lands — matched, under review, or unmatched.
 * Phase 5 webhook fan-out subscribes to this.
 */
class PaymentReceived
{
    use Dispatchable, SerializesModels;

    public function __construct(public Payment $payment) {}
}
