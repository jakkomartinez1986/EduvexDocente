<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\StudentManagement\Academics\AcademicNotification;
use App\Services\TeacherManagement\NotificationStatsCache;

/**
 * Invalida la caché de estadísticas de notificaciones cuando cambia una
 * AcademicNotification (creada, actualizada, eliminada o restaurada). El bump
 * de generación deja obsoletas todas las variantes de filtro a la vez, sin
 * depender del TTL (cache-strategy.md §3 "Stats de notificaciones": on send).
 */
class NotificationCacheObserver
{
    public function saved(AcademicNotification $notification): void
    {
        $this->invalidateStats($notification);
    }

    public function deleted(AcademicNotification $notification): void
    {
        $this->invalidateStats($notification);
    }

    public function restored(AcademicNotification $notification): void
    {
        $this->invalidateStats($notification);
    }

    private function invalidateStats(AcademicNotification $notification): void
    {
        if ($notification->year_id === null) {
            return;
        }

        NotificationStatsCache::invalidate(
            (int) $notification->year_id,
            $notification->teacher_id !== null ? (int) $notification->teacher_id : null,
        );
    }
}
