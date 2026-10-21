<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Cédula de identidad tolerante solo en el prefijo: los extranjeros usan
 * "E" seguido de dígitos y no llevan dígito verificador, así que se aceptan
 * tal cual. Cualquier otro valor debe ser una cédula ecuatoriana completa
 * de 10 dígitos con provincia y módulo 10 válidos.
 */
class FlexibleDni implements ValidationRule
{
    private const FOREIGNER_PATTERN = '/^E\d{1,19}$/i';

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

        if (preg_match(self::FOREIGNER_PATTERN, $dni)) {
            return;
        }

        if (! ctype_digit($dni)) {
            $fail(__('El :attribute debe tener 10 dígitos, o iniciar con E si es una cédula de extranjero.'));

            return;
        }

        if (strlen($dni) !== 10) {
            $fail(__('El :attribute debe tener 10 dígitos; una cédula de '.strlen($dni).' dígitos está incompleta.'));

            return;
        }

        $this->validateCedula($dni, $attribute, $fail);
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
