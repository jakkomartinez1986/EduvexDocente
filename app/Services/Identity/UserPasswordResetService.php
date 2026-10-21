<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Models\User;

/**
 * Reseteo administrativo de credenciales.
 *
 * Devuelve la contraseña del usuario al valor inicial del sistema
 * (`{cedula}passsword`) y marca `must_change_password` para que el
 * middleware EnsureUserIsActive lo obligue a definir una nueva al ingresar.
 */
final class UserPasswordResetService
{
    /**
     * Sufijo de la contraseña inicial. Se replica desde los imports
     * (Teachers/Students/RepresentativesImport) para mantener una sola fuente.
     */
    public const DEFAULT_PASSWORD_SUFFIX = 'passsword';

    /**
     * Contraseña inicial derivada de la cédula del usuario.
     */
    public static function defaultPasswordFor(string $dni): string
    {
        return $dni.self::DEFAULT_PASSWORD_SUFFIX;
    }

    /**
     * Restablece la contraseña del usuario a su valor inicial y exige el
     * cambio en el próximo ingreso.
     *
     * @return string La contraseña temporal asignada.
     */
    public function resetToDefault(User $user): string
    {
        $password = self::defaultPasswordFor($user->dni);

        $user->forceFill([
            'password' => $password,
            'must_change_password' => true,
            'remember_token' => null,
        ])->save();

        return $password;
    }
}
