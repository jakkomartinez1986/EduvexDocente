<?php

declare(strict_types=1);

namespace App\Actions\TeacherManagement;

use App\Jobs\RecalculateCourseAverages;
use App\Models\Academic\GradeBook\Summaries\Subjects\Activity;
use App\Models\Academic\GradeBook\Summaries\Subjects\ActivityGrade;
use App\Services\Academic\PdfReportCache;
use App\Services\TeacherManagement\GradebookCache;
use App\Support\Database\BulkWrite;

final class SaveQuickGradesAction
{
    /**
     * Guarda el rango de notas de la actividad en ~3 consultas estables: una
     * sola lectura de las filas existentes, un único UPDATE con CASE para las
     * que cambian y un único INSERT batch para las nuevas (en lugar de 2N en
     * el N+1 de updateOrCreate, H-07).
     *
     * @param  array<int, string>  $values  clave = student_id, valor = nota o ''
     */
    public function handle(
        int $activityId,
        array $values,
        int $userId,
        ?GradebookCache $gradebookCache = null,
        ?PdfReportCache $pdfCache = null,
    ): void {
        $gradebookCache ??= app(GradebookCache::class);
        $pdfCache ??= app(PdfReportCache::class);
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

        $gradebookCache->forgetForActivity($activityId);

        $block = Activity::query()
            ->with('assessmentBlock')
            ->find($activityId)
            ?->assessmentBlock;

        if ($block !== null) {
            $pdfCache->invalidateForSubjectGrade((int) $block->subject_id, (int) $block->grade_id);
            $pdfCache->invalidateForTeacher((int) $block->teacher_id);
            foreach ($studentIds as $sid) {
                $pdfCache->invalidateForStudent($sid);
            }

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
