<?php

declare(strict_types=1);

namespace App\Services\Api\V1\Academic;

use App\Models\Academic\GradeBook\Summaries\Subjects\Activity;
use App\Models\Academic\GradeBook\Summaries\Subjects\ActivityGrade;
use App\Models\Academic\GradeBook\Summaries\Subjects\ActivityRecovery;
use App\Models\Academic\GradeBook\Summaries\Subjects\AssessmentBlock;
use App\Models\Academic\GradeBook\Summaries\Subjects\StudentExam;
use App\Models\Academic\GradeBook\Summaries\Subjects\StudentProject;
use App\Models\Academic\GradeBook\Summaries\Supplementary\SupplementaryExam;
use App\Models\Identity\Users\Teacher;
use App\Models\Management\Enrollments\StudentEnrollment;
use App\Models\Setting\YearSettings\AcademicPeriod;
use App\Models\TeacherManagement\Academics\ClassSchedule;
use App\Services\AcademicYearService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Registro transaccional del libro de calificaciones: bloques, actividades,
 * notas, exámenes, proyectos, supletorios y recuperaciones. Todo el acceso
 * está restringido a las asignaciones del docente autenticado.
 */
final class GradeRegistrationService
{
    public function __construct(private readonly AcademicYearService $academicYearService) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function storeBlock(Teacher $teacher, array $validated): AssessmentBlock
    {
        $yearId = $this->resolveYearId($validated);

        $this->scheduleFor($teacher, $yearId, (int) $validated['subject_id'], (int) $validated['grade_id']);
        $this->resolvePeriod((int) $validated['trimester_id'], $yearId);

        return AssessmentBlock::create([
            'year_id' => $yearId,
            'subject_id' => (int) $validated['subject_id'],
            'grade_id' => (int) $validated['grade_id'],
            'trimester_id' => (int) $validated['trimester_id'],
            'teacher_id' => $teacher->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'internal_percentage' => $validated['internal_percentage'] ?? null,
            'order' => (int) ($validated['order'] ?? 0),
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function storeActivity(Teacher $teacher, array $validated): Activity
    {
        $block = $this->ownBlock($teacher, (int) $validated['assessment_block_id']);

        return Activity::create([
            'assessment_block_id' => $block->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'date' => $validated['date'] ?? null,
            'max_score' => (float) $validated['max_score'],
            'status' => (bool) ($validated['status'] ?? true),
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updateActivity(Teacher $teacher, Activity $activity, array $validated): Activity
    {
        $this->ownActivity($teacher, $activity->id);

        $activity->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'date' => $validated['date'] ?? null,
            'max_score' => (float) $validated['max_score'],
            'status' => (bool) ($validated['status'] ?? $activity->status),
        ]);

        return $activity->refresh();
    }

    /**
     * Registra (crea o actualiza) las notas de una actividad para el listado de estudiantes.
     *
     * @param  array<int, array{student_id: int, grade: float|int|string|null}>  $grades
     */
    public function storeActivityGrades(Teacher $teacher, Activity $activity, array $grades): int
    {
        $block = $this->ownActivity($teacher, $activity->id)->assessmentBlock;

        $period = AcademicPeriod::find($block->trimester_id);
        $this->assertValidPeriod($period, (int) $block->year_id);
        $this->assertGradingOpen($period);

        $studentIds = $this->enrolledStudentIds((int) $block->year_id, (int) $block->grade_id);
        $this->assertStudentsEnrolled($grades, $studentIds);

        foreach ($grades as $item) {
            ActivityGrade::updateOrCreate(
                [
                    'activity_id' => $activity->id,
                    'student_id' => (int) $item['student_id'],
                ],
                [
                    'grade' => $item['grade'] !== null ? (float) $item['grade'] : null,
                    'recorded_by' => $teacher->user_id,
                ],
            );
        }

        return count($grades);
    }

    /**
     * Registra notas sumativas (examen o proyecto) de un trimestre.
     *
     * @param  'exam'|'project'  $type
     * @param  array<string, mixed>  $validated
     */
    public function storeSummative(Teacher $teacher, string $type, array $validated): int
    {
        $yearId = $this->resolveYearId($validated);

        $this->scheduleFor($teacher, $yearId, (int) $validated['subject_id'], (int) $validated['grade_id']);

        $period = $this->resolvePeriod((int) $validated['trimester_id'], $yearId);
        $this->assertGradingOpen($period);

        $studentIds = $this->enrolledStudentIds($yearId, (int) $validated['grade_id']);
        $this->assertStudentsEnrolled($validated['grades'], $studentIds);

        $keys = [
            'subject_id' => (int) $validated['subject_id'],
            'grade_id' => (int) $validated['grade_id'],
            'trimester_id' => (int) $validated['trimester_id'],
            'year_id' => $yearId,
        ];

        foreach ($validated['grades'] as $item) {
            $grade = $item['grade'] !== null ? (float) $item['grade'] : null;

            $attributes = [
                ...$keys,
                'student_id' => (int) $item['student_id'],
            ];

            $values = [
                'grade' => $grade,
                'recorded_by' => $teacher->user_id,
            ];

            match ($type) {
                'exam' => StudentExam::updateOrCreate($attributes, $values),
                'project' => StudentProject::updateOrCreate($attributes, $values),
            };
        }

        return count($validated['grades']);
    }

    /**
     * Registra notas del examen supletorio de fin de año.
     *
     * @param  array<string, mixed>  $validated
     */
    public function storeSupplementary(Teacher $teacher, array $validated): int
    {
        $yearId = $this->resolveYearId($validated);

        $this->scheduleFor($teacher, $yearId, (int) $validated['subject_id'], (int) $validated['grade_id']);

        $studentIds = $this->enrolledStudentIds($yearId, (int) $validated['grade_id']);
        $this->assertStudentsEnrolled($validated['grades'], $studentIds);

        foreach ($validated['grades'] as $item) {
            $studentId = (int) $item['student_id'];
            $grade = $item['grade'] !== null ? (float) $item['grade'] : null;

            if ($grade === null) {
                SupplementaryExam::query()
                    ->where('student_id', $studentId)
                    ->where('subject_id', (int) $validated['subject_id'])
                    ->where('grade_id', (int) $validated['grade_id'])
                    ->where('year_id', $yearId)
                    ->delete();

                continue;
            }

            SupplementaryExam::updateOrCreate(
                [
                    'student_id' => $studentId,
                    'subject_id' => (int) $validated['subject_id'],
                    'grade_id' => (int) $validated['grade_id'],
                    'year_id' => $yearId,
                ],
                [
                    'grade' => $grade,
                    'recorded_by' => $teacher->user_id,
                ],
            );
        }

        return count($validated['grades']);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function registerRecovery(Teacher $teacher, Activity $activity, array $validated): ActivityRecovery
    {
        $block = $this->ownActivity($teacher, $activity->id)->assessmentBlock;

        $studentId = (int) $validated['student_id'];
        $studentIds = $this->enrolledStudentIds((int) $block->year_id, (int) $block->grade_id);

        if (! $studentIds->contains($studentId)) {
            throw ValidationException::withMessages([
                'student_id' => 'El estudiante no está matriculado en este curso.',
            ]);
        }

        $current = ActivityGrade::query()
            ->where('activity_id', $activity->id)
            ->where('student_id', $studentId)
            ->first();

        if (! $current || $current->grade === null) {
            throw ValidationException::withMessages([
                'student_id' => 'El estudiante no tiene una nota inicial en esta actividad.',
            ]);
        }

        $original = (float) $current->grade;
        $recovery = min(max((float) $validated['recovery_grade'], 0.0), (float) $activity->max_score);
        $method = (string) ($validated['update_method'] ?? ActivityRecovery::METHOD_AVERAGE);
        $final = ActivityRecovery::computeFinalGrade($original, $recovery, $method);

        $attempt = ActivityRecovery::query()
            ->where('activity_id', $activity->id)
            ->where('student_id', $studentId)
            ->withTrashed()
            ->count() + 1;

        return ActivityRecovery::create([
            'activity_id' => $activity->id,
            'student_id' => $studentId,
            'year_id' => $block->year_id,
            'recorded_by' => $teacher->user_id,
            'attempt_number' => $attempt,
            'original_grade' => $original,
            'recovery_grade' => $recovery,
            'update_method' => $method,
            'final_grade' => $final,
            'is_applied' => false,
        ]);
    }

    public function applyRecovery(Teacher $teacher, ActivityRecovery $recovery): void
    {
        $recovery->load('activity.assessmentBlock');

        $block = $recovery->activity?->assessmentBlock;

        if (! $block || $block->teacher_id !== $teacher->id) {
            throw new NotFoundHttpException('No se encontró la recuperación.');
        }

        if ($recovery->is_applied) {
            throw ValidationException::withMessages([
                'recovery' => 'Esta recuperación ya fue aplicada.',
            ]);
        }

        $period = AcademicPeriod::find($block->trimester_id);
        $this->assertValidPeriod($period, (int) $block->year_id);
        $this->assertGradingOpen($period);

        $recovery->update([
            'is_applied' => true,
            'applied_at' => now(),
            'recorded_by' => $teacher->user_id,
        ]);

        ActivityGrade::updateOrCreate(
            [
                'activity_id' => $recovery->activity_id,
                'student_id' => $recovery->student_id,
            ],
            [
                'grade' => $recovery->final_grade,
                'recorded_by' => $teacher->user_id,
            ],
        );
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

    private function resolvePeriod(int $trimesterId, int $yearId): AcademicPeriod
    {
        $period = AcademicPeriod::find($trimesterId);

        $this->assertValidPeriod($period, $yearId);

        return $period;
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

    private function ownBlock(Teacher $teacher, int $blockId): AssessmentBlock
    {
        $block = AssessmentBlock::find($blockId);

        if (! $block || $block->teacher_id !== $teacher->id) {
            throw new NotFoundHttpException('No se encontró el bloque de evaluación.');
        }

        return $block;
    }

    private function ownActivity(Teacher $teacher, int $activityId): Activity
    {
        $activity = Activity::with('assessmentBlock')->find($activityId);

        if (! $activity || ! $activity->assessmentBlock || $activity->assessmentBlock->teacher_id !== $teacher->id) {
            throw new NotFoundHttpException('No se encontró la actividad.');
        }

        return $activity;
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
     * @param  array<int, array{student_id: int, grade: float|int|string|null}>  $grades
     * @param  Collection<int, int>  $studentIds
     */
    private function assertStudentsEnrolled(array $grades, Collection $studentIds): void
    {
        foreach ($grades as $item) {
            if (! $studentIds->contains((int) $item['student_id'])) {
                throw ValidationException::withMessages([
                    'grades' => 'Uno o más estudiantes no están matriculados en este curso.',
                ]);
            }
        }
    }
}
