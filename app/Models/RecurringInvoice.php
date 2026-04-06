<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'template_invoice_id',
    'name',
    'frequency',
    'day_of_month',
    'next_run_at',
    'last_run_at',
    'is_active',
])]
class RecurringInvoice extends Model
{
    protected function casts(): array
    {
        return [
            'next_run_at' => 'date',
            'last_run_at' => 'date',
            'is_active' => 'boolean',
            'day_of_month' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function templateInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'template_invoice_id');
    }

    /**
     * Calculate the next run date after the given date based on the frequency.
     */
    public function calculateNextRunAt(\DateTimeInterface $from): \Carbon\Carbon
    {
        $date = \Carbon\Carbon::instance($from);

        return match ($this->frequency) {
            'daily' => $date->addDay(),
            'weekly' => $date->addWeek(),
            'biweekly' => $date->addWeeks(2),
            'monthly' => $date->addMonthNoOverflow(),
            'quarterly' => $date->addMonthsNoOverflow(3),
            'annually' => $date->addYear(),
            default => $date->addMonthNoOverflow(),
        };
    }
}
