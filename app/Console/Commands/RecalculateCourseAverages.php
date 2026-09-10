<?php

namespace App\Console\Commands;

use App\Jobs\RecalculateCourseAverages as RecalculateCourseAveragesJob;
use App\Models\Setting\YearSettings\AcademicPeriod;
use App\Services\AcademicYearService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('gradebook:recalculate')]
#[Description('Recalcula y calienta los promedios del libro de calificaciones de todas las clases del año lectivo activo (jobs idempotentes). Uso al cierre de trimestre o tras escrituras masivas.')]
class RecalculateCourseAverages extends Command
{
    public function handle(AcademicYearService $yearService): int
    {
        $yearId = $yearService->getActiveYearId();

        if ($yearId === null) {
            $this->warn('No hay año lectivo activo. Nada que recalcular.');

            return self::SUCCESS;
        }

        $trimesterIds = AcademicPeriod::query()
            ->where('year_id', $yearId)
            ->where('status', 1)
            ->where('is_supletorio', false)
            ->pluck('id')
            ->all();

        if ($trimesterIds === []) {
            $this->warn('El año lectivo no tiene trimestres activos. Nada que recalcular.');

            return self::SUCCESS;
        }

        $courses = DB::table('class_schedules')
            ->where('year_id', $yearId)
            ->whereNull('deleted_at')
            ->select('teacher_id', 'subject_id', 'grade_id')
            ->distinct()
            ->get();

        $count = 0;

        foreach ($courses as $course) {
            foreach ($trimesterIds as $trimesterId) {
                RecalculateCourseAveragesJob::dispatch(
                    $yearId,
                    (int) $course->subject_id,
                    (int) $course->grade_id,
                    (int) $course->teacher_id,
                    $trimesterId,
                );
                $count++;
            }
        }

        $this->info("Despachados {$count} job(s) de recálculo de promedios.");

        return self::SUCCESS;
    }
}
