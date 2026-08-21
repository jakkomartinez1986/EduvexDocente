<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class EcuadorianPhone implements ValidationRule
{
    public function __construct(
        protected bool $requireMobile = false,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $phone = preg_replace('/[^0-9]/', '', (string) $value);

        $length = strlen($phone);

        $normalized = $phone;

        if ($length === 13 && str_starts_with($phone, '593')) {
            $normalized = '0'.substr($phone, 3);
        } elseif ($length === 12 && str_starts_with($phone, '593')) {
            $normalized = '0'.substr($phone, 2);
        }

        if (strlen($normalized) !== 10) {
            $fail(__('El :attribute debe tener 10 dígitos.'));

            return;
        }

        if (! str_starts_with($normalized, '0')) {
            $fail(__('El :attribute debe comenzar con 0.'));

            return;
        }

        $isMobile = str_starts_with($normalized, '09');
        $areaCode = substr($normalized, 0, 2);

        $validAreaCodes = ['02', '03', '04', '05', '06', '07'];

        if (! $isMobile && ! in_array($areaCode, $validAreaCodes)) {
            $fail(__('El :attribute no corresponde a un código de área válido.'));

            return;
        }

        if ($this->requireMobile && ! $isMobile) {
            $fail(__('El :attribute debe ser un número de celular válido.'));
        }
    }
}
