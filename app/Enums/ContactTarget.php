<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum ContactTarget: string
{
    use HasLabel;

    case Doctor = 'doctor';
    case CareTeam = 'care_team';

    public static function labels(): array
    {
        return [
            'doctor' => 'الطبيب',
            'care_team' => 'فريق الرعاية',
        ];
    }
}
