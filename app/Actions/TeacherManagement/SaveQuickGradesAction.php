<?php

declare(strict_types=1);

namespace App\Actions\TeacherManagement;

use App\Models\Academic\GradeBook\Summaries\Subjects\ActivityGrade;
use App\Services\TeacherManagement\GradebookCache;

final class SaveQuickGradesAction
{
    /**
     * Guarda el rango de notas de la actividad en lote: una sola lectura de
     * las filas existentes y un único INSERT para las nuevas (≈2 consultas
     * estables en lugar de 2N en N+1 de updateOrCreate).
     *
     * @param  array<int, string>  $values  clave = student_id, valor = nota o ''
     */
    public function handle(
        int $activityId,
        array $values,
        int $userId,
        ?GradebookCache $gradebookCache = null,
    ): void {
        $gradebookCache ??= app(GradebookCache::class);
        $studentIds = array_map('intval', array_keys($values));

        $existing = $studentIds === []
            ? collect()
            : ActivityGrade::query()
                ->where('activity_id', $activityId)
                ->whereIn('student_id', $studentIds)
                ->get()
                ->keyBy('student_id');

        $rows = [];
        $now = now();

        foreach ($values as $studentId => $value) {
            $grade = $value !== '' ? min(max((float) $value, 0), 10) : null;
            $row = $existing->get((int) $studentId);

            if ($row !== null) {
                $row->update(['grade' => $grade, 'recorded_by' => $userId]);

                continue;
            }

            $rows[] = [
                'activity_id' => $activityId,
                'student_id' => (int) $studentId,
                'grade' => $grade,
                'recorded_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            ActivityGrade::insert($rows);
        }

        $gradebookCache->forgetForActivity($activityId);
    }
}
