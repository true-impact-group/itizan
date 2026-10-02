<?php

namespace App\Console\Commands;

use App\Actions\Admin\SaveStaffUser;
use App\Enums\Role;
use App\Models\User;
use App\Rules\PhoneNumber;
use App\Support\Phone;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

#[Signature('itizan:create-admin {name : Full name} {phone : Mobile number, e.g. 0551234567} {--email= : Optional email}')]
#[Description('Create an administrator account (the password is asked interactively, never passed on the command line)')]
class CreateAdmin extends Command
{
    public function handle(SaveStaffUser $save): int
    {
        $phone = Phone::normalize($this->argument('phone')) ?? $this->argument('phone');
        $password = $this->secret('Password');

        $validator = Validator::make(
            ['name' => $this->argument('name'), 'phone' => $phone, 'email' => $this->option('email') ?: null, 'password' => $password],
            [
                'name' => ['required', 'string', 'min:3', 'max:100'],
                'phone' => ['required', new PhoneNumber, Rule::unique(User::class, 'phone')],
                'email' => ['nullable', 'email', Rule::unique(User::class, 'email')],
                'password' => ['required', Password::min(12)->letters()->numbers()],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = $save->handle(null, [
            'name' => $this->argument('name'),
            'phone' => $phone,
            'email' => $this->option('email') ?: null,
            'password' => $password,
            'role' => Role::Admin->value,
            'is_active' => true,
        ]);

        $this->info("Administrator created: {$user->name} ({$user->phone}).");

        return self::SUCCESS;
    }
}
