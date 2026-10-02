<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum PlanStatus: string
{
    use HasLabel;

    case Active = 'active';
    case Paused = 'paused';
    case Completed = 'completed';

    public static function labels(): array
    {
        return [
            'active' => 'نشطة',
            'paused' => 'متوقفة مؤقتًا',
            'completed' => 'مكتملة',
        ];
    }

    /** Badge tone; status is always also shown as text, never by colour alone. */
    public function tone(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Paused => 'warning',
            self::Completed => 'neutral',
        };
    }
}
