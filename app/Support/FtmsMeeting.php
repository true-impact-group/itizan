<?php

namespace App\Support;

use Illuminate\Support\Str;

/** Consultations are held on the FTMS telemedicine platform; this builds the join link for a session. */
final class FtmsMeeting
{
    public static function newReference(): string
    {
        return strtolower((string) Str::ulid());
    }

    public static function urlFor(string $reference): string
    {
        return str_replace('{reference}', $reference, (string) config('itizan.ftms.join_url'));
    }
}
