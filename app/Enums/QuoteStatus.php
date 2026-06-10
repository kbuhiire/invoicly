<?php

namespace App\Enums;

use Illuminate\Support\Carbon;

/**
 * Stored quote states. "Expired" is intentionally NOT stored — like invoice
 * Overdue, it is derived at read time (sent + past expiry_date) so no job has
 * to keep it fresh.
 */
enum QuoteStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Declined = 'declined';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Sent => 'Sent',
            self::Accepted => 'Accepted',
            self::Declined => 'Declined',
        };
    }

    /**
     * The status to display, deriving "expired" for sent quotes whose expiry
     * date has passed.
     */
    public function effective(?Carbon $expiryDate, ?Carbon $today = null): string
    {
        $today = $today ?? Carbon::now()->startOfDay();

        if ($this === self::Sent && $expiryDate !== null && $expiryDate->lt($today)) {
            return 'expired';
        }

        return $this->value;
    }
}
