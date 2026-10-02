<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum LabStatus: string
{
    use HasLabel;

    case Requested = 'requested';
    case Uploaded = 'uploaded';
    case Reviewed = 'reviewed';

    public static function labels(): array
    {
        return [
            'requested' => 'مطلوب',
            'uploaded' => 'تم رفع النتيجة',
            'reviewed' => 'تمت المراجعة',
        ];
    }

    /** Badge tone; status is always also shown as text, never by colour alone. */
    public function tone(): string
    {
        return match ($this) {
            self::Requested => 'warning',
            self::Uploaded => 'info',
            self::Reviewed => 'success',
        };
    }
}
