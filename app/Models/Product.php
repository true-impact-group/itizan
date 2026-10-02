<?php

namespace App\Models;

use App\Enums\ProductType;
use App\Models\Concerns\HasLocalizedAttributes;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Unguarded]
class Product extends Model
{
    use HasFactory, HasLocalizedAttributes, SoftDeletes;

    protected function casts(): array
    {
        return [
            'type' => ProductType::class,
            'requires_prescription' => 'boolean',
            'in_stock' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function scopeOrderable(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function name(): string
    {
        return (string) $this->localized('name');
    }
}
