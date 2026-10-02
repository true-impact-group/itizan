<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum PractitionerType: string
{
    use HasLabel;

    case Doctor = 'doctor';
    case Dietitian = 'dietitian';

    public static function labels(): array
    {
        return [
            'doctor' => 'طبيب / استشاري',
            'dietitian' => 'أخصائي تغذية',
        ];
    }
}
