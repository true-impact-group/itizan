<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum SideEffectStatus: string
{
    use HasLabel;

    case New = 'new';
    case Reviewed = 'reviewed';
    case Escalated = 'escalated';

    public static function labels(): array
    {
        return [
            'new' => 'جديد',
            'reviewed' => 'تمت المراجعة',
            'escalated' => 'تم التصعيد للطبيب',
        ];
    }

    /** Badge tone; status is always also shown as text, never by colour alone. */
    public function tone(): string
    {
        return match ($this) {
            self::New => 'warning',
            self::Reviewed => 'success',
            self::Escalated => 'danger',
        };
    }
}
