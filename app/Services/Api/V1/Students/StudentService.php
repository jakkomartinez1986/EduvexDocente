<?php

declare(strict_types=1);

namespace App\Services\Api\V1\Students;

use App\Http\Resources\Api\V1\Students\StudentResource;
use App\Models\Identity\Users\Student;
use App\Models\Identity\Users\Teacher;
use App\Models\Management\Enrollments\StudentEnrollment;
use App\Models\TeacherManagement\Academics\ClassSchedule;
use App\Services\AcademicYearService;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Colección de estudiantes del docente autenticado (D-06 / H-04).
 *
 * Solo viajan los estudiantes matriculados en los grados que el docente
 * tiene asignados en el año activo, con el DTO mínimo de §3.2 y paginación
 * por cursor (D-07). Queda eliminada por diseño la clase de IDOR de un
 * `GET /students/{id}` plano.
 */
final class StudentService
{
    public function __construct(private readonly AcademicYearService $academicYearService) {}

    /**
     * @param  Teacher  $teacher  Docente autenticado.
     * @param  int|null  $gradeId  Filtro opcional por grado asignado.
     * @return array{items: array<int, StudentResource>, next_cursor: ?string, has_more: bool}
     */
    public function paginated(Teacher $teacher, ?int $gradeId): array
    {
        $year = $this->academicYearService->getActiveYear();
        $perPage = max(1, (int) config('api.students.page_size', 200));

        if ($year === null) {
            return ['items' => [], 'next_cursor' => null, 'has_more' => false];
        }

        $assignedGradeIds = ClassSchedule::query()
            ->where('teacher_id', $teacher->id)
            ->where('year_id', $year->id)
            ->where('is_active', true)
            ->pluck('grade_id')
            ->unique()
            ->values();

        if ($gradeId !== null && ! $assignedGradeIds->contains($gradeId)) {
            throw new NotFoundHttpException('El grado solicitado no está entre tus asignaciones del año activo.');
        }

        $gradeIds = $gradeId !== null
            ? collect([$gradeId])
            : $assignedGradeIds;

        /** @var CursorPaginator<int, Student> $page */
        $page = Student::query()
            ->with('user')
            ->whereIn('students.id', function ($query) use ($year, $gradeIds): void {
                $query->select('student_id')
                    ->from('student_enrollments')
                    ->where('year_id', $year->id)
                    ->whereIn('grade_id', $gradeIds);
            })
            ->orderBy('students.id')
            ->cursorPaginate($perPage);

        $enrollments = $this->enrollmentsForPage($page, $year->id, $gradeIds);

        $items = $page->getCollection()
            ->map(fn (Student $student): StudentResource => new StudentResource($student, $enrollments->get($student->id)))
            ->values()
            ->all();

        return [
            'items' => $items,
            'next_cursor' => $page->nextCursor()?->encode(),
            'has_more' => $page->hasMorePages(),
        ];
    }

    /**
     * Matrícula relevante para los estudiantes de esta página. Si un estudiante
     * tiene varias matrículas en grados asignados se usa la del grado menor
     * (determinista).
     *
     * @param  CursorPaginator<int, Student>  $page
     * @param  Collection<int, int>  $gradeIds
     * @return Collection<int, StudentEnrollment>
     */
    private function enrollmentsForPage(CursorPaginator $page, int $yearId, Collection $gradeIds): Collection
    {
        $studentIds = $page->getCollection()->pluck('id')->unique()->values();

        if ($studentIds->isEmpty()) {
            return collect();
        }

        return StudentEnrollment::query()
            ->where('year_id', $yearId)
            ->whereIn('grade_id', $gradeIds)
            ->whereIn('student_id', $studentIds)
            ->orderBy('grade_id')
            ->get()
            ->keyBy('student_id');
    }
}
