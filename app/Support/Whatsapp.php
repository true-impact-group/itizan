<?php

namespace App\Support;

final class Whatsapp
{
    /** wa.me link that opens the chat with the message pre-filled; the client presses "send". */
    public static function url(?string $message = null): string
    {
        $number = preg_replace('/\D+/', '', (string) config('itizan.whatsapp.number')) ?? '';
        $number = ltrim($number, '0');
        $text = $message ?? config('itizan.whatsapp.message');

        return 'https://wa.me/'.$number.'?text='.rawurlencode($text);
    }
}
