<?php

namespace App\Models;

use App\Enums\ContactRequestStatus;
use App\Enums\ContactTarget;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Unguarded]
class ContactRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'target' => ContactTarget::class,
            'status' => ContactRequestStatus::class,
            'resolved_at' => 'datetime',
            'reason' => 'encrypted',
            'response_note' => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function isUrgent(): bool
    {
        return $this->priority === 'urgent';
    }
}
