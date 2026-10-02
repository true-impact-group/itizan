<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Unguarded]
class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'free_delivery' => 'boolean',
            'eta_at' => 'datetime',
            'quoted_at' => 'datetime',
            'approved_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'delivery_address' => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(Pharmacy::class);
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(OrderEvent::class)->oldest('created_at')->oldest('id');
    }

    /** Adds a timeline entry (shown to the client as an order update). */
    public function recordEvent(OrderStatus $status, ?string $note = null, ?User $actor = null): OrderEvent
    {
        return $this->events()->create([
            'status' => $status,
            'note' => $note,
            'actor_id' => $actor?->id,
        ]);
    }

    public function canBeCancelledByClient(): bool
    {
        return in_array($this->status, [OrderStatus::Requested, OrderStatus::Quoted, OrderStatus::Approved], true);
    }
}
