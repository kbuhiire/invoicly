<?php

namespace App\Models;

use App\Enums\QuoteStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'client_id',
    'number',
    'status',
    'issue_date',
    'expiry_date',
    'currency',
    'amount',
    'vat_amount',
    'tax_rate_id',
    'payer_memo',
    'converted_invoice_id',
    'sent_at',
])]
class Quote extends Model
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
            'status' => QuoteStatus::class,
            'issue_date' => 'date',
            'expiry_date' => 'date',
            'amount' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'sent_at' => 'datetime',
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

    public function lineItems(): HasMany
    {
        return $this->hasMany(QuoteLineItem::class)->orderBy('sort_order');
    }

    public function convertedInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'converted_invoice_id');
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }

    public function effectiveStatus(): string
    {
        return $this->status->effective($this->expiry_date);
    }
}
