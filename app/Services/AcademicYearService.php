<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting\YearSettings\ScolarYear;
use Illuminate\Support\Facades\Cache;

/**
 * Resolución del año lectivo activo con caché de 1 día.
 *
 * El lookup se recalcula en cada request en muchos servicios (gradebook,
 * configuration, sync). La clave se documenta en cache-strategy.md §3 y se
 * invalida on save vía AcademicYearCacheObserver.
 *
 * NOTA: la caché almacena SOLO el id (valor primitivo), nunca el modelo
 * Eloquent. Cachear modelos serializados en la caché de archivos produce
 * __PHP_Incomplete_Class al deserializar (ver TypeError en
 * getActiveYear), así que el modelo se resuelve por id tras leer la caché.
 */
class AcademicYearService
{
    public function getActiveYear(): ?ScolarYear
    {
        $yearId = $this->getActiveYearId();

        return $yearId !== null ? ScolarYear::find($yearId) : null;
    }

    public function getActiveYearId(): ?int
    {
        $id = Cache::remember(
            static::cacheKey(),
            now()->addDay(),
            fn (): ?int => $this->resolveActiveYearId(),
        );

        return $id !== null ? (int) $id : null;
    }

    private function resolveActiveYearId(): ?int
    {
        $id = ScolarYear::query()
            ->where('status', true)
            ->latest('year_name')
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    public static function cacheKey(): string
    {
        return 'eduvex:'.app()->environment().':academic:active-year';
    }
}
