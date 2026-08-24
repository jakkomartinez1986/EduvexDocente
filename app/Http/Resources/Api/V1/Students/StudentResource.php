<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Students;

use App\Models\Identity\Users\Student;
use App\Models\Management\Enrollments\StudentEnrollment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * DTO mínimo de estudiante para clientes móviles (API_ROADMAP §3.2 / H-04).
 *
 * Nunca viajan: email, dni, roles, permisos, birth_date, blood_type,
 * emergency_contact ni medical_info. El cliente solo necesita identificar
 * al estudiante dentro de los grados asignados al docente autenticado.
 *
 * @property Student $resource
 */
final class StudentResource extends JsonResource
{
    public function __construct(
        Student $resource,
        private readonly ?StudentEnrollment $enrollment = null,
    ) {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $this->resource->user;

        return [
            'id' => $this->resource->id,
            'student_code' => $this->resource->student_code,
            'name' => $user?->name,
            'lastname' => $user?->lastname,
            'full_name' => $user?->full_name,
            'grade_id' => $this->enrollment?->grade_id,
            'enrollment_status' => $this->enrollment?->status,
            'profile_photo_url' => $user?->defaultUserPhotoUrl(),
        ];
    }
}
