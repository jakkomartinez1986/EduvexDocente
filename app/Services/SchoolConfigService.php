<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting\EducationalSettings\School;
use Illuminate\Support\Facades\Cache;

/**
 * Resolución de la escuela activa con caché de 24 h.
 *
 * El lookup `School::where('status', 1)->first()` se repite en decenas de
 * renders/PDFs (gradebook, carnet, incidents, dashboard, API config). La clave
 * se documenta en cache-strategy.md §3 y se invalida on save vía
 * SchoolCacheObserver.
 *
 * NOTA: la caché almacena SOLO el id (valor primitivo), nunca el modelo
 * Eloquent (cachear modelos en la caché de archivos produce
 * __PHP_Incomplete_Class al deserializar). El modelo se resuelve por id.
 */
class SchoolConfigService
{
    public function getActiveSchool(): ?School
    {
        $schoolId = $this->getActiveSchoolId();

        $school = $schoolId !== null ? School::find($schoolId) : null;

        if ($school === null || ! $school->isActive()) {
            // La caché puede quedar huérfana si la escuela se eliminó/desactivó
            // por SQL directo (migrate:fresh --seed, imports) sin disparar el
            // observer. En ese caso ignoramos el id cacheado y resolvemos el
            // activo real, dejando la caché reparada.
            Cache::forget(static::cacheKey());

            $school = School::query()
                ->where('status', 1)
                ->latest('id')
                ->first();

            if ($school !== null) {
                Cache::put(static::cacheKey(), $school->id, now()->addDay());
            }
        }

        return $school;
    }

    public function getActiveSchoolId(): ?int
    {
        return Cache::remember(
            static::cacheKey(),
            now()->addDay(),
            fn (): ?int => School::query()
                ->where('status', 1)
                ->latest('id')
                ->value('id'),
        );
    }

    public static function cacheKey(): string
    {
        return 'eduvex:'.app()->environment().':school:active-id';
    }
}
