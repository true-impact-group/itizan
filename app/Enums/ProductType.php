<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum ProductType: string
{
    use HasLabel;

    case Medication = 'medication';
    case Supplement = 'supplement';

    public static function labels(): array
    {
        return [
            'medication' => 'دواء',
            'supplement' => 'مكمل غذائي',
        ];
    }
}
