<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum ContactRequestStatus: string
{
    use HasLabel;

    case Open = 'open';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';

    public static function labels(): array
    {
        return [
            'open' => 'مفتوح',
            'in_progress' => 'قيد المتابعة',
            'resolved' => 'تم الحل',
        ];
    }

    /** Badge tone; status is always also shown as text, never by colour alone. */
    public function tone(): string
    {
        return match ($this) {
            self::Open => 'warning',
            self::InProgress => 'info',
            self::Resolved => 'success',
        };
    }
}
