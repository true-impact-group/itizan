<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum AssessmentStatus: string
{
    use HasLabel;

    case Draft = 'draft';
    case Submitted = 'submitted';
    case NeedsInfo = 'needs_info';
    case Approved = 'approved';
    case NotSuitable = 'not_suitable';

    public static function labels(): array
    {
        return [
            'draft' => 'مسودة',
            'submitted' => 'بانتظار مراجعة الطبيب',
            'needs_info' => 'نحتاج معلومات إضافية',
            'approved' => 'البرنامج مناسب',
            'not_suitable' => 'غير مناسب حاليًا',
        ];
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Submitted => 'warning',
            self::NeedsInfo => 'info',
            self::Approved => 'success',
            self::NotSuitable => 'danger',
        };
    }

    /** The client may edit while drafting or when the doctor asked for more information. */
    public function isEditableByClient(): bool
    {
        return in_array($this, [self::Draft, self::NeedsInfo], true);
    }
}
