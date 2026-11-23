<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\RebuildAttendanceSummaries;
use App\Models\Identity\Users\Student;
use App\Models\Management\Enrollments\StudentEnrollment;
use App\Models\TeacherManagement\Academics\ClassSchedule;
use App\Models\User;
use App\Services\Academic\PdfReportCache;
use App\Services\TeacherManagement\GradebookCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Mantiene los datos derivados coherentes con el criterio de estudiante activo
 * (matrícula vigente + cuenta activa): agregados del gradebook, reportes PDF y
 * resúmenes materializados de asistencia.
 */
final class ActiveStudentStatusObserver
{
    public function saved(Model $model): void
    {
        if (! $model->wasChanged('status')) {
            return;
        }

        $this->refresh($model);
    }

    public function deleted(Model $model): void
    {
        $this->refresh($model);
    }

    public function restored(Model $model): void
    {
        $this->refresh($model);
    }

    private function refresh(Model $model): void
    {
        foreach ($this->affectedEnrollments($model)->groupBy(fn (StudentEnrollment $enrollment): string => $enrollment->year_id.':'.$enrollment->grade_id) as $enrollments) {
            /** @var StudentEnrollment $first */
            $first = $enrollments->first();
            $yearId = (int) $first->year_id;
            $gradeId = (int) $first->grade_id;

            DB::afterCommit(function () use ($yearId, $gradeId, $enrollments): void {
                app(GradebookCache::class)->invalidateForGrade($yearId, $gradeId);

                $pdfCache = app(PdfReportCache::class);
                ClassSchedule::query()
                    ->where('year_id', $yearId)
                    ->where('grade_id', $gradeId)
                    ->pluck('subject_id')
                    ->unique()
                    ->each(fn ($subjectId) => $pdfCache->invalidateForSubjectGrade((int) $subjectId, $gradeId));

                $enrollments->pluck('student_id')->unique()
                    ->each(fn ($studentId) => $pdfCache->invalidateForStudent((int) $studentId));

                RebuildAttendanceSummaries::dispatch($yearId);
            });
        }
    }

    /**
     * @return Collection<int, StudentEnrollment>
     */
    private function affectedEnrollments(Model $model): Collection
    {
        if ($model->wasRecentlyCreated) {
            return collect();
        }

        if ($model instanceof StudentEnrollment) {
            return collect([$model]);
        }

        if ($model instanceof User) {
            $studentId = Student::query()->where('user_id', $model->getKey())->value('id');

            return $studentId === null
                ? collect()
                : StudentEnrollment::query()->where('student_id', $studentId)->get();
        }

        return collect();
    }
}
