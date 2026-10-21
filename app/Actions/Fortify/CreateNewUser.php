<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Services\Identity\SuperAdminService;
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

        // El rol SUPER-ADMIN se otorga como maximo una vez y solo a la cedula
        // configurada en SUPER_ADMIN_DNI (fuera del repositorio).
        $superAdmin = app(SuperAdminService::class);

        if (! $superAdmin->exists() && $superAdmin->isSuperAdminDni($input['dni'])) {
            $superAdmin->promote($user);
        }

        return $user;
    }
}
