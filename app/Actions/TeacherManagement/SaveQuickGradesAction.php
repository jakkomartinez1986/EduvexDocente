<?php

declare(strict_types=1);

namespace App\Actions\TeacherManagement;

use App\Models\Academic\GradeBook\Summaries\Subjects\ActivityGrade;

final class SaveQuickGradesAction
{
    public function handle(int $activityId, array $values, int $userId): void
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
