<?php

declare(strict_types=1);

namespace App\Exceptions\Api;

use RuntimeException;

/**
 * Error de autenticación: credenciales inválidas o usuario desactivado.
 *
 * El mensaje es genérico a propósito para no revelar si el usuario existe.
 */
final class InvalidCredentialsException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Las credenciales proporcionadas no son válidas.');
    }
}
