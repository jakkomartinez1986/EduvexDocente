<?php

declare(strict_types=1);

namespace App\Actions\Academic;

use App\Jobs\RecalculateCourseAverages;
use App\Models\Academic\GradeBook\Summaries\Subjects\Activity;
use App\Models\Academic\GradeBook\Summaries\Subjects\ActivityGrade;
use App\Models\Academic\GradeBook\Summaries\Subjects\AssessmentBlock;
use App\Models\Identity\Users\Teacher;
use App\Models\Management\Enrollments\StudentEnrollment;
use App\Models\StudentManagement\Academics\HomeworkPending;
use App\Services\TeacherManagement\GradebookCache;

final class SaveGradeAction
{
    public function __construct(private readonly GradebookCache $gradebookCache) {}

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

        $block = $this->syncHomeworkPending($activityId, $studentId, $grade);

        $this->gradebookCache->forgetForActivity($activityId);

        if ($block !== null) {
            RecalculateCourseAverages::dispatch(
                (int) $block->year_id,
                (int) $block->subject_id,
                (int) $block->grade_id,
                (int) $block->teacher_id,
                (int) $block->trimester_id,
            );
        }
    }

    private function syncHomeworkPending(int $activityId, ?int $studentId, mixed $grade): ?AssessmentBlock
    {
        $activity = Activity::with('assessmentBlock')->find($activityId);
        if (! $activity || ! $activity->assessmentBlock) {
            return null;
        }

        $block = $activity->assessmentBlock;

        $teacherId = Teacher::where('user_id', auth()->id())->first()?->id;

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
                        'teacher_id' => $teacherId,
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

        return $block;
    }
}
