<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Unguarded]
class Subscription extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'payment_method' => PaymentMethod::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'auto_renew' => 'boolean',
            'auto_renew_consented_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'renewal_reminded_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /** Active right now: paid, started and not yet ended. */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('status', SubscriptionStatus::Active->value)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now());
    }

    public function isCurrent(): bool
    {
        return $this->status === SubscriptionStatus::Active
            && $this->starts_at->lte(now())
            && $this->ends_at->gt(now());
    }

    public function remainingFollowups(): int
    {
        return max($this->followups_included - $this->followups_used, 0);
    }

    public function daysLeft(): int
    {
        return max((int) ceil(now()->diffInDays($this->ends_at, false)), 0);
    }
}
