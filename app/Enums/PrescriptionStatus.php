<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum PrescriptionStatus: string
{
    use HasLabel;

    case Active = 'active';
    case Stopped = 'stopped';
    case Completed = 'completed';

    public static function labels(): array
    {
        return [
            'active' => 'فعّالة',
            'stopped' => 'موقوفة',
            'completed' => 'مكتملة',
        ];
    }

    /** Badge tone; status is always also shown as text, never by colour alone. */
    public function tone(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Stopped => 'danger',
            self::Completed => 'neutral',
        };
    }
}
