<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Records who accessed or changed sensitive data. Never put clinical content in $metadata.
 */
final class Audit
{
    public static function log(string $action, ?Model $subject = null, array $metadata = []): AuditLog
    {
        return AuditLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'ip_address' => request()?->ip(),
            'metadata' => $metadata ?: null,
        ]);
    }
}
