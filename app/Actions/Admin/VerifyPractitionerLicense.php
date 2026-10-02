<?php

namespace App\Actions\Admin;

use App\Exceptions\BusinessRuleViolation;
use App\Models\Practitioner;
use App\Models\User;
use App\Support\Audit;

/** Only practitioners whose licence was verified by an administrator appear publicly or can be booked. */
class VerifyPractitionerLicense
{
    public function handle(Practitioner $practitioner, User $admin, mixed $expiresOn = null): Practitioner
    {
        if (blank($practitioner->license_number)) {
            throw new BusinessRuleViolation(__('أدخل رقم الترخيص قبل التوثيق.'));
        }

        $practitioner->update([
            'license_verified_at' => now(),
            'license_verified_by' => $admin->id,
            'license_expires_on' => $expiresOn,
        ]);

        Audit::log('practitioner.license_verified', $practitioner);

        return $practitioner;
    }

    public function revoke(Practitioner $practitioner): Practitioner
    {
        $practitioner->update(['license_verified_at' => null, 'license_verified_by' => null]);

        Audit::log('practitioner.license_revoked', $practitioner);

        return $practitioner;
    }
}
