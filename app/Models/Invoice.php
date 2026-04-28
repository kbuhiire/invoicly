<?php

namespace App\Models;

use App\Enums\ClientType;
use App\Enums\InvoiceStatus;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
#[Fillable([
    'user_id',
    'client_id',
    'number',
    'issue_date',
    'due_date',
    'period_from',
    'period_to',
    'status',
    'currency',
    'amount',
    'vat_amount',
    'amount_secondary',
    'currency_secondary',
    'payer_memo',
    'payment_details',
    'invoice_type',
    'vat_id',
    'tax_id',
    'attachment_path',
    'is_template',
])]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory, HasUuids;

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
            'issue_date' => 'date',
            'due_date' => 'date',
            'period_from' => 'date',
            'period_to' => 'date',
            'status' => InvoiceStatus::class,
            'amount' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'amount_secondary' => 'decimal:2',
            'is_template' => 'boolean',
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
        return $this->hasMany(InvoiceLineItem::class)->orderBy('sort_order');
    }

    public function recurringSchedules(): HasMany
    {
        return $this->hasMany(RecurringInvoice::class, 'template_invoice_id');
    }

    public static function nextNumberForUser(User $user, ClientType $clientType, ?\DateTimeInterface $issueDate = null): string
    {
        $year = ($issueDate ?? now())->format('Y');
        $prefix = $clientType === ClientType::External ? 'EINV' : 'DINV';
        $like = "{$prefix}-{$year}-%";

        $numbers = static::query()
            ->where('user_id', $user->id)
            ->where('number', 'like', $like)
            ->lockForUpdate()
            ->pluck('number');

        $max = 0;
        foreach ($numbers as $number) {
            if (preg_match('/-(\d+)$/', (string) $number, $matches)) {
                $max = max($max, (int) $matches[1]);
            }
        }

        return sprintf('%s-%s-%d', $prefix, $year, $max + 1);
    }
}
