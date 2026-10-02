<?php

namespace App\Models;

use App\Enums\Severity;
use App\Enums\SideEffectStatus;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Unguarded]
class SideEffectReport extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'severity' => Severity::class,
            'status' => SideEffectStatus::class,
            'occurred_at' => 'datetime',
            'handled_at' => 'datetime',
            'symptom' => 'encrypted',
            'response_note' => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
