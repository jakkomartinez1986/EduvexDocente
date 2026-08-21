<?php

declare(strict_types=1);

namespace App\Services\Api\V1\Academic;

use App\Http\Resources\Api\V1\Academic\ActivityRecoveryResource;
use App\Http\Resources\Api\V1\Academic\AssessmentBlockResource;
use App\Http\Resources\Api\V1\Academic\GradebookStudentResource;
use App\Http\Resources\Api\V1\Academic\StudentExamResource;
use App\Http\Resources\Api\V1\Academic\StudentProjectResource;
use App\Http\Resources\Api\V1\Academic\SupplementaryExamResource;
use App\Models\Academic\GradeBook\Summaries\Subjects\ActivityGrade;
use App\Models\Academic\GradeBook\Summaries\Subjects\ActivityRecovery;
use App\Models\Academic\GradeBook\Summaries\Subjects\AssessmentBlock;
use App\Models\Academic\GradeBook\Summaries\Subjects\StudentExam;
use App\Models\Academic\GradeBook\Summaries\Subjects\StudentProject;
use App\Models\Academic\GradeBook\Summaries\Supplementary\SupplementaryExam;
use App\Models\Identity\Users\Student;
use App\Models\Identity\Users\Teacher;
use App\Models\Management\Enrollments\StudentEnrollment;
use App\Models\Setting\YearSettings\AcademicPeriod;
use App\Models\Setting\YearSettings\GradingScheme;
use App\Models\Setting\YearSettings\ScolarYear;
use App\Models\TeacherManagement\Academics\ClassSchedule;
use App\Models\User;
use App\Services\AcademicYearService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Construye la vista transaccional del libro de calificaciones de un docente:
 * contexto, matrícula, bloques/actividades/notas, exámenes, proyectos,
 * recuperaciones, supletorios y el resumen por estudiante con las reglas
 * reales de cálculo usadas en los reportes.
 */
final class GradebookService
{
    public function __construct(private readonly AcademicYearService $academicYearService) {}

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function view(Teacher $teacher, array $validated): array
    {
        $yearId = $this->resolveYearId($validated);

        $schedule = $this->scheduleFor($teacher, $yearId, (int) $validated['subject_id'], (int) $validated['grade_id']);

        $period = $this->resolvePeriod((int) $validated['trimester_id'], $yearId);

        $year = ScolarYear::find($yearId);
        $gradingScheme = GradingScheme::where('year_id', $yearId)->where('status', 1)->first();

        $studentIds = $this->enrolledStudentIds($yearId, (int) $validated['grade_id']);

        $students = $studentIds->isEmpty()
            ? collect()
            : Student::with('user')
                ->whereIn('id', $studentIds)
                ->orderBy(User::select('lastname')->whereColumn('users.id', 'students.user_id'))
                ->orderBy(User::select('name')->whereColumn('users.id', 'students.user_id'))
                ->get();

        $enrollments = StudentEnrollment::query()
            ->where('year_id', $yearId)
            ->where('grade_id', (int) $validated['grade_id'])
            ->whereIn('student_id', $studentIds)
            ->get()
            ->keyBy('student_id');

        $blocks = AssessmentBlock::query()
            ->with(['activities.grades' => function ($query) use ($studentIds): void {
                $query->whereIn('student_id', $studentIds);
            }])
            ->where('year_id', $yearId)
            ->where('subject_id', (int) $validated['subject_id'])
            ->where('grade_id', (int) $validated['grade_id'])
            ->where('trimester_id', (int) $validated['trimester_id'])
            ->where('teacher_id', $teacher->id)
            ->orderBy('order')
            ->orderBy('created_at')
            ->get();

        $activityIds = $blocks->flatMap(fn (AssessmentBlock $block): Collection => $block->activities)->pluck('id');

        $exams = StudentExam::query()
            ->where('year_id', $yearId)
            ->where('subject_id', (int) $validated['subject_id'])
            ->where('grade_id', (int) $validated['grade_id'])
            ->where('trimester_id', (int) $validated['trimester_id'])
            ->whereIn('student_id', $studentIds)
            ->get()
            ->keyBy('student_id');

        $projects = StudentProject::query()
            ->where('year_id', $yearId)
            ->where('subject_id', (int) $validated['subject_id'])
            ->where('grade_id', (int) $validated['grade_id'])
            ->where('trimester_id', (int) $validated['trimester_id'])
            ->whereIn('student_id', $studentIds)
            ->get()
            ->keyBy('student_id');

        $recoveries = $activityIds->isEmpty()
            ? collect()
            : ActivityRecovery::query()
                ->whereIn('activity_id', $activityIds)
                ->whereIn('student_id', $studentIds)
                ->orderBy('attempt_number')
                ->get();

        $supplementaryExams = SupplementaryExam::query()
            ->where('year_id', $yearId)
            ->where('subject_id', (int) $validated['subject_id'])
            ->where('grade_id', (int) $validated['grade_id'])
            ->whereIn('student_id', $studentIds)
            ->get()
            ->keyBy('student_id');

        return [
            'generated_at' => now()->toISOString(),
            'context' => $this->context($schedule, $period, $year, $gradingScheme, $teacher),
            'students' => $students->map(
                fn (Student $student): GradebookStudentResource => new GradebookStudentResource(
                    $student,
                    $enrollments->get($student->id),
                ),
            )->values(),
            'blocks' => AssessmentBlockResource::collection($blocks),
            'exams' => StudentExamResource::collection($exams->values()),
            'projects' => StudentProjectResource::collection($projects->values()),
            'recoveries' => ActivityRecoveryResource::collection($recoveries),
            'supplementary_exams' => SupplementaryExamResource::collection($supplementaryExams->values()),
            'summary' => $this->summary($students, $blocks, $exams, $projects, $gradingScheme),
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
            ->with('subject.area', 'grade.nivel.shift')
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

        if (! $period || (int) $period->year_id !== $yearId || (bool) $period->is_supletorio) {
            throw new NotFoundHttpException('El período no es válido para el libro de calificaciones.');
        }

        return $period;
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
     * @return array<string, mixed>
     */
    private function context(
        ClassSchedule $schedule,
        AcademicPeriod $period,
        ?ScolarYear $year,
        ?GradingScheme $gradingScheme,
        Teacher $teacher,
    ): array {
        $subject = $schedule->subject;
        $grade = $schedule->grade;

        return [
            'year_id' => (int) $schedule->year_id,
            'year_name' => $year?->year_name,
            'subject' => [
                'id' => $schedule->subject_id,
                'subject_name' => $subject?->subject_name,
                'area_id' => $subject?->area_id,
                'area_name' => $subject?->area?->area_name,
            ],
            'grade' => [
                'id' => $schedule->grade_id,
                'grade_name' => $grade?->grade_name,
                'section' => $grade?->section,
                'nivel_name' => $grade?->nivel?->nivel_name,
                'shift_name' => $grade?->nivel?->shift?->shift_name,
            ],
            'trimester' => [
                'id' => $period->id,
                'trimester_name' => $period->trimester_name,
                'start_date' => $period->start_date ? Carbon::parse($period->start_date)->toDateString() : null,
                'end_date' => $period->end_date ? Carbon::parse($period->end_date)->toDateString() : null,
                'is_supletorio' => (bool) $period->is_supletorio,
                'is_grading_open' => $period->isGradingOpen(),
                'is_grading_past' => $period->isGradingPast(),
                'status' => (int) $period->status,
            ],
            'teacher' => [
                'id' => $teacher->id,
                'teacher_code' => $teacher->teacher_code,
                'full_name' => $teacher->full_name,
            ],
            'assignment' => [
                'schedule_type' => $schedule->schedule_type,
                'is_active' => (bool) $schedule->is_active,
            ],
            'grading_scheme' => $gradingScheme ? [
                'formative_percentage' => (float) $gradingScheme->formative_percentage,
                'summative_percentage' => (float) $gradingScheme->summative_percentage,
                'exam_percentage' => (float) $gradingScheme->exam_percentage,
                'project_percentage' => (float) $gradingScheme->project_percentage,
            ] : null,
        ];
    }

    /**
     * @param  Collection<int, Student>  $students
     * @param  Collection<int, AssessmentBlock>  $blocks
     * @param  Collection<int, StudentExam>  $exams
     * @param  Collection<int, StudentProject>  $projects
     * @return array<int, array<string, mixed>>
     */
    private function summary(
        Collection $students,
        Collection $blocks,
        Collection $exams,
        Collection $projects,
        ?GradingScheme $gradingScheme,
    ): array {
        $formativePercentage = (float) ($gradingScheme->formative_percentage ?? 0);
        $examPercentage = (float) ($gradingScheme->exam_percentage ?? 0);
        $projectPercentage = (float) ($gradingScheme->project_percentage ?? 0);

        return $students->map(function (Student $student) use ($blocks, $exams, $projects, $formativePercentage, $examPercentage, $projectPercentage): array {
            $blockAverages = $blocks->mapWithKeys(
                fn (AssessmentBlock $block): array => [$block->id => $this->blockAverageFor($block, $student->id)],
            )->all();

            $validAverages = array_values(array_filter($blockAverages, fn ($value): bool => $value !== null));

            $formativeAverage = $validAverages === []
                ? null
                : floor(array_sum($validAverages) / count($validAverages) * 100) / 100;

            $examGrade = $exams->get($student->id)?->grade;
            $projectGrade = $projects->get($student->id)?->grade;

            $hasData = $formativeAverage !== null || $examGrade !== null || $projectGrade !== null;

            $total = null;
            if ($hasData) {
                $formativeWeighted = $formativeAverage !== null
                    ? $formativeAverage * ($formativePercentage / 100)
                    : 0.0;
                $examWeighted = $examGrade !== null
                    ? (float) $examGrade * ($examPercentage / 100)
                    : 0.0;
                $projectWeighted = $projectGrade !== null
                    ? (float) $projectGrade * ($projectPercentage / 100)
                    : 0.0;

                $total = round($formativeWeighted + $examWeighted + $projectWeighted, 2);
            }

            return [
                'student_id' => $student->id,
                'block_averages' => $blockAverages,
                'formative_average' => $formativeAverage,
                'exam_grade' => $examGrade !== null ? (float) $examGrade : null,
                'project_grade' => $projectGrade !== null ? (float) $projectGrade : null,
                'total' => $total,
                'status' => $this->statusFor($total),
            ];
        })->values()->all();
    }

    private function blockAverageFor(AssessmentBlock $block, int $studentId): ?float
    {
        $activities = $block->activities;

        if ($activities->isEmpty()) {
            return null;
        }

        $total = 0.0;
        foreach ($activities as $activity) {
            $grade = $activity->grades->firstWhere('student_id', $studentId);

            if ($grade instanceof ActivityGrade && $grade->grade !== null) {
                $total += (float) $grade->grade;
            }
        }

        return floor($total / $activities->count() * 100) / 100;
    }

    private function statusFor(?float $total): ?string
    {
        if ($total === null) {
            return null;
        }

        return match (true) {
            $total >= 7 => 'aprobado',
            $total >= 5 => 'supletorio',
            default => 'reprobado',
        };
    }
}
