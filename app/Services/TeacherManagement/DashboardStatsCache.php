<?php

declare(strict_types=1);

namespace App\Services\TeacherManagement;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Caché breve de las estadísticas del dashboard del docente (no tutor).
 *
 * - Clave por docente/año lectivo: `dashboard:stats:{teacherId}:{yearId}`.
 * - TTL corto (5 min) y regeneración protegida con lock anti-stampede
 *   (cache-strategy.md §3 "Dashboard de estadísticas", §4).
 * - Estas estadísticas son agregados de conteo que cambian con notas,
 *   asistencias, tareas y notificaciones; la estrategia es "calculada"
 *   (TTL) y no exige invalidación push.
 */
final class DashboardStatsCache
{
    private const TTL_SECONDS = 300;

    /**
     * @param  Closure(): array<string, int>  $compute
     * @return array<string, int>
     */
    public function counts(int $teacherId, int $yearId, Closure $compute): array
    {
        $key = $this->key($teacherId, $yearId);

        return Cache::remember(
            $key,
            now()->addSeconds(self::TTL_SECONDS),
            function () use ($key, $compute): array {
                return Cache::lock($key.':lock', 30)
                    ->block(10, fn (): array => $compute());
            },
        );
    }

    public function key(int $teacherId, int $yearId): string
    {
        return "dashboard:stats:{$teacherId}:{$yearId}";
    }

    public function forget(int $teacherId, int $yearId): void
    {
        Cache::forget($this->key($teacherId, $yearId));
    }
}
