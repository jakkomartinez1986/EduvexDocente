<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Resources\Api\V1\Setting\AreaResource;
use App\Http\Resources\Api\V1\Setting\ClassroomResource;
use App\Http\Resources\Api\V1\Setting\GradeResource;
use App\Http\Resources\Api\V1\Setting\NivelResource;
use App\Http\Resources\Api\V1\Setting\ShiftResource;
use App\Http\Resources\Api\V1\Setting\SubjectResource;
use App\Models\Setting\EducationalSettings\Area;
use App\Models\Setting\EducationalSettings\Classroom;
use App\Models\Setting\EducationalSettings\Grade;
use App\Models\Setting\EducationalSettings\Nivel;
use App\Models\Setting\EducationalSettings\Shift;
use App\Models\Setting\EducationalSettings\Subject;
use Illuminate\Support\Facades\Cache;

/**
 * Catálogos estáticos de la institución (turnos, niveles, grados, áreas,
 * materias, aulas) con caché de 24 h.
 *
 * La clave se documenta en cache-strategy.md §3 (fila "catálogos estáticos").
 * Se invalida on save vía StaticCatalogCacheObserver.
 *
 * NOTA: solo se cachean ARRAYS serializables (resultado de toArray de los
 * recursos), nunca modelos Eloquent ni objetos Resource — evita
 * __PHP_Incomplete_Class en la caché de archivos.
 *
 * @return array<string, list<array<string, mixed>>>
 */
class StaticCatalogService
{
    public function catalogs(): array
    {
        return Cache::remember(
            static::cacheKey(),
            now()->addDay(),
            fn (): array => [
                'shifts' => ShiftResource::collection(Shift::where('status', 1)->orderBy('shift_name')->get())->toArray(request()),
                'nivels' => NivelResource::collection(Nivel::where('status', 1)->orderBy('nivel_name')->get())->toArray(request()),
                'grades' => GradeResource::collection(Grade::where('status', 1)->orderBy('grade_name')->get())->toArray(request()),
                'areas' => AreaResource::collection(Area::orderBy('area_name')->get())->toArray(request()),
                'subjects' => SubjectResource::collection(Subject::orderBy('subject_name')->get())->toArray(request()),
                'classrooms' => ClassroomResource::collection(Classroom::where('status', 1)->orderBy('classroom_name')->get())->toArray(request()),
            ],
        );
    }

    public static function cacheKey(): string
    {
        return 'eduvex:'.app()->environment().':catalog:static';
    }
}
