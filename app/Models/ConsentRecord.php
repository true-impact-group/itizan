<?php

namespace App\Models;

use App\Enums\ConsentType;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Append-only evidence of what a client agreed to and when. */
#[Unguarded]
class ConsentRecord extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'type' => ConsentType::class,
            'granted' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
