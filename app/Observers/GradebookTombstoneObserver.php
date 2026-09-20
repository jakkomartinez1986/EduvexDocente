<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Academic\GradeBook\Summaries\Subjects\Activity;
use App\Models\Academic\GradeBook\Summaries\Subjects\ActivityGrade;
use App\Models\Academic\GradeBook\Summaries\Subjects\AssessmentBlock;
use App\Models\Sync\SyncTombstone;
use Illuminate\Database\Eloquent\Model;

/**
 * Publica tombstones de sincronización cuando se elimina estructura del
 * libro de calificaciones (soft delete de bloque/actividad/nota). El pull
 * incremental de gradebook los entrega al cliente para que borre las filas
 * locales. Al borrar un bloque se eliminan en cascada sus actividades y las
 * notas de cada actividad (mismo patrón soft), de modo que el pull no siga
 * entregando filas huérfanas como upserts.
 */
class GradebookTombstoneObserver
{
    public function deleted(Model $model): void
    {
        if ($model->isForceDeleting()) {
            return;
        }

        if ($model instanceof AssessmentBlock) {
            $model->activities()->get()->each(
                fn (Activity $activity): bool => (bool) $activity->delete(),
            );

            SyncTombstone::updateOrCreate(
                ['entity' => 'assessment_block', 'entity_id' => $model->id],
                [
                    'owner_user_id' => $model->teacher?->user_id,
                    'deleted_at' => now(),
                ],
            );

            return;
        }

        if ($model instanceof Activity) {
            $model->activityGrades()->get()->each(
                fn (ActivityGrade $grade): bool => (bool) $grade->delete(),
            );

            SyncTombstone::updateOrCreate(
                ['entity' => 'activity', 'entity_id' => $model->id],
                [
                    'owner_user_id' => $model->assessmentBlock?->teacher?->user_id,
                    'deleted_at' => now(),
                ],
            );

            return;
        }

        if ($model instanceof ActivityGrade) {
            SyncTombstone::updateOrCreate(
                ['entity' => 'activity_grade', 'entity_id' => $model->id],
                [
                    'owner_user_id' => $model->recorded_by,
                    'deleted_at' => now(),
                ],
            );
        }
    }
}
