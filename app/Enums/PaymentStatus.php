<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum PaymentStatus: string
{
    use HasLabel;

    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Refunded = 'refunded';

    public static function labels(): array
    {
        return [
            'pending' => 'قيد المعالجة',
            'paid' => 'مدفوع',
            'failed' => 'فشل',
            'refunded' => 'مسترد',
        ];
    }

    /** Badge tone; status is always also shown as text, never by colour alone. */
    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Paid => 'success',
            self::Failed => 'danger',
            self::Refunded => 'neutral',
        };
    }
}
