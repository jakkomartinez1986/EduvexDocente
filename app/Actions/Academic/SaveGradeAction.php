<?php

declare(strict_types=1);

namespace App\Actions\Academic;

use App\Jobs\RecalculateCourseAverages;
use App\Jobs\SyncHomeworkPendingForActivity;
use App\Models\Academic\GradeBook\Summaries\Subjects\Activity;
use App\Models\Academic\GradeBook\Summaries\Subjects\ActivityGrade;
use App\Services\Academic\PdfReportCache;
use App\Services\TeacherManagement\GradebookCache;

final class SaveGradeAction
{
    public function __construct(
        private readonly GradebookCache $gradebookCache,
        private readonly PdfReportCache $pdfCache,
    ) {}

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

        $block = Activity::query()
            ->with('assessmentBlock')
            ->find($activityId)
            ?->assessmentBlock;

        SyncHomeworkPendingForActivity::dispatch($activityId);

        $this->gradebookCache->forgetForActivity($activityId);

        if ($block !== null) {
            $this->pdfCache->invalidateForSubjectGrade((int) $block->subject_id, (int) $block->grade_id);
            $this->pdfCache->invalidateForTeacher((int) $block->teacher_id);
            $this->pdfCache->invalidateForStudent($studentId);

            RecalculateCourseAverages::dispatch(
                (int) $block->year_id,
                (int) $block->subject_id,
                (int) $block->grade_id,
                (int) $block->teacher_id,
                (int) $block->trimester_id,
            );
        }
    }
}
