<?php

declare(strict_types=1);

namespace App\Services\Api\V1\TeacherManagement;

use App\Http\Resources\Api\V1\TeacherManagement\AttendanceResource;
use App\Http\Resources\Api\V1\TeacherManagement\ClassObservationResource;
use App\Models\Identity\Users\Teacher;
use App\Models\TeacherManagement\Academics\ClassSchedule;
use App\Models\TeacherManagement\Attendances\Attendance;
use App\Models\TeacherManagement\Attendances\ClassObservation;
use App\Services\AcademicYearService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Descarga las asistencias y observaciones de clase ya registradas del
 * docente para que el cliente offline pueda replicarlas y sincronizar.
 */
final class AttendanceDownloadService
{
    public function __construct(private readonly AcademicYearService $academicYearService) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function download(Teacher $teacher, array $filters): array
    {
        $yearId = $filters['year_id'] ?? $this->academicYearService->getActiveYearId();

        if ($yearId === null) {
            return [
                'year_id' => null,
                'generated_at' => now()->toISOString(),
                'observations' => [],
                'attendances' => [],
                'pagination' => null,
            ];
        }

        $yearId = (int) $yearId;

        $scheduleIds = ClassSchedule::query()
            ->where('teacher_id', $teacher->id)
            ->where('year_id', $yearId)
            ->pluck('id');

        if ($scheduleIds->isEmpty()) {
            return [
                'year_id' => $yearId,
                'generated_at' => now()->toISOString(),
                'observations' => [],
                'attendances' => [],
                'pagination' => null,
            ];
        }

        $limit = isset($filters['limit']) ? (int) $filters['limit'] : null;
        $offset = (int) ($filters['offset'] ?? 0);

        $observations = $this->paged(
            ClassObservation::query()
                ->whereIn('class_schedule_id', $scheduleIds)
                ->when($filters['schedule_id'] ?? null, fn ($query, $value) => $query->where('class_schedule_id', $value))
                ->when($filters['date'] ?? null, fn ($query, $value) => $query->whereDate('observation_date', $value))
                ->orderBy('observation_date'),
            $limit,
            $offset,
        );

        $attendances = $this->paged(
            Attendance::query()
                ->whereIn('class_schedule_id', $scheduleIds)
                ->when($filters['schedule_id'] ?? null, fn ($query, $value) => $query->where('class_schedule_id', $value))
                ->when($filters['date'] ?? null, fn ($query, $value) => $query->whereDate('date', $value))
                ->orderBy('date'),
            $limit,
            $offset,
        );

        return [
            'year_id' => $yearId,
            'generated_at' => now()->toISOString(),
            'observations' => ClassObservationResource::collection($observations['rows']),
            'attendances' => AttendanceResource::collection($attendances['rows']),
            'pagination' => $limit === null ? null : [
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => $observations['has_more'] || $attendances['has_more'],
            ],
        ];
    }

    /**
     * Aplica la paginación opcional (limit/offset) devolviendo las filas de la
     * página y si quedan más. Para detectar `has_more` se consulta `limit + 1`
     * y se descarta la fila extra, evitando un COUNT adicional.
     *
     * @param  Builder  $query
     * @return array{rows: Collection, has_more: bool}
     */
    private function paged($query, ?int $limit, int $offset): array
    {
        if ($limit === null) {
            return ['rows' => $query->get(), 'has_more' => false];
        }

        $rows = $query
            ->offset($offset)
            ->limit($limit + 1)
            ->get();

        $hasMore = $rows->count() > $limit;

        return ['rows' => $rows->take($limit), 'has_more' => $hasMore];
    }
}
