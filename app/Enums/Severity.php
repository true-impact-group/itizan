<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum Severity: string
{
    use HasLabel;

    case Mild = 'mild';
    case Moderate = 'moderate';
    case Severe = 'severe';

    public static function labels(): array
    {
        return [
            'mild' => 'خفيفة',
            'moderate' => 'متوسطة',
            'severe' => 'شديدة',
        ];
    }

    /** Badge tone; status is always also shown as text, never by colour alone. */
    public function tone(): string
    {
        return match ($this) {
            self::Mild => 'info',
            self::Moderate => 'warning',
            self::Severe => 'danger',
        };
    }
}
