<?php

namespace App\Models;

use App\Enums\AppointmentMode;
use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

#[Unguarded]
class Appointment extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'type' => AppointmentType::class,
            'mode' => AppointmentMode::class,
            'status' => AppointmentStatus::class,
            'scheduled_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
            'closure_reminded_at' => 'datetime',
            'is_instant' => 'boolean',
            'client_notes' => 'encrypted',
            'practitioner_notes' => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function practitioner(): BelongsTo
    {
        return $this->belongsTo(Practitioner::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->whereIn('status', AppointmentStatus::active())
            ->where('scheduled_at', '>=', now()->subMinutes(30));
    }

    /** Confirmed sessions that finished more than 15 minutes ago and were never marked completed / no-show. */
    public function scopeAwaitingClosure(Builder $query): Builder
    {
        return $query->where('status', AppointmentStatus::Confirmed->value)
            ->whereRaw("scheduled_at + (duration_minutes * interval '1 minute') < ?", [now()->subMinutes(15)]);
    }

    public function endsAt(): Carbon
    {
        return $this->scheduled_at->copy()->addMinutes($this->duration_minutes);
    }

    /** The client can open the session from 10 minutes before the start until it ends. */
    public function isJoinable(): bool
    {
        return $this->status === AppointmentStatus::Confirmed
            && $this->meeting_url !== null
            && now()->between($this->scheduled_at->copy()->subMinutes(10), $this->endsAt());
    }

    public function isCancellable(): bool
    {
        if (! $this->status->isActive()) {
            return false;
        }

        return $this->is_instant
            || $this->scheduled_at->gt(now()->addHours((int) config('itizan.booking.cancel_before_hours')));
    }
}
