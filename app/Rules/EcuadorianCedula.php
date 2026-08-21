<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class EcuadorianCedula implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $cedula = preg_replace('/[^0-9]/', '', (string) $value);

        if (strlen($cedula) !== 10) {
            $fail(__('El :attribute debe tener exactamente 10 dígitos.'));

            return;
        }

        $provinceCode = (int) substr($cedula, 0, 2);

        if ($provinceCode < 1 || $provinceCode > 24) {
            $fail(__('El :attribute no corresponde a una provincia válida.'));

            return;
        }

        $digits = str_split($cedula);
        $weights = [2, 1, 2, 1, 2, 1, 2, 1, 2];
        $sum = 0;

        for ($i = 0; $i < 9; $i++) {
            $product = (int) $digits[$i] * $weights[$i];
            $sum += ($product >= 10) ? $product - 9 : $product;
        }

        $checkDigit = (int) $digits[9];
        $expectedCheckDigit = (10 - ($sum % 10)) % 10;

        if ($checkDigit !== $expectedCheckDigit) {
            $fail(__('El :attribute no es válido.'));
        }
    }
}
