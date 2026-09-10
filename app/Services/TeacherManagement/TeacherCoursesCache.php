<?php

declare(strict_types=1);

namespace App\Services\TeacherManagement;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Caché de los cursos (horarios) de un docente en el año lectivo
 * (cache-strategy.md §3 "cursos del docente", livewire-performance.md F-04).
 *
 * - Clave por docente/año: `teacher-courses:{teacherId}:{yearId}:v{generation}`.
 * - Guarda SOLO primitivos (listas de arrays descriptivos), nunca modelos
 *   Eloquent (regla del proyecto: no cachear modelos, evita __PHP_Incomplete_Class).
 * - TTL largo (24 h) con invalidación push vía ClassScheduleCacheObserver
 *   (saved/deleted/restored) que incrementa la generación y deja obsoletas
 *   todas las variantes a la vez.
 * - Regeneración protegida con lock anti-stampede.
 */
final class TeacherCoursesCache
{
    private const TTL_SECONDS = 86_400;

    /**
     * @param  Closure(): array<int, array<string, mixed>>  $compute
     * @return array<int, array<string, mixed>>
     */
    public function courses(int $teacherId, int $yearId, Closure $compute): array
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

    /**
     * Cachea un booleano transaccional (p. ej. "¿el docente da clases este año?")
     * con la misma clave por generación.
     *
     * @param  Closure(): bool  $compute
     */
    public function hasCourses(int $teacherId, int $yearId, Closure $compute): bool
    {
        $key = $this->key($teacherId, $yearId);

        return (bool) Cache::remember(
            $key.':exists',
            now()->addSeconds(self::TTL_SECONDS),
            fn (): bool => (bool) $compute(),
        );
    }

    public function key(int $teacherId, int $yearId): string
    {
        $generation = (int) Cache::get(self::generationKey($teacherId, $yearId), 0);

        return 'eduvex:'.app()->environment().':teacher-courses:'
            .$teacherId.':'.$yearId.':v'.$generation;
    }

    /**
     * Invalida todas las variantes de un docente/año incrementando la
     * generación. Lo dispara el observer de ClassSchedule.
     */
    public static function invalidate(int $teacherId, int $yearId): void
    {
        $key = self::generationKey($teacherId, $yearId);

        Cache::add($key, 0);

        Cache::increment($key);
    }

    private static function generationKey(int $teacherId, int $yearId): string
    {
        return 'eduvex:'.app()->environment().':teacher-courses:generation:'
            .$teacherId.':'.$yearId;
    }
}
