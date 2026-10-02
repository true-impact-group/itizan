<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum TicketStatus: string
{
    use HasLabel;

    case Open = 'open';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public static function labels(): array
    {
        return [
            'open' => 'مفتوحة',
            'in_progress' => 'قيد المعالجة',
            'resolved' => 'تم الحل',
            'closed' => 'مغلقة',
        ];
    }

    /** Badge tone; status is always also shown as text, never by colour alone. */
    public function tone(): string
    {
        return match ($this) {
            self::Open => 'warning',
            self::InProgress => 'info',
            self::Resolved => 'success',
            self::Closed => 'neutral',
        };
    }
}
