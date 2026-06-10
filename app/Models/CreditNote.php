<?php

namespace App\Models;

use App\Enums\CreditNoteStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'user_id',
    'client_id',
    'invoice_id',
    'number',
    'status',
    'issue_date',
    'currency',
    'amount',
    'memo',
    'applied_at',
])]
class CreditNote extends Model
{
    use HasUuids;

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected function casts(): array
    {
        return [
            'status' => CreditNoteStatus::class,
            'issue_date' => 'date',
            'amount' => 'decimal:2',
            'applied_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * The bridge payment created when this credit was applied to an invoice.
     */
    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }
}
