<?php

declare(strict_types=1);

namespace App\Services\TeacherManagement;

use App\Models\Academic\GradeBook\Summaries\Subjects\ActivityGrade;
use App\Models\Academic\GradeBook\Summaries\Subjects\AssessmentBlock;
use App\Models\Identity\Users\Student;
use App\Models\Setting\YearSettings\AcademicPeriod;
use App\Models\TeacherManagement\Academics\ClassSchedule;

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

    public function loadActivities(int $scheduleId, int $yearId): array
    {
        $schedule = ClassSchedule::with('subject', 'grade', 'trimester')->findOrFail($scheduleId);

        $trimesterId = $schedule->trimester_id;
        if (! $trimesterId) {
            $activePeriod = AcademicPeriod::where('year_id', $yearId)
                ->where('status', 1)
                ->where('is_supletorio', false)
                ->where('start_date', '<=', now())
                ->where('end_date', '>=', now())
                ->first();
            $trimesterId = $activePeriod?->id;
        }

        $blockQuery = AssessmentBlock::where('year_id', $yearId)
            ->where('subject_id', $schedule->subject_id)
            ->where('grade_id', $schedule->grade_id)
            ->with('activities');

        if ($trimesterId) {
            $blockQuery->where('trimester_id', $trimesterId);
        }

        $block = $blockQuery->first();

        if (! $block && ! $trimesterId) {
            $block = AssessmentBlock::where('year_id', $yearId)
                ->where('subject_id', $schedule->subject_id)
                ->where('grade_id', $schedule->grade_id)
                ->with('activities')
                ->orderBy('created_at', 'desc')
                ->first();
        }

        if (! $block) {
            return [];
        }

        $allActivities = $block->activities->sortByDesc('created_at')->values()->all();

        return array_map(fn ($a) => [
            'id' => $a->id,
            'name' => $a->name,
            'date' => $a->date,
            'score' => $a->score,
        ], $allActivities);
    }

    public function getDefaultActivity(array $activities): ?array
    {
        if (empty($activities)) {
            return null;
        }

        $collection = collect($activities);

        $todayActivity = $collection->firstWhere('date', now()->toDateString())
            ?? $collection->sortByDesc('created_at')->first();

        return $todayActivity;
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
        foreach ($values as $studentId => $value) {
            $grade = $value !== '' ? min(max((float) $value, 0), 10) : null;

            ActivityGrade::updateOrCreate(
                ['activity_id' => $activityId, 'student_id' => $studentId],
                ['grade' => $grade, 'recorded_by' => $userId]
            );
        }
    }
}
