<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedAttributes;
use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Unguarded]
class Package extends Model
{
    use HasFactory, HasLocalizedAttributes, SoftDeletes;

    protected function casts(): array
    {
        return [
            'vat_inclusive' => 'boolean',
            'is_active' => 'boolean',
            'features_ar' => 'array',
            'features_en' => 'array',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function name(): string
    {
        return (string) $this->localized('name');
    }

    public function description(): ?string
    {
        return $this->localized('description');
    }

    /** @return list<string> */
    public function features(): array
    {
        $locale = app()->getLocale() === 'en' ? 'en' : 'ar';

        return $this->{"features_{$locale}"} ?: ($this->features_ar ?? []);
    }

    public function monthlyAverageHalalas(): int
    {
        return Money::perMonth($this->price_halalas, $this->duration_months);
    }

    /** Approximate amount of one payment when split into equal instalments. */
    public function installmentHalalas(?int $parts = null): int
    {
        $parts ??= (int) config('itizan.payments.installments', 4);

        return (int) round($this->price_halalas / $parts);
    }

    /** VAT contained in (or added to) the price, depending on how the price is defined. */
    public function vatHalalas(): int
    {
        return $this->vat_inclusive
            ? Money::vatContained($this->price_halalas)
            : Money::vatOn($this->price_halalas);
    }

    /** What the client is actually charged. */
    public function chargeHalalas(): int
    {
        return $this->vat_inclusive ? $this->price_halalas : $this->price_halalas + $this->vatHalalas();
    }
}
