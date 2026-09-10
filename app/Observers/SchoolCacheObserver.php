<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Setting\EducationalSettings\School;
use App\Services\SchoolConfigService;
use Illuminate\Support\Facades\Cache;

/**
 * Invalida la caché de la escuela activa cuando cambia (creado/actualizado/
 * eliminado/restaurado). Sin esta invalidación push, un cambio de estado o
 * de escuela tardaría hasta 24 h en reflejarse en los PDFs y renders que
 * resuelven la escuela activa (cache-strategy.md §3, fila "school config").
 */
class SchoolCacheObserver
{
    public function saved(School $school): void
    {
        Cache::forget(SchoolConfigService::cacheKey());
    }

    public function deleted(School $school): void
    {
        Cache::forget(SchoolConfigService::cacheKey());
    }

    public function restored(School $school): void
    {
        Cache::forget(SchoolConfigService::cacheKey());
    }
}
