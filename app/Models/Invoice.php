<?php

namespace App\Models;

use App\Enums\ClientType;
use App\Enums\InvoiceStatus;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
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
    'amount_paid',
    'paid_at',
    'sent_at',
    'last_reminder_sent_at',
    'vat_amount',
    'tax_rate_id',
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
            'paid_at' => 'datetime',
            'sent_at' => 'datetime',
            'last_reminder_sent_at' => 'datetime',
            'status' => InvoiceStatus::class,
            'amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
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

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderByDesc('paid_at');
    }

    /**
     * Everything that is not a draft. Drafts must never reach the money
     * pipelines (dashboard KPIs, forecast, reminders, reconciliation, credit).
     */
    public function scopeFinalized($query)
    {
        return $query->where('status', '!=', InvoiceStatus::Draft->value);
    }

    /**
     * Invoices that can still receive money: finalized, not a template, not
     * fully paid. The single definition of "open" shared by forecasting,
     * reminders, and reconciliation.
     */
    public function scopeOpenForPayment($query)
    {
        return $query
            ->where('status', '!=', InvoiceStatus::Draft->value)
            ->where('is_template', false)
            ->where('status', '!=', InvoiceStatus::Paid->value)
            ->whereRaw('amount_paid < amount');
    }

    /**
     * Outstanding balance = invoice total minus what has been paid.
     */
    public function outstandingAmount(): string
    {
        $outstanding = bcsub((string) $this->amount, (string) ($this->amount_paid ?? '0'), 2);

        return bccomp($outstanding, '0', 2) === 1 ? $outstanding : '0.00';
    }

    public function isFullyPaid(): bool
    {
        return bccomp((string) ($this->amount_paid ?? '0'), (string) $this->amount, 2) >= 0;
    }

    public function recurringSchedules(): HasMany
    {
        return $this->hasMany(RecurringInvoice::class, 'template_invoice_id');
    }

    /**
     * Consume and return the next invoice number for this user/segment.
     */
    public static function nextNumberForUser(User $user, ClientType $clientType, ?\DateTimeInterface $issueDate = null): string
    {
        return app(\App\Services\DocumentNumberService::class)->next(
            $user,
            self::sequenceType($clientType),
            $issueDate
        );
    }

    /**
     * Show the upcoming invoice number without consuming the sequence —
     * for form display only.
     */
    public static function previewNumberForUser(User $user, ClientType $clientType, ?\DateTimeInterface $issueDate = null): string
    {
        return app(\App\Services\DocumentNumberService::class)->preview(
            $user,
            self::sequenceType($clientType),
            $issueDate
        );
    }

    private static function sequenceType(ClientType $clientType): string
    {
        return $clientType === ClientType::External
            ? \App\Services\DocumentNumberService::TYPE_INVOICE_EXTERNAL
            : \App\Services\DocumentNumberService::TYPE_INVOICE_INVOICLY;
    }
}
