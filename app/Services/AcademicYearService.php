<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting\YearSettings\ScolarYear;
use Illuminate\Support\Facades\Cache;

/**
 * Resolución del año lectivo activo con caché de 1 día.
 *
 * El lookup se recalcula en cada request en muchos servicios (gradebook,
 * configuration, sync). La caché usa la clave documentada en
 * cache-strategy.md §3 y se invalida on save vía AcademicYearCacheObserver.
 */
class AcademicYearService
{
    public function getActiveYear(): ?ScolarYear
    {
        return Cache::remember(
            static::cacheKey(),
            now()->addDay(),
            fn (): ?ScolarYear => ScolarYear::where('status', true)
                ->latest('year_name')
                ->first(),
        );
    }

    public function getActiveYearId(): ?int
    {
        return $this->getActiveYear()?->id;
    }

    public static function cacheKey(): string
    {
        return 'eduvex:'.app()->environment().':academic:active-year';
    }
}
