<?php

namespace App\Actions\Consent;

use App\Enums\ConsentType;
use App\Models\ConsentRecord;
use App\Models\User;

/** Medical, marketing, telemedicine and renewal consents are recorded separately, never bundled. */
class RecordConsent
{
    public const CURRENT_VERSION = '1.0';

    public function handle(User $user, ConsentType $type, bool $granted = true): ConsentRecord
    {
        return ConsentRecord::create([
            'user_id' => $user->id,
            'type' => $type->value,
            'version' => self::CURRENT_VERSION,
            'granted' => $granted,
            'ip_address' => request()?->ip(),
            'user_agent' => mb_substr((string) request()?->userAgent(), 0, 255),
        ]);
    }

    /** The most recent record per type decides whether consent is currently granted. */
    public function hasConsent(User $user, ConsentType $type): bool
    {
        return (bool) ConsentRecord::query()
            ->where('user_id', $user->id)
            ->where('type', $type->value)
            ->latest('id')
            ->value('granted');
    }
}
