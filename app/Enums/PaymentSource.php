<?php

namespace App\Enums;

/**
 * How a payment record entered the system.
 */
enum PaymentSource: string
{
    case Manual = 'manual';          // recorded by hand in the UI
    case AutoMatched = 'auto_matched'; // matched to an invoice by the reconciler
    case Api = 'api';                // pushed via the public API
    case Webhook = 'webhook';        // received from a gateway/bank webhook
    case CreditNote = 'credit_note'; // bridge payment from an applied credit note
}
