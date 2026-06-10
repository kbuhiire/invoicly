<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Paid = 'paid';
    case PartiallyPaid = 'partially_paid';
    case AwaitingPayment = 'awaiting_payment';

    /**
     * Human-readable label for UI/badges.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Paid => 'Paid',
            self::PartiallyPaid => 'Partially paid',
            self::AwaitingPayment => 'Awaiting payment',
        };
    }

    public function isDraft(): bool
    {
        return $this === self::Draft;
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

    /**
     * Statuses an invoice may be created with. Draft keeps the invoice out of
     * every money pipeline until it is finalized.
     *
     * @return list<string>
     */
    public static function selectableOnCreate(): array
    {
        return [self::Draft->value, self::AwaitingPayment->value, self::Paid->value];
    }
}
