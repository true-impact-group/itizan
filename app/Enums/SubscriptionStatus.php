<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum SubscriptionStatus: string
{
    use HasLabel;

    case Pending = 'pending';
    case Active = 'active';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public static function labels(): array
    {
        return [
            'pending' => 'بانتظار الدفع',
            'active' => 'نشط',
            'expired' => 'منتهي',
            'cancelled' => 'ملغى',
        ];
    }

    /** Badge tone; status is always also shown as text, never by colour alone. */
    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Active => 'success',
            self::Expired => 'neutral',
            self::Cancelled => 'neutral',
        };
    }
}
