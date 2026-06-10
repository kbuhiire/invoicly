<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'document_type',
    'prefix',
    'next_number',
    'padding',
    'include_year',
])]
class DocumentSequence extends Model
{
    protected function casts(): array
    {
        return [
            'next_number' => 'integer',
            'padding' => 'integer',
            'include_year' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
