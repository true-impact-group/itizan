<?php

namespace App\Models;

use App\Enums\DoseStatus;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Unguarded]
class DoseLog extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => DoseStatus::class,
            'scheduled_for' => 'datetime',
            'logged_at' => 'datetime',
            'pre_dose_answers' => 'encrypted:array',
        ];
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
