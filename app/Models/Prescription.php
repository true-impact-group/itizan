<?php

namespace App\Models;

use App\Enums\PrescriptionStatus;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Only a doctor creates or changes a prescription; nothing here is ever adjusted automatically. */
#[Unguarded]
class Prescription extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => PrescriptionStatus::class,
            'medication_name' => 'encrypted',
            'dose' => 'encrypted',
            'instructions' => 'encrypted',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'next_dose_at' => 'datetime',
            'reminder_sent_for' => 'datetime',
            'stopped_at' => 'datetime',
            'lapse_notified_at' => 'datetime',
            'pre_dose_questions' => 'array',
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

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function doseLogs(): HasMany
    {
        return $this->hasMany(DoseLog::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', PrescriptionStatus::Active->value);
    }

    /** Days after a subscription ends before its active prescriptions are sent to the doctor for review. */
    public const LAPSE_GRACE_DAYS = 7;

    /**
     * Active prescriptions whose client has no current subscription and whose last subscription ended more than
     * the grace period ago. Nothing is stopped automatically: stopping treatment is the doctor's decision.
     */
    public function scopeLapsed(Builder $query): Builder
    {
        $lastEnd = "(select max(s.ends_at) from subscriptions s where s.user_id = prescriptions.user_id and s.status in ('active', 'expired') and s.deleted_at is null)";

        return $query->active()
            ->whereNotExists(fn ($sub) => $sub->selectRaw('1')->from('subscriptions as c')
                ->whereColumn('c.user_id', 'prescriptions.user_id')
                ->where('c.status', 'active')->where('c.starts_at', '<=', now())->where('c.ends_at', '>', now())
                ->whereNull('c.deleted_at'))
            ->whereRaw("{$lastEnd} < ?", [now()->subDays(self::LAPSE_GRACE_DAYS)]);
    }

    public function isActive(): bool
    {
        return $this->status === PrescriptionStatus::Active;
    }

    public function isDue(): bool
    {
        return $this->isActive() && $this->next_dose_at !== null && $this->next_dose_at->lte(now());
    }

    /** @return list<array{text: string, alert_on: string}> */
    public function preDoseQuestions(): array
    {
        return $this->pre_dose_questions ?? [];
    }
}
