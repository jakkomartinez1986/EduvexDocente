<?php

declare(strict_types=1);

namespace App\Services\Api\V1\Setting;

use App\Http\Resources\Api\V1\Setting\ClassScheduleResource;
use App\Http\Resources\Api\V1\Setting\SchoolResource;
use App\Http\Resources\Api\V1\Setting\ScolarYearResource;
use App\Http\Resources\Api\V1\Setting\TeacherResource;
use App\Models\Identity\Users\Teacher;
use App\Models\Setting\YearSettings\ScolarYear;
use App\Models\TeacherManagement\Academics\ClassSchedule;
use App\Models\User;
use App\Services\AcademicYearService;
use App\Services\SchoolConfigService;
use App\Services\StaticCatalogService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Construye el paquete de configuración que un cliente offline (móvil o
 * escritorio) descarga para poder trabajar sin conexión en los módulos de
 * asistencia, horario docente y libro de calificaciones.
 */
final class OfflinePackageService
{
    public function __construct(private readonly AcademicYearService $academicYearService) {}

    /**
     * @return array<string, mixed>
     */
    public function build(User $user): array
    {
        $teacher = $user->teacher instanceof Teacher ? $user->teacher : null;
        $year = $this->academicYearService->getActiveYear();

        return [
            'generated_at' => now()->toISOString(),
            'school' => $this->school(),
            'school_year' => $this->schoolYear($year),
            'catalogs' => $this->catalogs(),
            'teacher' => $this->teacher($teacher),
            'schedules' => $this->schedules($teacher, $year),
        ];
    }

    private function school(): ?SchoolResource
    {
        $school = app(SchoolConfigService::class)->getActiveSchool();

        return $school ? new SchoolResource($school) : null;
    }

    private function schoolYear(?ScolarYear $year): ?ScolarYearResource
    {
        if ($year === null) {
            return null;
        }

        $year->loadMissing([
            'academicPeriods',
            'gradingSchemes',
            'calendarDays' => fn ($query) => $query->orderBy('date'),
        ]);

        return new ScolarYearResource($year);
    }

    /**
     * Catálogos estáticos de la institución, cacheados 24 h
     * (StaticCatalogService, invalidado on save por StaticCatalogCacheObserver).
     *
     * @return array<string, mixed>
     */
    private function catalogs(): array
    {
        return app(StaticCatalogService::class)->catalogs();
    }

    private function teacher(?Teacher $teacher): ?TeacherResource
    {
        if ($teacher === null) {
            return null;
        }

        return new TeacherResource($teacher->loadMissing('user'));
    }

    private function schedules(?Teacher $teacher, ?ScolarYear $year): AnonymousResourceCollection
    {
        if ($teacher === null || $year === null) {
            return ClassScheduleResource::collection([]);
        }

        return ClassScheduleResource::collection(
            ClassSchedule::with(['subject.area', 'grade.nivel.shift', 'trimester', 'calendarDay'])
                ->where('year_id', $year->id)
                ->where('teacher_id', $teacher->id)
                ->where('is_active', true)
                ->orderBy('day')
                ->orderBy('start_time')
                ->get(),
        );
    }
}
