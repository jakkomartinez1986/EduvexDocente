<?php

declare(strict_types=1);

namespace App\Services\Academic;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Caché de PDFs de reportes de calificaciones.
 *
 * Guarda el binario del PDF para evitar recomputar datos pesados y
 * re-renderizar la vista en cada descarga.
 *
 * - TTL corto (15 min) para no servir datos obsoletos.
 * - Anti-stampede: lock para que solo un request regenere.
 * - Invalidación "versioned": cada dimensión (teacher, student, subject+grade)
 *   tiene un contador de versión; al invalidar se incrementa, forzando que la
 *   clave del PDF cambie y se regenere. Funciona con cualquier driver de caché.
 */
final class PdfReportCache
{
    private const TTL_SECONDS = 900;

    /**
     * La clave del PDF combina el tipo, los parámetros y las versiones de las
     * dimensiones que pueden invalidarlo.
     */
    public function key(string $type, array $params, array $buckets = []): string
    {
        $versions = [];
        foreach ($buckets as $bucket) {
            $versions[$bucket] = $this->version($bucket);
        }

        $raw = $type.':'.json_encode($params, JSON_THROW_ON_ERROR).':'.implode('|', $versions);

        return 'pdf-report:'.hash('xxh128', $raw);
    }

    /**
     * Sirve de caché o genera y almacena el PDF.
     *
     * @param  Closure(): string  $render  Debe retornar el contenido binario del PDF.
     */
    public function remember(string $type, array $params, array $buckets, Closure $render): string
    {
        $key = $this->key($type, $params, $buckets);

        return Cache::remember(
            $key,
            now()->addSeconds(self::TTL_SECONDS),
            function () use ($key, $render) {
                return Cache::lock($key.':lock', 30)
                    ->block(10, fn (): string => $render());
            },
        );
    }

    /**
     * Invalida el caché de reportes sumando 1 a la versión del bucket dado.
     *
     * El contador de versiones nunca expira y además se ancla al timestamp:
     * si el driver evicta la clave (cache:clear, TTL de driver, etc.) la
     * próxima invalidación re-sembra un valor SIEMPRE mayor que cualquier
     * versión usada antes. Así el nombre de archivo nunca se repite con uno
     * que todavía viva en el Object Storage y el flujo async no re-sirve un
     * PDF obsoleto (ready() → URL firmada).
     */
    public function invalidate(string $bucket): void
    {
        $versionKey = $this->versionKey($bucket);
        $current = max((int) Cache::get($versionKey, 1), now()->getTimestamp());

        Cache::put($versionKey, $current + 1);
    }

    public function invalidateForTeacher(int $teacherId): void
    {
        $this->invalidate('teacher:'.$teacherId);
    }

    public function invalidateForStudent(int $studentId): void
    {
        $this->invalidate('student:'.$studentId);
    }

    public function invalidateForSubjectGrade(int $subjectId, int $gradeId): void
    {
        $this->invalidate("subject-grade:{$subjectId}:{$gradeId}");
    }

    /**
     * Retorna la versión actual de un bucket (inicia en 1).
     */
    public function version(string $bucket): string
    {
        return (string) (Cache::get($this->versionKey($bucket), 1));
    }

    private function versionKey(string $bucket): string
    {
        return 'pdf-report:version:'.$bucket;
    }
}
