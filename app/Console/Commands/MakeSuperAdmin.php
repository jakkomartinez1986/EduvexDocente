<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Identity\SuperAdminService;
use App\Services\Identity\UserPasswordResetService;
use Illuminate\Console\Command;

/**
 * Alta (o promocion) de la cuenta unica de SUPER-ADMIN.
 *
 * Es el camino previsto para instalar el sistema: el registro publico queda
 * disponible solo mientras no exista un super administrador, y esta commande
 * es la que crea esa primera cuenta cuando el alta se hace por consola.
 */
class MakeSuperAdmin extends Command
{
    protected $signature = 'make:super-admin
                            {--dni= : Cedula del super administrador (por defecto la de SUPER_ADMIN_DNI)}
                            {--name= : Nombre de la cuenta a crear}
                            {--lastname= : Apellido de la cuenta a crear}
                            {--email= : Correo de la cuenta a crear}';

    protected $description = 'Crear o promover la cuenta unica de SUPER-ADMIN del sistema';

    public function handle(SuperAdminService $superAdmin): int
    {
        $dni = trim((string) ($this->option('dni') ?: config('eduvex.super_admin_dni')));

        if ($dni === '') {
            $this->error('No hay cedula de super administrador: define SUPER_ADMIN_DNI en el entorno o usa --dni.');

            return self::FAILURE;
        }

        if ($superAdmin->exists()) {
            $current = $superAdmin->first();

            $this->error("Ya existe un super administrador (cedula {$current?->dni}). El sistema solo admite uno.");

            return self::FAILURE;
        }

        $user = User::where('dni', $dni)->first();

        if ($user instanceof User) {
            $superAdmin->promote($user);

            $this->info("La cuenta {$user->dni} fue promovida a SUPER-ADMIN.");

            return self::SUCCESS;
        }

        $email = trim((string) $this->option('email'));

        if ($email === '') {
            $email = 'superadmin@eduvex.test';
        }

        if (User::where('email', $email)->exists()) {
            $this->error("El correo {$email} ya esta registrado: usa --email para indicar otro.");

            return self::FAILURE;
        }

        $password = UserPasswordResetService::defaultPasswordFor($dni);

        $user = User::create([
            'name' => trim((string) $this->option('name')) ?: 'Super',
            'lastname' => trim((string) $this->option('lastname')) ?: 'Administrador',
            'dni' => $dni,
            'email' => $email,
            'password' => $password,
            'status' => 1,
            'must_change_password' => true,
        ]);

        $superAdmin->promote($user);

        $this->info("Super administrador creado: {$user->email} (cedula {$user->dni}).");
        $this->line("Contrasena temporal: {$password} (debera cambiarla al ingresar).");

        return self::SUCCESS;
    }
}
