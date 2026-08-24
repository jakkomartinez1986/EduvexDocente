<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Sync\SyncTombstone;
use App\Models\TeacherManagement\Attendances\Attendance;

/**
 * Publica tombstones de sincronización cuando una asistencia se elimina
 * (soft delete al corregir a Presente). El pull incremental los entrega al
 * cliente para que borre la fila local correspondiente.
 */
class AttendanceObserver
{
    public function deleted(Attendance $attendance): void
    {
        if ($attendance->isForceDeleting()) {
            return;
        }

        SyncTombstone::updateOrCreate(
            ['entity' => 'attendance', 'entity_id' => $attendance->id],
            [
                'owner_user_id' => $attendance->recorded_by,
                'deleted_at' => now(),
            ],
        );
    }
}
