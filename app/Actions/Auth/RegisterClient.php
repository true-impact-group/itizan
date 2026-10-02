<?php

namespace App\Actions\Auth;

use App\Actions\Consent\RecordConsent;
use App\Enums\ConsentType;
use App\Enums\Role;
use App\Models\User;
use App\Notifications\WelcomeNotification;
use Illuminate\Support\Facades\DB;

class RegisterClient
{
    public function __construct(private readonly RecordConsent $consents) {}

    /**
     * @param  array{name: string, phone: string, email?: ?string, password: string, gender?: ?string, birth_date?: mixed, city?: ?string, locale?: ?string, marketing_consent?: bool}  $data
     */
    public function handle(array $data): User
    {
        $user = DB::transaction(function () use ($data) {
            $user = new User([
                'name' => $data['name'],
                'phone' => $data['phone'],
                'email' => $data['email'] ?? null,
                'password' => $data['password'],
                'gender' => $data['gender'] ?? null,
                'birth_date' => $data['birth_date'] ?? null,
                'city' => $data['city'] ?? null,
                'locale' => $data['locale'] ?? 'ar',
            ]);
            // Sensitive attributes are never mass assigned.
            $user->role = Role::Client;
            $user->is_active = true;
            $user->save();

            // Terms/privacy, and (optionally) marketing, are separate records.
            $this->consents->handle($user, ConsentType::TermsPrivacy);
            if (! empty($data['marketing_consent'])) {
                $this->consents->handle($user, ConsentType::Marketing);
            }

            return $user;
        });

        $user->notify(new WelcomeNotification);

        return $user;
    }
}
