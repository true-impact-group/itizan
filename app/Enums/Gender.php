<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum Gender: string
{
    use HasLabel;

    case Male = 'male';
    case Female = 'female';

    public static function labels(): array
    {
        return [
            'male' => 'ذكر',
            'female' => 'أنثى',
        ];
    }
}
