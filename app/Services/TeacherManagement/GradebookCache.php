<?php

declare(strict_types=1);

namespace App\Services\TeacherManagement;

use App\Models\Academic\GradeBook\Summaries\Subjects\AssessmentBlock;
use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Caché de los agregados del libro de calificaciones (cache-strategy.md §3-§4).
 *
 * - Clave por curso/período: `gradebook:{year}:{subject}:{grade}:{teacher}:{trimester}`.
 * - TTL corto (5 min) para no servir datos obsoletos de calificaciones.
 * - Anti-stampede: la regeneración se protege con un lock Redis de Laravel de
 *   forma que solo un request recomputa cuando la clave expiró; el resto
 *   espera y devuelve el valor recién generado.
 * - Invalida on write: los Actions de guardar notas llaman a
 *   forgetForActivity()/, que deriva la clave desde el bloque de la actividad.
 */
final class GradebookCache
{
    private const TTL_SECONDS = 300;

    /**
     * Clave de caché para los agregados de una clase/período.
     */
    public function key(int $yearId, int $subjectId, int $gradeId, int $teacherId, int $trimesterId): string
    {
        return "gradebook:{$yearId}:{$subjectId}:{$gradeId}:{$teacherId}:{$trimesterId}";
    }

    /**
     * Regenera (o sirve de caché) los agregados de una clase de un trimestre.
     * Solo un request regenera en caso de expiración (anti-stampede).
     *
     * @param  Closure(): array<string, array{student_id:int, avg:float|null}>  $compute
     * @return array<int, float|null> clave = student_id, valor = promedio
     */
    public function averages(
        int $yearId,
        int $subjectId,
        int $gradeId,
        int $teacherId,
        int $trimesterId,
        Closure $compute,
    ): array {
        $key = $this->key($yearId, $subjectId, $gradeId, $teacherId, $trimesterId);

        return Cache::remember(
            $key,
            now()->addSeconds(self::TTL_SECONDS),
            function () use ($key, $compute) {
                return Cache::lock($key.':lock', 30)
                    ->block(10, fn (): array => $compute());
            },
        );
    }

    /**
     * Invalida la caché de la clase a la que pertenece una actividad (por su
     * bloque de evaluación). Se llama al guardar notas.
     */
    public function forgetForActivity(int $activityId): void
    {
        $block = AssessmentBlock::query()
            ->whereHas('activities', fn ($q) => $q->where('id', $activityId))
            ->first();

        if ($block === null) {
            return;
        }

        $this->forget(
            (int) $block->year_id,
            (int) $block->subject_id,
            (int) $block->grade_id,
            (int) $block->teacher_id,
            (int) $block->trimester_id,
        );
    }

    /**
     * Invalida la caché de una clase/período determinada.
     */
    public function forget(
        int $yearId,
        int $subjectId,
        int $gradeId,
        int $teacherId,
        int $trimesterId,
    ): void {
        Cache::forget($this->key($yearId, $subjectId, $gradeId, $teacherId, $trimesterId));
    }
}
