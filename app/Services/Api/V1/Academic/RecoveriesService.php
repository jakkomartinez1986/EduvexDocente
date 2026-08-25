<?php

declare(strict_types=1);

namespace App\Services\Api\V1\Academic;

use App\Models\Academic\GradeBook\Summaries\Subjects\Activity;
use App\Models\Academic\GradeBook\Summaries\Subjects\ActivityGrade;
use App\Models\Academic\GradeBook\Summaries\Subjects\ActivityRecovery;
use App\Models\Academic\GradeBook\Summaries\Subjects\StudentExam;
use App\Models\Academic\GradeBook\Summaries\Supplementary\ExamRecovery;
use App\Models\Identity\Users\Student;
use App\Models\Identity\Users\Teacher;
use App\Models\Management\Enrollments\StudentEnrollment;
use App\Models\Setting\EducationalSettings\Grade;
use App\Models\Setting\EducationalSettings\Subject;
use App\Models\Setting\YearSettings\AcademicPeriod;
use App\Models\TeacherManagement\Academics\ClassSchedule;
use App\Models\User;
use App\Services\AcademicYearService;
use Closure;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Módulo de recuperaciones del docente (espejo de la página web
 * "Recuperaciones"): estudiantes recuperables de una actividad o del examen,
 * historial de recuperaciones aplicadas y ciclo de vida de las recuperaciones
 * del examen (registrar, aplicar, eliminar). Las recuperaciones de actividad
 * se crean vía POST /grades/activities/{id}/recoveries.
 */
final class RecoveriesService
{
    public const PASSING_GRADE = 7.0;

    public const EXAM_MAX_SCORE = 20.0;

    public function __construct(private readonly AcademicYearService $academicYearService) {}

    /**
     * Estudiantes con nota baja (< aprobación) o con recuperaciones previas,
     * para la actividad o el examen indicado.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function recoverable(Teacher $teacher, array $validated): array
    {
        $type = (string) $validated['type'];
        $activity = null;

        if ($type === 'activity') {
            $activity = $this->ownActivity($teacher, (int) $validated['activity_id']);
            $block = $activity->assessmentBlock;

            $yearId = (int) $block->year_id;
            $subjectId = (int) $block->subject_id;
            $gradeId = (int) $block->grade_id;
            $trimesterId = (int) $block->trimester_id;
        } else {
            $yearId = $this->resolveYearId($validated);
            $subjectId = (int) $validated['subject_id'];
            $gradeId = (int) $validated['grade_id'];
            $trimesterId = (int) $validated['trimester_id'];

            $this->scheduleFor($teacher, $yearId, $subjectId, $gradeId);
        }

        $period = AcademicPeriod::find($trimesterId);

        if (! $period || (int) $period->year_id !== $yearId || (bool) $period->is_supletorio) {
            throw new NotFoundHttpException('El período no es válido para el libro de calificaciones.');
        }

        $enrolledIds = $this->activeEnrolledStudentIds($yearId, $gradeId);

        [$grades, $recoveries] = match ($type) {
            'activity' => [
                ActivityGrade::query()
                    ->where('activity_id', $activity?->id)
                    ->whereIn('student_id', $enrolledIds)
                    ->get()
                    ->keyBy('student_id'),
                ActivityRecovery::query()
                    ->where('activity_id', $activity?->id)
                    ->whereIn('student_id', $enrolledIds)
                    ->orderBy('attempt_number')
                    ->get()
                    ->groupBy('student_id'),
            ],
            default => [
                StudentExam::query()
                    ->where('year_id', $yearId)
                    ->where('subject_id', $subjectId)
                    ->where('grade_id', $gradeId)
                    ->where('trimester_id', $trimesterId)
                    ->whereIn('student_id', $enrolledIds)
                    ->get()
                    ->keyBy('student_id'),
                ExamRecovery::query()
                    ->where('subject_id', $subjectId)
                    ->where('grade_id', $gradeId)
                    ->where('trimester_id', $trimesterId)
                    ->where('year_id', $yearId)
                    ->whereIn('student_id', $enrolledIds)
                    ->orderBy('attempt_number')
                    ->get()
                    ->groupBy('student_id'),
            ],
        };

        $students = Student::query()
            ->whereIn('id', $enrolledIds)
            ->with('user')
            ->orderBy(User::query()->select('lastname')->whereColumn('users.id', 'students.user_id'))
            ->get();

        $rows = $students
            ->filter(function (Student $student) use ($grades, $recoveries): bool {
                /** @var float|int|null $current */
                $current = $grades->get($student->id)?->grade;

                return ($current !== null && (float) $current < self::PASSING_GRADE)
                    || $recoveries->has($student->id);
            })
            ->map(function (Student $student) use ($grades, $recoveries): array {
                /** @var float|int|null $current */
                $current = $grades->get($student->id)?->grade;

                /** @var Collection<int, ActivityRecovery|ExamRecovery> $studentRecoveries */
                $studentRecoveries = $recoveries->get($student->id, collect());

                return [
                    ...$this->studentPayload($student),
                    'current_grade' => $current !== null ? round((float) $current, 2) : null,
                    'recovery_count' => $studentRecoveries->count(),
                    'recoveries' => $studentRecoveries->map(
                        fn (ActivityRecovery|ExamRecovery $recovery): array => $this->recoveryPayload($recovery),
                    )->values()->all(),
                ];
            })
            ->values();

        $grade = Grade::find($gradeId);

        return [
            'context' => [
                'type' => $type,
                'year_id' => $yearId,
                'activity' => $activity !== null ? [
                    'id' => $activity->id,
                    'name' => $activity->name,
                    'max_score' => (float) $activity->max_score,
                ] : null,
                'subject' => [
                    'id' => $subjectId,
                    'subject_name' => Subject::find($subjectId)?->subject_name,
                ],
                'grade' => [
                    'id' => $gradeId,
                    'grade_name' => $grade?->grade_name,
                    'section' => $grade?->section,
                ],                'trimester' => [
                    'id' => $trimesterId,
                    'trimester_name' => $period->trimester_name,
                    'is_grading_open' => $period->isActive() && $period->isGradingOpen(),
                ],
                'passing_grade' => self::PASSING_GRADE,
                'max_score' => $activity !== null ? (float) $activity->max_score : self::EXAM_MAX_SCORE,
                'counts' => [
                    'low' => $rows->filter(
                        fn (array $row): bool => $row['current_grade'] !== null && $row['current_grade'] < self::PASSING_GRADE,
                    )->count(),
                    'recoveries' => $rows->sum('recovery_count'),
                    'applied' => $rows->sum(
                        fn (array $row): int => count(array_filter($row['recoveries'], fn (array $r): bool => (bool) $r['is_applied'])),
                    ),
                ],
            ],
            'students' => $rows->all(),
        ];
    }

    /**
     * Historial de recuperaciones ya aplicadas al libro de calificaciones.
     * Actividad: limitado a los bloques del docente. Examen: limitado a las
     * asignaturas/cursos que el docente tiene asignados en el año lectivo.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function applied(Teacher $teacher, array $validated): array
    {
        $yearId = $this->resolveYearId($validated);
        $trimesterId = (int) $validated['trimester_id'];

        $activityRecoveries = ActivityRecovery::query()
            ->where('is_applied', true)
            ->whereHas('activity.assessmentBlock', function ($query) use ($trimesterId, $yearId, $teacher): void {
                $query->where('trimester_id', $trimesterId)
                    ->where('year_id', $yearId)
                    ->where('teacher_id', $teacher->id);
            })
            ->with(['student.user', 'activity.assessmentBlock.subject', 'activity.assessmentBlock.grade'])
            ->orderByDesc('applied_at')
            ->get()
            ->map(function (ActivityRecovery $recovery): array {
                $block = $recovery->activity?->assessmentBlock;

                return [
                    ...$this->recoveryPayload($recovery),
                    ...$this->studentPayload($recovery->student),
                    ...$this->subjectGradePayload($block?->subject, $block?->grade),
                    'activity' => $recovery->activity ? [
                        'id' => $recovery->activity->id,
                        'name' => $recovery->activity->name,
                        'max_score' => (float) $recovery->activity->max_score,
                    ] : null,
                ];
            });

        $examRecoveries = ExamRecovery::query()
            ->where('is_applied', true)
            ->where('trimester_id', $trimesterId)
            ->where('year_id', $yearId)
            ->whereExists($this->ownScheduleExists($teacher, $yearId))
            ->with(['student.user', 'subject', 'grade'])
            ->orderByDesc('applied_at')
            ->get()
            ->map(function (ExamRecovery $recovery): array {
                return [
                    ...$this->recoveryPayload($recovery),
                    ...$this->studentPayload($recovery->student),
                    ...$this->subjectGradePayload($recovery->subject, $recovery->grade),
                ];
            });

        return [
            'trimester_id' => $trimesterId,
            'year_id' => $yearId,
            'activity_recoveries' => $activityRecoveries->all(),
            'exam_recoveries' => $examRecoveries->all(),
        ];
    }

    /**
     * Restricción EXISTS contra class_schedules: únicamente recuperaciones de
     * examenes cuya combinación subject_id + grade_id pertenece a una
     * asignación docente del año indicado.
     */
    private function ownScheduleExists(Teacher $teacher, int $yearId): Closure
    {
        return fn (QueryBuilder $query): QueryBuilder => $query
            ->selectRaw('1')
            ->from('class_schedules')
            ->whereColumn('class_schedules.subject_id', 'exam_recoveries.subject_id')
            ->whereColumn('class_schedules.grade_id', 'exam_recoveries.grade_id')
            ->where('class_schedules.teacher_id', $teacher->id)
            ->where('class_schedules.year_id', $yearId)
            ->whereNull('class_schedules.deleted_at');
    }

    /**
     * @return array<string, mixed>
     */
    private function subjectGradePayload(?Subject $subject, ?Grade $grade): array
    {
        return [
            'subject' => $subject ? [
                'id' => $subject->id,
                'subject_name' => $subject->subject_name,
            ] : null,
            'grade' => $grade ? [
                'id' => $grade->id,
                'grade_name' => $grade->grade_name,
                'section' => $grade->section,
            ] : null,
        ];
    }

    /**
     * Registra un intento de recuperación del examen sumativo. Permitido aun
     * con la ventana de calificación cerrada (igual que la web); aplicar sí
     * exige ventana abierta.
     *
     * @param  array<string, mixed>  $validated
     */
    public function registerExamRecovery(Teacher $teacher, array $validated): ExamRecovery
    {
        $yearId = $this->resolveYearId($validated);

        $this->scheduleFor($teacher, $yearId, (int) $validated['subject_id'], (int) $validated['grade_id']);

        $period = AcademicPeriod::find((int) $validated['trimester_id']);
        $this->assertValidPeriod($period, $yearId);

        $studentId = (int) $validated['student_id'];
        $studentIds = $this->enrolledStudentIds($yearId, (int) $validated['grade_id']);

        if (! $studentIds->contains($studentId)) {
            throw ValidationException::withMessages([
                'student_id' => 'El estudiante no está matriculado en este curso.',
            ]);
        }

        $exam = StudentExam::query()
            ->where('year_id', $yearId)
            ->where('subject_id', (int) $validated['subject_id'])
            ->where('grade_id', (int) $validated['grade_id'])
            ->where('trimester_id', (int) $validated['trimester_id'])
            ->where('student_id', $studentId)
            ->first();

        if (! $exam || $exam->grade === null) {
            throw ValidationException::withMessages([
                'student_id' => 'El estudiante no tiene nota de examen registrada.',
            ]);
        }

        $original = (float) $exam->grade;
        $recovery = min(max((float) $validated['recovery_grade'], 0.0), self::EXAM_MAX_SCORE);
        $method = (string) ($validated['update_method'] ?? ExamRecovery::METHOD_AVERAGE);
        $final = ExamRecovery::computeFinalGrade($original, $recovery, $method);

        $attempt = ExamRecovery::query()
            ->where('subject_id', (int) $validated['subject_id'])
            ->where('grade_id', (int) $validated['grade_id'])
            ->where('trimester_id', (int) $validated['trimester_id'])
            ->where('year_id', $yearId)
            ->where('student_id', $studentId)
            ->withTrashed()
            ->count() + 1;

        return ExamRecovery::create([
            'student_id' => $studentId,
            'subject_id' => (int) $validated['subject_id'],
            'grade_id' => (int) $validated['grade_id'],
            'trimester_id' => (int) $validated['trimester_id'],
            'year_id' => $yearId,
            'recorded_by' => $teacher->user_id,
            'attempt_number' => $attempt,
            'original_grade' => $original,
            'recovery_grade' => $recovery,
            'update_method' => $method,
            'final_grade' => $final,
            'is_applied' => false,
        ]);
    }

    /**
     * Aplica la recuperación del examen: marca aplicada y sobrescribe la nota
     * del examen en el libro de calificaciones.
     */
    public function applyExamRecovery(Teacher $teacher, ExamRecovery $recovery): void
    {
        $this->ownExamRecoveryOrFail($teacher, $recovery);

        if ($recovery->is_applied) {
            throw ValidationException::withMessages([
                'recovery' => 'Esta recuperación ya fue aplicada.',
            ]);
        }

        $period = AcademicPeriod::find((int) $recovery->trimester_id);
        $this->assertValidPeriod($period, (int) $recovery->year_id);
        $this->assertGradingOpen($period);

        $recovery->update([
            'is_applied' => true,
            'applied_at' => now(),
            'recorded_by' => $teacher->user_id,
        ]);

        StudentExam::updateOrCreate(
            [
                'year_id' => (int) $recovery->year_id,
                'subject_id' => (int) $recovery->subject_id,
                'grade_id' => (int) $recovery->grade_id,
                'trimester_id' => (int) $recovery->trimester_id,
                'student_id' => (int) $recovery->student_id,
            ],
            [
                'grade' => $recovery->final_grade,
                'recorded_by' => $teacher->user_id,
            ],
        );
    }

    /**
     * Elimina una recuperación del examen aún no aplicada.
     */
    public function destroyExamRecovery(Teacher $teacher, ExamRecovery $recovery): void
    {
        $this->ownExamRecoveryOrFail($teacher, $recovery);

        if ($recovery->is_applied) {
            throw ValidationException::withMessages([
                'recovery' => 'No se puede eliminar una recuperación ya aplicada.',
            ]);
        }

        $recovery->delete();
    }

    /**
     * Elimina una recuperación de actividad aún no aplicada.
     */
    public function destroyActivityRecovery(Teacher $teacher, ActivityRecovery $recovery): void
    {
        $recovery->load('activity.assessmentBlock');

        $block = $recovery->activity?->assessmentBlock;

        if (! $block || $block->teacher_id !== $teacher->id) {
            throw new NotFoundHttpException('No se encontró la recuperación.');
        }

        if ($recovery->is_applied) {
            throw ValidationException::withMessages([
                'recovery' => 'No se puede eliminar una recuperación ya aplicada.',
            ]);
        }

        $recovery->delete();
    }

    /**
     * Forma canónica de una recuperación para listados (actividad o examen).
     *
     * @return array<string, mixed>
     */
    private function recoveryPayload(ActivityRecovery|ExamRecovery $recovery): array
    {
        return [
            'id' => $recovery->id,
            'type' => $recovery instanceof ActivityRecovery ? 'activity' : 'exam',
            'attempt_number' => $recovery->attempt_number,
            'original_grade' => (float) $recovery->original_grade,
            'recovery_grade' => (float) $recovery->recovery_grade,
            'update_method' => $recovery->update_method,
            'final_grade' => $recovery->final_grade !== null ? (float) $recovery->final_grade : null,
            'is_applied' => (bool) $recovery->is_applied,
            'applied_at' => $recovery->applied_at?->toISOString(),
            'created_at' => $recovery->created_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function studentPayload(?Student $student): array
    {
        $user = $student?->user;

        return [
            'student' => [
                'id' => $student?->id,
                'student_code' => $student?->student_code,
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'lastname' => $user->lastname,
                    'full_name' => $user->full_name,
                ] : null,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolveYearId(array $validated): int
    {
        if (($validated['year_id'] ?? null) !== null) {
            return (int) $validated['year_id'];
        }

        $activeYearId = $this->academicYearService->getActiveYearId();

        if ($activeYearId === null) {
            throw new NotFoundHttpException('No existe un año lectivo activo.');
        }

        return $activeYearId;
    }

    private function scheduleFor(Teacher $teacher, int $yearId, int $subjectId, int $gradeId): ClassSchedule
    {
        $schedule = ClassSchedule::query()
            ->where('teacher_id', $teacher->id)
            ->where('year_id', $yearId)
            ->where('subject_id', $subjectId)
            ->where('grade_id', $gradeId)
            ->first();

        if (! $schedule) {
            throw new NotFoundHttpException('No se encontró la asignación de enseñanza para este docente.');
        }

        return $schedule;
    }

    private function assertValidPeriod(?AcademicPeriod $period, int $yearId): void
    {
        if (! $period || (int) $period->year_id !== $yearId || (bool) $period->is_supletorio) {
            throw new NotFoundHttpException('El período no es válido para el libro de calificaciones.');
        }
    }

    private function assertGradingOpen(AcademicPeriod $period): void
    {
        if (! $period->isActive() || ! $period->isGradingOpen()) {
            throw ValidationException::withMessages([
                'period' => 'El período de calificación está cerrado.',
            ]);
        }
    }

    private function ownActivity(Teacher $teacher, int $activityId): Activity
    {
        $activity = Activity::with('assessmentBlock')->find($activityId);

        if (! $activity || ! $activity->assessmentBlock || $activity->assessmentBlock->teacher_id !== $teacher->id) {
            throw new NotFoundHttpException('No se encontró la actividad.');
        }

        return $activity;
    }

    private function ownExamRecoveryOrFail(Teacher $teacher, ExamRecovery $recovery): void
    {
        try {
            $this->scheduleFor($teacher, (int) $recovery->year_id, (int) $recovery->subject_id, (int) $recovery->grade_id);
        } catch (NotFoundHttpException) {
            throw new NotFoundHttpException('No se encontró la recuperación.');
        }
    }

    /**
     * @return Collection<int, int>
     */
    private function enrolledStudentIds(int $yearId, int $gradeId): Collection
    {
        return StudentEnrollment::query()
            ->where('year_id', $yearId)
            ->where('grade_id', $gradeId)
            ->pluck('student_id')
            ->unique()
            ->values();
    }

    /**
     * @return Collection<int, int>
     */
    private function activeEnrolledStudentIds(int $yearId, int $gradeId): Collection
    {
        return StudentEnrollment::query()
            ->where('year_id', $yearId)
            ->where('grade_id', $gradeId)
            ->where('status', 'active')
            ->pluck('student_id')
            ->unique()
            ->values();
    }
}
