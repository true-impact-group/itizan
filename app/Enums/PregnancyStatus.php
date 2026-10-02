<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum PregnancyStatus: string
{
    use HasLabel;

    case NotApplicable = 'not_applicable';
    case NotPregnant = 'not_pregnant';
    case Pregnant = 'pregnant';

    public static function labels(): array
    {
        return [
            'not_applicable' => 'لا ينطبق',
            'not_pregnant' => 'لست حاملًا',
            'pregnant' => 'حامل',
        ];
    }
}
