<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Academic;

use App\Models\Identity\Users\Student;
use App\Models\Management\Enrollments\StudentEnrollment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Estudiante matriculado en un grado/año, con su usuario y estado de matrícula.
 *
 * @mixin Student
 */
final class GradebookStudentResource extends JsonResource
{
    public function __construct(
        private readonly Student $studentResource,
        ?StudentEnrollment $enrollment = null,
    ) {
        parent::__construct($studentResource);
        $this->enrollment = $enrollment;
    }

    public ?StudentEnrollment $enrollment = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Student $student */
        $student = $this->studentResource;

        $user = $student->user;

        return [
            'id' => $student->id,
            'student_code' => $student->student_code,
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'lastname' => $user->lastname,
                'full_name' => $user->full_name,
            ] : null,
            'enrollment_status' => $this->enrollment?->status,
        ];
    }
}
