<?php

namespace App\Models;

use Database\Factories\InvoiceLineItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['invoice_id', 'description', 'quantity', 'unit_price', 'sort_order'])]
class InvoiceLineItem extends Model
{
    /** @use HasFactory<InvoiceLineItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function lineTotal(): string
    {
        return bcmul((string) $this->quantity, (string) $this->unit_price, 2);
    }
}
