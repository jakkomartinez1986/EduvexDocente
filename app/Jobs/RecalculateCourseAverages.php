<?php

namespace App\Jobs;

use App\Models\Setting\YearSettings\GradingScheme;
use App\Services\TeacherManagement\CourseAveragesComputer;
use App\Services\TeacherManagement\GradebookCache;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Recálculo/calentamiento (idempotente) de los agregados de una clase en un
 * trimestre (cache-strategy.md §3). Dispare tras una escritura masiva (import,
 * recuperaciones) o al cierre de trimestre para que el gradebook y los reportes
 * sirvan promedios recién computados sin esperar a que un render re-genere la
 * clase. La fuente única de verdad es CourseAveragesComputer; el resultado se
 * vuelve a escribir en GradebookCache para reutilizarlo en el render.
 */
class RecalculateCourseAverages implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $yearId,
        public readonly int $subjectId,
        public readonly int $gradeId,
        public readonly int $teacherId,
        public readonly int $trimesterId,
    ) {}

    public function handle(
        CourseAveragesComputer $computer,
        GradebookCache $cache,
    ): void {
        $gradingScheme = GradingScheme::query()
            ->where('year_id', $this->yearId)
            ->where('status', 1)
            ->first();

        $cache->averages(
            $this->yearId,
            $this->subjectId,
            $this->gradeId,
            $this->teacherId,
            $this->trimesterId,
            fn (): array => $computer->compute(
                $this->yearId,
                $this->subjectId,
                $this->gradeId,
                $this->teacherId,
                $this->trimesterId,
                $gradingScheme,
            ),
        );
    }
}
