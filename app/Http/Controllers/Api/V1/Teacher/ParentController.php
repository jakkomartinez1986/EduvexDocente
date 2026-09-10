<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Teacher\ParentIndexRequest;
use App\Http\Resources\Api\V1\Teacher\ParentResource;
use App\Models\Identity\Users\Representative;
use App\Models\Identity\Users\Teacher;
use App\Models\Management\Enrollments\StudentEnrollment;
use App\Models\TeacherManagement\Academics\ClassSchedule;
use App\Services\AcademicYearService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * GET /teacher/parents — representantes de los estudiantes asignados al docente.
 *
 * Filtra exclusivamente los padres/representantes de los estudiantes
 * que pertenecen a los grados asignados al docente autenticado.
 */
final class ParentController extends Controller
{
    public function __construct(private readonly AcademicYearService $academicYearService) {}

    public function index(ParentIndexRequest $request): JsonResponse
    {
        $teacher = $request->user()->teacher;

        if (! $teacher instanceof Teacher) {
            throw new AccessDeniedHttpException('El usuario autenticado no tiene un perfil de docente.');
        }

        $year = $this->academicYearService->getActiveYear();

        if (! $year) {
            return ApiResponse::success(data: ['parents' => []], meta: ['total' => 0]);
        }

        $assignedGradeIds = ClassSchedule::where('teacher_id', $teacher->id)
            ->where('year_id', $year->id)
            ->where('is_active', true)
            ->pluck('grade_id')
            ->unique();

        $studentIds = StudentEnrollment::where('year_id', $year->id)
            ->whereIn('grade_id', $assignedGradeIds)
            ->pluck('student_id')
            ->unique();

        $query = Representative::with('user', 'student.user')
            ->whereIn('student_id', $studentIds);

        if ($request->filled('grade_id')) {
            $gradeId = $request->gradeId();

            if (! $assignedGradeIds->contains($gradeId)) {
                return ApiResponse::success(data: ['parents' => []], meta: ['total' => 0]);
            }

            $studentIdsForGrade = StudentEnrollment::where('year_id', $year->id)
                ->where('grade_id', $gradeId)
                ->pluck('student_id');

            $query->whereIn('student_id', $studentIdsForGrade);
        }

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->studentId());
        }

        $parents = $query->get();

        return ApiResponse::success(
            data: ['parents' => ParentResource::collection($parents)],
            meta: ['total' => $parents->count()],
        );
    }
}
