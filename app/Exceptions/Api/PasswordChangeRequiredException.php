<?php

declare(strict_types=1);

namespace App\Exceptions\Api;

use RuntimeException;

/**
 * El usuario autenticó correctamente pero tiene pendiente el cambio de
 * contraseña obligatorio (must_change_password). No se emite token.
 *
 * La rotación ocurre por la vía web (Fortify); ver docs/api/authentication.md.
 */
final class PasswordChangeRequiredException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Debe cambiar su contraseña antes de continuar.');
    }
}
