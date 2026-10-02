<?php

namespace App\Models;

use App\Enums\PractitionerType;
use App\Models\Concerns\HasLocalizedAttributes;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Unguarded]
class Practitioner extends Model
{
    use HasFactory, HasLocalizedAttributes, SoftDeletes;

    protected function casts(): array
    {
        return [
            'type' => PractitionerType::class,
            'license_verified_at' => 'datetime',
            'license_expires_on' => 'date',
            'accepts_instant' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function availabilities(): HasMany
    {
        return $this->hasMany(PractitionerAvailability::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /** Only practitioners whose licence has been verified (and is not expired) are shown publicly or bookable. */
    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->whereNotNull('license_verified_at')
            ->where(fn (Builder $q) => $q->whereNull('license_expires_on')->orWhere('license_expires_on', '>=', today()));
    }

    public function scopeOfType(Builder $query, PractitionerType $type): Builder
    {
        return $query->where('type', $type->value);
    }

    public function isVerified(): bool
    {
        return $this->is_active
            && $this->license_verified_at !== null
            && ($this->license_expires_on === null || $this->license_expires_on->gte(today()));
    }

    public function displayName(): string
    {
        if (app()->getLocale() === 'en' && $this->name_en) {
            return $this->name_en;
        }

        return $this->user->name;
    }

    public function specialty(): string
    {
        return (string) $this->localized('specialty');
    }

    public function bio(): ?string
    {
        return $this->localized('bio');
    }
}
