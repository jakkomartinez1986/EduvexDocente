<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Models\Security\Authorizations\Role;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Super administrador unico del sistema.
 *
 * Centraliza las tres decisiones del dominio que giran alrededor de la
 * cuenta privileged:
 *
 *  - que cedula recibe el rol SUPER-ADMIN (config/eduvex.php, alimentada por
 *    la variable de entorno SUPER_ADMIN_DNI, nunca hardcodeada en el codigo);
 *  - que exista como maximo un super administrador;
 *  - que esa cuenta no pueda desactivarse ni eliminarse, para no liberar la
 *    cedula y dejar el sistema sin administrador.
 */
final class SuperAdminService
{
    /**
     * Nombre del rol de administracion total.
     */
    public const ROLE_NAME = 'SUPER-ADMIN';

    /**
     * Guard de Spatie al que pertenece el rol.
     */
    public const GUARD_NAME = 'web';

    /**
     * Descripcion del rol al crearlo por primera vez.
     */
    public const ROLE_DESCRIPTION = 'Super administrator with unrestricted access';

    /**
     * Indica si ya existe una cuenta con el rol SUPER-ADMIN.
     */
    public function exists(): bool
    {
        return $this->first() !== null;
    }

    /**
     * Devuelve la cuenta SUPER-ADMIN existente, si la hay.
     *
     * No usa el scope `role()` de Spatie: ese scope lanza una excepcion
     * cuando el rol SUPER-ADMIN todavia no existe en la base, y aqui la
     * consulta debe responder "no hay" en una instalacion recien migrada.
     */
    public function first(): ?User
    {
        return User::whereHas('roles', fn ($query) => $query->where('name', self::ROLE_NAME))->first();
    }

    /**
     * Indica si la cedula indicada corresponde al super administrador.
     *
     * Si SUPER_ADMIN_DNI no esta configurado la comparacion falla en falso
     * (fail closed): el registro publico nunca otorga el rol privileged.
     */
    public function isSuperAdminDni(string $dni): bool
    {
        $configured = config('eduvex.super_admin_dni');

        if (! is_string($configured) || trim($configured) === '') {
            Log::warning('SUPER_ADMIN_DNI no esta configurado: el registro no otorgara el rol SUPER-ADMIN.');

            return false;
        }

        return hash_equals(trim($configured), trim($dni));
    }

    /**
     * Convierte una cuenta en super administrador: verifica el correo, la
     * activa y le asigna el rol SUPER-ADMIN.
     */
    public function promote(User $user): void
    {
        $user->markEmailAsVerified();
        $user->status = 1;
        $user->save();

        $user->assignRole($this->role());
    }

    /**
     * Indica si la cuenta es el super administrador vigente.
     */
    public function isSuperAdmin(User $user): bool
    {
        return $user->hasRole(self::ROLE_NAME);
    }

    /**
     * Devuelve el rol SUPER-ADMIN creandolo si aun no existe.
     */
    private function role(): Role
    {
        return Role::firstOrCreate(
            ['name' => self::ROLE_NAME, 'guard_name' => self::GUARD_NAME],
            ['description' => self::ROLE_DESCRIPTION],
        );
    }
}
