<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * An outbound webhook destination. When a subscribed event fires for the owning
 * user, a signed JSON payload is POSTed to {url} by the DispatchWebhook job.
 */
#[Fillable([
    'user_id',
    'url',
    'secret',
    'events',
    'active',
])]
#[Hidden(['secret'])]
class WebhookSubscription extends Model
{
    use HasUuids;

    /** Domain events a subscription may listen for. */
    public const EVENTS = [
        'payment.received',
        'invoice.reconciled',
    ];

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
            'events' => 'array',
            'active' => 'boolean',
            'last_dispatched_at' => 'datetime',
        ];
    }

    public static function generateSecret(): string
    {
        return 'whsec_'.Str::random(48);
    }

    public function subscribesTo(string $event): bool
    {
        return in_array($event, $this->events ?? [], true);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
