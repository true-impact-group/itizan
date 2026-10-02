<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum AppointmentMode: string
{
    use HasLabel;

    case Video = 'video';
    case Audio = 'audio';
    case Chat = 'chat';

    public static function labels(): array
    {
        return [
            'video' => 'فيديو',
            'audio' => 'صوت',
            'chat' => 'محادثة',
        ];
    }
}
