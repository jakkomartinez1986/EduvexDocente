<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Setting\EducationalSettings\Area;
use App\Models\Setting\EducationalSettings\Classroom;
use App\Models\Setting\EducationalSettings\Grade;
use App\Models\Setting\EducationalSettings\Nivel;
use App\Models\Setting\EducationalSettings\Shift;
use App\Models\Setting\EducationalSettings\Subject;
use App\Services\StaticCatalogService;
use Illuminate\Support\Facades\Cache;

/**
 * Invalida la caché del catálogo estático cuando cambia alguno de los modelos
 * maestros que lo componen (turnos, niveles, grados, áreas, materias, aulas).
 * Sin esta invalidación push, un cambio tardaría hasta 24 h en reflejarse en
 * el paquete offline del cliente (cache-strategy.md §3, fila "catálogos").
 */
class StaticCatalogCacheObserver
{
    public function saved(Shift|Nivel|Grade|Area|Subject|Classroom $model): void
    {
        Cache::forget(StaticCatalogService::cacheKey());
    }

    public function deleted(Shift|Nivel|Grade|Area|Subject|Classroom $model): void
    {
        Cache::forget(StaticCatalogService::cacheKey());
    }

    public function restored(Shift|Nivel|Grade|Area|Subject|Classroom $model): void
    {
        Cache::forget(StaticCatalogService::cacheKey());
    }
}
