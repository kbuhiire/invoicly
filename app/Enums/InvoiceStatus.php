<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Paid = 'paid';
    case PartiallyPaid = 'partially_paid';
    case AwaitingPayment = 'awaiting_payment';

    /**
     * Human-readable label for UI/badges.
     */
    public function label(): string
    {
        return match ($this) {
            self::Paid => 'Paid',
            self::PartiallyPaid => 'Partially paid',
            self::AwaitingPayment => 'Awaiting payment',
        };
    }

    /**
     * Statuses a user may set directly on an invoice form. PartiallyPaid is
     * derived from recorded payments by PaymentService, never chosen by hand.
     *
     * @return list<string>
     */
    public static function manualValues(): array
    {
        return [self::Paid->value, self::AwaitingPayment->value];
    }
}
