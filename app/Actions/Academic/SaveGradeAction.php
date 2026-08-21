<?php

declare(strict_types=1);

namespace App\Actions\Academic;

use App\Models\Academic\GradeBook\Summaries\Subjects\Activity;
use App\Models\Academic\GradeBook\Summaries\Subjects\ActivityGrade;
use App\Models\Management\Enrollments\StudentEnrollment;
use App\Models\StudentManagement\Academics\HomeworkPending;

final class SaveGradeAction
{
    public function __invoke(
        int $activityId,
        int $studentId,
        mixed $value,
    ): void {
        $grade = $value !== '' ? $value : null;

        if ($grade !== null) {
            $grade = min(max((float) $grade, 0), 10);
        }

        ActivityGrade::updateOrCreate(
            [
                'activity_id' => $activityId,
                'student_id' => $studentId,
            ],
            [
                'grade' => $grade,
                'recorded_by' => auth()->id(),
            ]
        );

        $this->syncHomeworkPending($activityId, $studentId, $grade);
    }

    private function syncHomeworkPending(int $activityId, ?int $studentId, mixed $grade): void
    {
        $activity = Activity::with('assessmentBlock')->find($activityId);
        if (! $activity || ! $activity->assessmentBlock) {
            return;
        }

        $block = $activity->assessmentBlock;

        $enrolledIds = StudentEnrollment::where('grade_id', $block->grade_id)
            ->where('year_id', $block->year_id)
            ->pluck('student_id')
            ->toArray();

        $allGrades = ActivityGrade::where('activity_id', $activityId)
            ->whereIn('student_id', $enrolledIds)
            ->get()
            ->keyBy('student_id');

        foreach ($enrolledIds as $sid) {
            $existing = $allGrades->get($sid);

            if (! $existing || $existing->grade === null) {
                HomeworkPending::updateOrCreate(
                    [
                        'activity_id' => $activityId,
                        'student_id' => $sid,
                    ],
                    [
                        'subject_id' => $block->subject_id,
                        'grade_id' => $block->grade_id,
                        'teacher_id' => auth()->user()->teacher?->id,
                        'year_id' => $block->year_id,
                        'trimester_id' => $block->trimester_id,
                        'description' => 'Tarea no presentada: '.$activity->name,
                        'due_date' => $activity->date ?? now(),
                        'status' => 'not_submitted',
                    ]
                );
            } else {
                HomeworkPending::where('activity_id', $activityId)
                    ->where('student_id', $sid)
                    ->where('status', 'not_submitted')
                    ->whereNull('notified_at')
                    ->update(['status' => 'submitted']);
            }
        }
    }
}
