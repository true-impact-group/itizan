<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum AppointmentType: string
{
    use HasLabel;

    case FreeIntro = 'free_intro';
    case FollowUp = 'follow_up';

    public static function labels(): array
    {
        return [
            'free_intro' => 'استشارة مجانية',
            'follow_up' => 'استشارة متابعة',
        ];
    }
}
