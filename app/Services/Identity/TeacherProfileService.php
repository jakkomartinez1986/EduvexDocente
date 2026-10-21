<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Models\Identity\Users\Teacher;
use App\Models\User;

/**
 * Perfil de docente enlazado a una cuenta de usuario.
 *
 * El rol DOCENTE no basta por si solo: el modulo de docentes, la navegacion
 * y los horarios se apoyan en el registro `teachers`, que es 1-a-1 con
 * `users`. Este servicio garantiza que la cuenta que recibe el rol DOCENTE
 * tenga siempre su perfil de docente, y no la deja a medias.
 */
final class TeacherProfileService
{
    /**
     * Nombre del rol de docente.
     */
    public const ROLE_NAME = 'DOCENTE';

    /**
     * Prefijo de los codigos de docente (DOC-0001, DOC-0002, ...).
     */
    public const CODE_PREFIX = 'DOC-';

    /**
     * Cantidad de digitos del codigo de docente.
     */
    private const CODE_LENGTH = 4;

    /**
     * Devuelve el perfil de docente de la cuenta, creandolo si no existe.
     *
     * Los datos academicos del docente (especializacion, titulo, nivel
     * educativo) se completan despues desde el modulo de Docentes.
     */
    public function ensure(User $user): Teacher
    {
        $teacher = Teacher::where('user_id', $user->id)->first();

        if ($teacher) {
            return $teacher;
        }

        return Teacher::create([
            'user_id' => $user->id,
            'teacher_code' => $this->nextCode(),
            'hire_date' => now()->toDateString(),
        ]);
    }

    /**
     * Siguiente codigo de docente libre.
     *
     * Se consideran tambien los docentes dados de baja logicamente porque su
     * codigo sigue reservado por el indice unico.
     */
    private function nextCode(): string
    {
        $number = (int) Teacher::withTrashed()->orderByDesc('id')->value('id');

        do {
            $number++;
            $code = self::CODE_PREFIX.str_pad((string) $number, self::CODE_LENGTH, '0', STR_PAD_LEFT);
        } while (Teacher::withTrashed()->where('teacher_code', $code)->exists());

        return $code;
    }
}
