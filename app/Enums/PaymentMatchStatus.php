<?php

namespace App\Enums;

/**
 * Reconciliation state of a payment against invoices (Phase 2 consumes this;
 * manual payments are created already Matched).
 */
enum PaymentMatchStatus: string
{
    case Matched = 'matched';     // confidently linked to an invoice
    case Unmatched = 'unmatched'; // landed without a linked invoice
    case Review = 'review';       // ambiguous — needs human confirmation
}
