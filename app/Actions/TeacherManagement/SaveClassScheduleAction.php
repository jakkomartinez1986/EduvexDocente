<?php

declare(strict_types=1);

namespace App\Actions\TeacherManagement;

use App\Models\Identity\Users\Teacher;
use App\Models\Setting\EducationalSettings\Subject;
use App\Models\TeacherManagement\Academics\ClassSchedule;
use Spatie\Permission\Models\Role;

final class SaveClassScheduleAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?int $scheduleId = null): ClassSchedule
    {
        if ($scheduleId) {
            $schedule = ClassSchedule::findOrFail($scheduleId);
            $schedule->update($data);

            return $schedule;
        }

        return ClassSchedule::create($data);
    }

    public function validateIntegralSupport(
        int $teacherId,
        int $subjectId,
        int $yearId,
        int $gradeId,
        ?int $excludeScheduleId = null,
    ): bool {
        $subject = Subject::findOrFail($subjectId);
        $isIntegralSupport = $subject->subject_name === 'Acompañamiento integral en el aula';

        if (! $isIntegralSupport) {
            return false;
        }

        $existingQuery = ClassSchedule::where('teacher_id', $teacherId)
            ->where('subject_id', $subjectId)
            ->where('year_id', $yearId)
            ->where('grade_id', '!=', $gradeId);

        if ($excludeScheduleId) {
            $existingQuery->where('id', '!=', $excludeScheduleId);
        }

        return $existingQuery->exists();
    }

    public function assignTutorRoleIfNeeded(int $teacherId): void
    {
        $teacher = Teacher::findOrFail($teacherId);

        if ($teacher->user && ! $teacher->user->hasRole('TUTOR') && Role::query()->where('name', 'TUTOR')->exists()) {
            $teacher->user->assignRole('TUTOR');
        }
    }

    public function deleteSchedule(int $scheduleId): bool
    {
        $model = ClassSchedule::find($scheduleId);
        if (! $model) {
            return false;
        }
        $model->delete();

        return true;
    }
}
