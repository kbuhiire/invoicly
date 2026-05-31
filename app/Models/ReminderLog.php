<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit trail + dedupe ledger for smart reminders. One row per invoice per
 * reminder stage (see the unique(invoice_id, dedupe_key) index); the service
 * upserts on it so a failed/skipped attempt can be retried while a "sent" row
 * stops the same stage from going out twice.
 */
#[Fillable([
    'user_id',
    'invoice_id',
    'client_id',
    'type',
    'dedupe_key',
    'channel',
    'status',
    'provider_message_sid',
    'error',
    'sent_at',
])]
class ReminderLog extends Model
{
    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
