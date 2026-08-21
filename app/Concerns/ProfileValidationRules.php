<?php

namespace App\Concerns;

use App\Models\User;
use App\Rules\EcuadorianCedula;
use App\Rules\EcuadorianPhone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait ProfileValidationRules
{
    /**
     * Get the validation rules used to validate user profiles.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function profileRules(?int $userId = null): array
    {
        return [
            'name' => $this->nameRules(),
            'lastname' => $this->lastnameRules(),
            'dni' => $this->dniRules($userId),
            'phone' => $this->phoneRules(),
            'cellphone' => $this->cellphoneRules(),
            'address' => $this->addressRules(),
            'email' => $this->emailRules($userId),
        ];
    }

    /**
     * Get the validation rules used to validate user names.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function nameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * Get the validation rules used to validate user emails.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function emailRules(?int $userId = null): array
    {
        return [
            'required',
            'string',
            'email',
            'max:255',
            $userId === null
                ? Rule::unique(User::class)
                : Rule::unique(User::class)->ignore($userId),
        ];
    }

    protected function dniRules(?int $userId = null): array
    {
        return [
            'required',
            'string',
            'max:10',
            new EcuadorianCedula,
            $userId === null
                ? Rule::unique(User::class, 'dni')
                : Rule::unique(User::class, 'dni')->ignore($userId),
        ];
    }

    protected function lastnameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    protected function phoneRules(): array
    {
        return ['nullable', 'string', 'max:20', new EcuadorianPhone];
    }

    protected function cellphoneRules(): array
    {
        return ['nullable', 'string', 'max:20', new EcuadorianPhone(requireMobile: true)];
    }

    protected function addressRules(): array
    {
        return ['nullable', 'string'];
    }
}
