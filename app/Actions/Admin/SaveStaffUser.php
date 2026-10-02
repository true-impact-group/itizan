<?php

namespace App\Actions\Admin;

use App\Enums\PractitionerType;
use App\Enums\Role;
use App\Models\Practitioner;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;

/** Creates or updates a staff account; role and pharmacy are only ever set here, by an administrator. */
class SaveStaffUser
{
    /**
     * @param  array{name: string, phone: string, email?: ?string, password?: ?string, role: string, pharmacy_id?: ?int, is_active?: bool, specialty_ar?: ?string, specialty_en?: ?string, name_en?: ?string, license_number?: ?string, bio_ar?: ?string, bio_en?: ?string, accepts_instant?: bool}  $data
     */
    public function handle(?User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            $isNew = $user === null;
            $user ??= new User;

            $user->fill([
                'name' => $data['name'],
                'phone' => $data['phone'],
                'email' => $data['email'] ?? null,
            ]);
            if (! empty($data['password'])) {
                $user->password = $data['password'];
            }

            $role = Role::from($data['role']);
            $user->role = $role;
            $user->pharmacy_id = $role === Role::Pharmacy ? ($data['pharmacy_id'] ?? null) : null;
            $user->is_active = $data['is_active'] ?? true;
            $user->save();

            if (in_array($role, [Role::Doctor, Role::Dietitian], true)) {
                Practitioner::updateOrCreate(['user_id' => $user->id], [
                    'type' => $role === Role::Doctor ? PractitionerType::Doctor : PractitionerType::Dietitian,
                    'name_en' => $data['name_en'] ?? null,
                    'specialty_ar' => $data['specialty_ar'] ?? '',
                    'specialty_en' => $data['specialty_en'] ?? null,
                    'license_number' => $data['license_number'] ?? null,
                    'bio_ar' => $data['bio_ar'] ?? null,
                    'bio_en' => $data['bio_en'] ?? null,
                    'accepts_instant' => $role === Role::Doctor && ! empty($data['accepts_instant']),
                ]);
            }

            Audit::log($isNew ? 'staff.created' : 'staff.updated', $user, ['role' => $role->value]);

            return $user;
        });
    }
}
