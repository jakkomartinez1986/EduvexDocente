<?php

declare(strict_types=1);

namespace App\Services\TeacherManagement;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Caché de los contadores de la pantalla de notificaciones
 * (cache-strategy.md §3 "Stats de notificaciones").
 *
 * - Clave por año + ámbito docente: `notifications:stats:{teacherId}:{yearId}:v{n}`.
 *   El ámbito es `all` para administración (sin filtro por docente) o el id del
 *   docente autenticado.
 * - Cada combinación de filtros académicos (trimestre/nivel/grado/asignatura)
 *   ocupa una variante con hash propio.
 * - Invalida on send/create: cualquier escritura sobre AcademicNotification
 *   dispara NotificationCacheObserver, que incrementa una generación por ámbito
 *   y deja obsoletas TODAS las variantes a la vez (sin tags no hay wildcard forget).
 * - TTL corto (5 min) y regeneración protegida con lock anti-stampede.
 * - Solo se cachean valores primitivos (contadores), nunca modelos Eloquent.
 */
final class NotificationStatsCache
{
    private const TTL_SECONDS = 300;

    /**
     * @param  array<string, int|string|null>  $academicFilters
     * @param  Closure(): array<string, int>  $compute
     * @return array<string, int>
     */
    public function stats(int $yearId, ?int $teacherId, array $academicFilters, Closure $compute): array
    {
        $key = $this->key($yearId, $teacherId, $academicFilters);

        return Cache::remember(
            $key,
            now()->addSeconds(self::TTL_SECONDS),
            function () use ($key, $compute): array {
                return Cache::lock($key.':lock', 30)
                    ->block(10, fn (): array => $compute());
            },
        );
    }

    /**
     * @param  array<string, int|string|null>  $academicFilters
     */
    public function key(int $yearId, ?int $teacherId, array $academicFilters): string
    {
        $generation = (int) Cache::get(self::generationKey($yearId, $teacherId), 0);

        return 'eduvex:'.app()->environment().':notifications:stats:'
            .($teacherId ?? 'all').':'.$yearId.':v'.$generation.':'.md5(serialize($academicFilters));
    }

    /**
     * Invalida todas las variantes de filtro de un ámbito (año + vista docente)
     * incrementando la generación. La vista administración (`all`) se invalida
     * siempre, y la del docente afectado además cuando existe.
     */
    public static function invalidate(int $yearId, ?int $teacherId): void
    {
        $scopes = array_unique([null, $teacherId !== null && $teacherId !== 0 ? $teacherId : null]);

        foreach ($scopes as $scope) {
            $key = self::generationKey($yearId, $scope);

            Cache::add($key, 0);

            Cache::increment($key);
        }
    }

    private static function generationKey(int $yearId, ?int $teacherId): string
    {
        return 'eduvex:'.app()->environment().':notifications:stats:generation:'
            .$yearId.':'.($teacherId ?? 'all');
    }
}
