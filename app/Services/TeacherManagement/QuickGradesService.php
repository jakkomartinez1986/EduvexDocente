<?php

declare(strict_types=1);

namespace App\Services\TeacherManagement;

use App\Models\Academic\GradeBook\Summaries\Subjects\ActivityGrade;
use App\Models\Academic\GradeBook\Summaries\Subjects\AssessmentBlock;
use App\Models\Identity\Users\Student;
use App\Models\Setting\YearSettings\AcademicPeriod;
use App\Models\TeacherManagement\Academics\ClassSchedule;
use App\Support\Database\BulkWrite;
use Illuminate\Database\Eloquent\Builder;

final class QuickGradesService
{
    public const MODAL_PAGE_SIZE = 8;

    public function loadStudentsForQuickGrades(int $scheduleId, int $yearId): array
    {
        $schedule = ClassSchedule::with('grade')->findOrFail($scheduleId);

        $students = Student::whereHas('enrollments', function ($q) use ($schedule, $yearId) {
            $q->where('grade_id', $schedule->grade_id)
                ->where('year_id', $yearId);
        })->with('user')->get();

        return $students->map(fn ($s) => [
            'id' => $s->id,
            'name' => $s->user->full_name ?? trim(($s->user->lastname ?? '').' '.($s->user->name ?? '')),
            'code' => $s->student_code,
        ])->toArray();
    }

    /**
     * Carga TODOS los bloques de evaluación del aula (con sus actividades) para
     * que el docente pueda registrar notas en cualquier bloque y no solo en el
     * primero. Antes solo devolvía el bloque inicial (`->first()`), por lo que
     * las actividades de los bloques nuevos nunca aparecían en el menú rápido.
     *
     * @return array<int, array{id:int, name:string, order:int, activities:array<int, array{id:int, name:string, date:?string, max_score:?float}>}>
     */
    public function loadBlocks(int $scheduleId, int $yearId): array
    {
        $schedule = ClassSchedule::with('subject', 'grade', 'trimester')->findOrFail($scheduleId);

        $trimesterId = $this->resolveTrimesterId($schedule, $yearId);

        $blocks = $this->queryBlocks($schedule, $yearId, $trimesterId)->get();

        if ($blocks->isEmpty() && ! $trimesterId) {
            $blocks = $this->queryBlocks($schedule, $yearId, null)->get();
        }

        return $blocks->map(fn (AssessmentBlock $block) => [
            'id' => $block->id,
            'name' => $block->name,
            'order' => $block->order,
            'activities' => $block->activities
                ->sortByDesc('created_at')
                ->values()
                ->map(fn ($activity) => [
                    'id' => $activity->id,
                    'name' => $activity->name,
                    'date' => $activity->date?->toDateString(),
                    'max_score' => $activity->max_score,
                ])->all(),
        ])->values()->all();
    }

    /**
     * Bloque por defecto: el primero que tenga actividades; si ninguno tiene,
     * el primero de la lista.
     *
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array<string, mixed>|null
     */
    public function getDefaultBlock(array $blocks): ?array
    {
        if ($blocks === []) {
            return null;
        }

        $collection = collect($blocks);

        return $collection->first(fn ($block) => ($block['activities'] ?? []) !== [])
            ?? $collection->first();
    }

    /**
     * Actividad por defecto de un bloque: la de la fecha de hoy si existe,
     * caso contrario la más reciente.
     *
     * @param  array<int, array<string, mixed>>  $activities
     * @return array<string, mixed>|null
     */
    public function getDefaultActivity(array $activities): ?array
    {
        if ($activities === []) {
            return null;
        }

        $collection = collect($activities);
        $today = now()->toDateString();

        return $collection->first(fn ($activity) => ($activity['date'] ?? null) === $today)
            ?? $collection->first();
    }

    /**
     * @return Builder<AssessmentBlock>
     */
    private function queryBlocks(ClassSchedule $schedule, int $yearId, ?int $trimesterId): Builder
    {
        $query = AssessmentBlock::where('year_id', $yearId)
            ->where('subject_id', $schedule->subject_id)
            ->where('grade_id', $schedule->grade_id)
            ->where(function ($q) use ($schedule) {
                $q->where('teacher_id', $schedule->teacher_id)
                    ->orWhereNull('teacher_id');
            })
            ->with('activities');

        if ($trimesterId) {
            $query->where('trimester_id', $trimesterId);
        }

        return $query->orderBy('order')->orderBy('created_at');
    }

    private function resolveTrimesterId(ClassSchedule $schedule, int $yearId): ?int
    {
        if ($schedule->trimester_id) {
            return $schedule->trimester_id;
        }

        $activePeriod = AcademicPeriod::where('year_id', $yearId)
            ->where('status', 1)
            ->where('is_supletorio', false)
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->first();

        return $activePeriod?->id;
    }

    public function loadGradesForActivity(int $activityId, array $students): array
    {
        $studentIds = collect($students)->pluck('id')->toArray();

        $existingGrades = ActivityGrade::where('activity_id', $activityId)
            ->whereIn('student_id', $studentIds)
            ->pluck('grade', 'student_id')
            ->toArray();

        return collect($students)
            ->mapWithKeys(fn ($s) => [$s['id'] => $existingGrades[$s['id']] ?? ''])
            ->toArray();
    }

    public function getPaginatedStudents(array $students, int $page): array
    {
        $start = $page * self::MODAL_PAGE_SIZE;

        return array_slice($students, $start, self::MODAL_PAGE_SIZE);
    }

    public function getTotalPages(int $totalStudents): int
    {
        return max(1, (int) ceil($totalStudents / self::MODAL_PAGE_SIZE));
    }

    public function saveQuickGrades(int $activityId, array $values, int $userId): void
    {
        $studentIds = array_values(array_unique(array_map('intval', array_keys($values))));

        $existing = $studentIds === []
            ? collect()
            : ActivityGrade::query()
                ->where('activity_id', $activityId)
                ->whereIn('student_id', $studentIds)
                ->get()
                ->keyBy('student_id');

        $changedMap = [];
        $rows = [];
        $now = now();

        foreach ($values as $studentId => $value) {
            $studentIdVal = (int) $studentId;
            $grade = $value !== '' ? min(max((float) $value, 0), 10) : null;
            $row = $existing->get($studentIdVal);

            if ($row !== null) {
                $sameGrade = $row->grade === null && $grade === null
                    || $row->grade !== null && $grade !== null && abs((float) $row->grade - $grade) < 0.001;

                if (! $sameGrade || (int) $row->recorded_by !== $userId) {
                    $changedMap[$studentIdVal] = [
                        'grade' => $grade,
                        'recorded_by' => $userId,
                    ];
                }

                continue;
            }

            $rows[] = [
                'activity_id' => $activityId,
                'student_id' => $studentIdVal,
                'grade' => $grade,
                'recorded_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($changedMap !== []) {
            $gradeBy = [];
            $recordedByBy = [];

            foreach ($changedMap as $studentIdVal => $valueRow) {
                $gradeBy[$studentIdVal] = $valueRow['grade'];
                $recordedByBy[$studentIdVal] = $valueRow['recorded_by'];
            }

            BulkWrite::caseUpdate(
                ActivityGrade::query()->where('activity_id', $activityId),
                'student_id',
                [
                    'grade' => $gradeBy,
                    'recorded_by' => $recordedByBy,
                ],
            );
        }

        if ($rows !== []) {
            BulkWrite::insertBatch(ActivityGrade::class, $rows, function (array $row): void {
                $rowGrade = $row['grade'];
                $rowRecordedBy = $row['recorded_by'];

                $active = ActivityGrade::query()
                    ->where('activity_id', $row['activity_id'])
                    ->where('student_id', $row['student_id'])
                    ->first();

                if ($active !== null) {
                    $active->update(['grade' => $rowGrade, 'recorded_by' => $rowRecordedBy]);

                    return;
                }

                $trashed = ActivityGrade::withTrashed()
                    ->where('activity_id', $row['activity_id'])
                    ->where('student_id', $row['student_id'])
                    ->first();

                if ($trashed !== null) {
                    $trashed->restore();
                    $trashed->update(['grade' => $rowGrade, 'recorded_by' => $rowRecordedBy]);
                }
            });
        }
    }
}
