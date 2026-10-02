<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum AppointmentStatus: string
{
    use HasLabel;

    case Requested = 'requested';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    public static function labels(): array
    {
        return [
            'requested' => 'بانتظار التأكيد',
            'confirmed' => 'مؤكد',
            'completed' => 'مكتمل',
            'cancelled' => 'ملغى',
            'no_show' => 'لم يحضر',
        ];
    }

    /** Statuses that still occupy a slot. */
    public static function active(): array
    {
        return [self::Requested->value, self::Confirmed->value];
    }

    public function isActive(): bool
    {
        return in_array($this->value, self::active(), true);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Requested => 'warning',
            self::Confirmed => 'info',
            self::Completed => 'success',
            self::Cancelled, self::NoShow => 'neutral',
        };
    }
}
