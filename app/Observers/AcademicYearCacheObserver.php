<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Setting\YearSettings\AcademicPeriod;
use App\Models\Setting\YearSettings\ScolarYear;
use App\Services\AcademicYearService;
use Illuminate\Support\Facades\Cache;

/**
 * Invalida la caché del año lectivo activo cuando cambia un ScolarYear o un
 * AcademicPeriod (creado/actualizado/eliminado). Sin esta invalidación push,
 * un nuevo año activo tardaría hasta 1 día en reflejarse en los servicios
 * que resuelven el año activo (cache-strategy.md §3, fila "active-year").
 */
class AcademicYearCacheObserver
{
    public function saved(ScolarYear|AcademicPeriod $model): void
    {
        Cache::forget(AcademicYearService::cacheKey());
    }

    public function deleted(ScolarYear|AcademicPeriod $model): void
    {
        Cache::forget(AcademicYearService::cacheKey());
    }

    public function restored(ScolarYear|AcademicPeriod $model): void
    {
        Cache::forget(AcademicYearService::cacheKey());
    }
}
