<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum DoseStatus: string
{
    use HasLabel;

    case Taken = 'taken';
    case Missed = 'missed';

    public static function labels(): array
    {
        return [
            'taken' => 'تم أخذ الجرعة',
            'missed' => 'جرعة منسية',
        ];
    }

    /** Badge tone; status is always also shown as text, never by colour alone. */
    public function tone(): string
    {
        return match ($this) {
            self::Taken => 'success',
            self::Missed => 'warning',
        };
    }
}
