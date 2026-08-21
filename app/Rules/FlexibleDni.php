<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class FlexibleDni implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $dni = trim((string) $value);

        if ($dni === '') {
            $fail(__('El :attribute es obligatorio.'));

            return;
        }

        if (strlen($dni) > 20) {
            $fail(__('El :attribute no debe exceder 20 caracteres.'));

            return;
        }

        $numericOnly = preg_replace('/[^0-9]/', '', $dni);

        if (strlen($numericOnly) === 10 && $numericOnly === $dni) {
            $this->validateCedula($numericOnly, $attribute, $fail);
        }
    }

    protected function validateCedula(string $cedula, string $attribute, Closure $fail): void
    {
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
            $fail(__('El :attribute de cédula ecuatoriana no es válido.'));
        }
    }
}
