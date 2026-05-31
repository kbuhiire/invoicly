<?php

namespace App\Models;

use App\Enums\ClientType;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Fillable([
    'user_id',
    'name',
    'type',
    'is_business',
    'first_name',
    'last_name',
    'business_name',
    'country',
    'street',
    'city',
    'postal_code',
    'email',
    'phone',
    'preferred_contact_method',
    'whatsapp_opt_in',
    'vat_number',
])]
class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
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
            'type' => ClientType::class,
            'is_business' => 'boolean',
            'whatsapp_opt_in' => 'boolean',
            'paid_invoice_count' => 'integer',
            'avg_days_to_pay' => 'integer',
            'on_time_rate' => 'integer',
            'late_count' => 'integer',
            'credit_score' => 'integer',
            'flagged_for_review' => 'boolean',
            'behavior_recomputed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * All payments received against this client's invoices — the basis for
     * payment-behaviour aggregates (forecasting, credit scoring).
     */
    public function payments(): HasManyThrough
    {
        return $this->hasManyThrough(Payment::class, Invoice::class);
    }
}
