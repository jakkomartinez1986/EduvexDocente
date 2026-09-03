<?php

namespace App\Jobs;

use App\Services\AcademicYearService;
use App\Services\Api\V1\TeacherManagement\AttendanceSummariesRebuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Materializa los resúmenes de asistencia en la tabla `attendance_summaries`
 * (database-optimization.md §7, queue-strategy.md §3). Agrega por
 * (student_id, grade_id, trimester_id, year_id) las clases impartidas y las
 * inasistencias de todos los docentes del grado, respetando el mismo criterio
 * de conteo que AttendanceSummaryService (presentes = clases impartidas menos
 * los registros explícitos de no-asistencia). Es idempotente: hace upsert sobre
 * la clave natural.
 */
class RebuildAttendanceSummaries implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  int|null  $yearId  Año lectivo a agregar (null = año activo).
     * @param  int|null  $trimesterId  Período a agregar (null = todos del año).
     */
    public function __construct(
        public readonly ?int $yearId = null,
        public readonly ?int $trimesterId = null,
    ) {}

    public function handle(
        AttendanceSummariesRebuilder $rebuilder,
        AcademicYearService $academicYears,
    ): void {
        $yearId = $this->yearId ?? $academicYears->getActiveYearId();

        if ($yearId === null) {
            return;
        }

        $rebuilder->rebuild((int) $yearId, $this->trimesterId);
    }
}
