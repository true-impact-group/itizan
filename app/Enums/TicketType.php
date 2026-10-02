<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum TicketType: string
{
    use HasLabel;

    case Support = 'support';
    case Complaint = 'complaint';

    public static function labels(): array
    {
        return [
            'support' => 'طلب دعم',
            'complaint' => 'شكوى',
        ];
    }
}
