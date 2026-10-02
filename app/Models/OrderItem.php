<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Unguarded]
class OrderItem extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return ['is_available' => 'boolean'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function lineTotalHalalas(): int
    {
        return $this->is_available ? $this->quantity * $this->unit_price_halalas : 0;
    }
}
