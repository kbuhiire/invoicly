<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * An inbound webhook endpoint for one user. External systems POST payment events
 * to /webhooks/incoming/{uuid}/payments; the signing_secret verifies the HMAC.
 */
#[Fillable([
    'user_id',
    'provider',
    'name',
    'signing_secret',
    'active',
])]
#[Hidden(['signing_secret'])]
class Integration extends Model
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
            'active' => 'boolean',
            'last_event_at' => 'datetime',
        ];
    }

    public static function generateSecret(): string
    {
        return 'whsec_'.Str::random(48);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
