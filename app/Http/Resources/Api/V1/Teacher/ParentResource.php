<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Teacher;

use App\Models\Identity\Users\Representative;
use App\Models\Identity\Users\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * DTO de representante/padre para el cliente Flutter.
 *
 * Devuelve únicamente la información necesaria para que el docente
 * identifique y contacte a los padres de familia de sus estudiantes.
 *
 * @property Representative $resource
 */
final class ParentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Representative $representative */
        $representative = $this->resource;

        /** @var User|null $user */
        $user = $representative->user;

        /** @var Student|null $student */
        $student = $representative->student;

        /** @var User|null $studentUser */
        $studentUser = $student?->user;

        return [
            'id' => $representative->id,
            'user' => [
                'id' => $user?->id,
                'name' => $user?->name,
                'lastname' => $user?->lastname,
                'phone' => $user?->phone,
                'cellphone' => $user?->cellphone,
                'email' => $user?->email,
            ],
            'relationship' => $representative->relationship,
            'occupation' => $representative->occupation,
            'student' => [
                'id' => $student?->id,
                'user' => [
                    'id' => $studentUser?->id,
                    'name' => $studentUser?->name,
                    'lastname' => $studentUser?->lastname,
                ],
                'student_code' => $student?->student_code,
            ],
        ];
    }
}
