<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\TeacherManagement\Academics\ClassSchedule;
use App\Services\TeacherManagement\TeacherCoursesCache;

/**
 * Invalida la caché de cursos del docente (TeacherCoursesCache) cuando cambia
 * un horario. El bump de generación deja obsoletas todas las variantes del
 * docente/año afectado sin depender del TTL de 24 h
 * (cache-strategy.md §3 "cursos del docente": on save schedule).
 */
class ClassScheduleCacheObserver
{
    public function saved(ClassSchedule $schedule): void
    {
        $this->invalidate($schedule);
    }

    public function deleted(ClassSchedule $schedule): void
    {
        $this->invalidate($schedule);
    }

    public function restored(ClassSchedule $schedule): void
    {
        $this->invalidate($schedule);
    }

    private function invalidate(ClassSchedule $schedule): void
    {
        if ($schedule->teacher_id === null || $schedule->year_id === null) {
            return;
        }

        TeacherCoursesCache::invalidate((int) $schedule->teacher_id, (int) $schedule->year_id);
    }
}
