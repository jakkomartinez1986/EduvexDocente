<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Models\Identity\Users\Teacher;
use App\Models\User;

/**
 * Perfil de docente enlazado a una cuenta de usuario.
 *
 * El rol DOCENTE y el perfil `teachers` van de la mano: el modulo de
 * docentes, la navegacion y los horarios se apoyan en el perfil, que es
 * 1-a-1 con `users`. Por eso el rol DOCENTE solo se ofrece y se asigna a
 * cuentas que ya tienen perfil de docente (por ejemplo, las importadas o
 * las creadas en el modulo de Docentes); una cuenta que se registro por
 * si sola no lo recibe, porque todavia no es docente.
 */
final class TeacherProfileService
{
    /**
     * Nombre del rol de docente.
     */
    public const ROLE_NAME = 'DOCENTE';

    /**
     * Indica si la cuenta ya tiene perfil de docente.
     *
     * Los perfiles dados de baja logicamente no cuentan: el rol DOCENTE
     * exige un perfil vigente.
     */
    public function hasProfile(User $user): bool
    {
        return Teacher::where('user_id', $user->id)->exists();
    }
}
