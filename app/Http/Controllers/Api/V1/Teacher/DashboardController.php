<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Setting\ClassScheduleResource;
use App\Models\Identity\Users\Teacher;
use App\Models\Management\Enrollments\StudentEnrollment;
use App\Models\Setting\YearSettings\AcademicPeriod;
use App\Models\TeacherManagement\Academics\ClassSchedule;
use App\Services\AcademicYearService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * GET /teacher/dashboard — datos agregados del docente para la pantalla principal.
 *
 * Un solo endpoint que agrega: perfil del docente, período activo, resumen
 * de asignaciones, horario de hoy, y estado de actividad reciente.
 * Evita múltiples llamadas desde el cliente Flutter.
 */
final class DashboardController extends Controller
{
    public function __construct(private readonly AcademicYearService $academicYearService) {}

    public function index(Request $request): JsonResponse
    {
        $teacher = $request->user()->teacher;

        if (! $teacher instanceof Teacher) {
            throw new AccessDeniedHttpException('El usuario autenticado no tiene un perfil de docente.');
        }

        $year = $this->academicYearService->getActiveYear();

        $schedules = $year
            ? ClassSchedule::with('subject', 'grade')
                ->where('teacher_id', $teacher->id)
                ->where('year_id', $year->id)
                ->where('is_active', true)
                ->get()
            : collect();

        $assignedGradeIds = $schedules->pluck('grade_id')->unique();

        $studentCount = ($year && $assignedGradeIds->isNotEmpty())
            ? StudentEnrollment::where('year_id', $year->id)
                ->whereIn('grade_id', $assignedGradeIds)
                ->distinct('student_id')
                ->count('student_id')
            : 0;

        $subjectCount = $schedules->pluck('subject_id')->unique()->count();

        $todayName = strtoupper(now()->isoFormat('dddd'));
        $todaySchedule = $schedules
            ->filter(fn (ClassSchedule $s): bool => $s->day === $todayName)
            ->sortBy('start_time')
            ->values();

        $currentPeriod = $year
            ? AcademicPeriod::where('year_id', $year->id)
                ->where('status', 1)
                ->where('is_supletorio', false)
                ->where('start_date', '<=', now())
                ->where('end_date', '>=', now())
                ->first()
            : null;

        return ApiResponse::success(data: [
            'teacher' => [
                'id' => $teacher->id,
                'full_name' => $teacher->full_name,
                'teacher_code' => $teacher->teacher_code,
            ],
            'academic_year' => [
                'year_name' => $year?->year_name,
                'current_period' => $currentPeriod ? [
                    'id' => $currentPeriod->id,
                    'trimester_name' => $currentPeriod->trimester_name,
                    'start_date' => Carbon::parse($currentPeriod->start_date)->toDateString(),
                    'end_date' => Carbon::parse($currentPeriod->end_date)->toDateString(),
                    'is_grading_open' => $currentPeriod->isGradingOpen(),
                ] : null,
            ],
            'summary' => [
                'total_schedules' => $schedules->count(),
                'total_students' => $studentCount,
                'total_subjects' => $subjectCount,
                'total_grades' => $assignedGradeIds->count(),
            ],
            'today_schedule' => ClassScheduleResource::collection($todaySchedule),
        ]);
    }
}
