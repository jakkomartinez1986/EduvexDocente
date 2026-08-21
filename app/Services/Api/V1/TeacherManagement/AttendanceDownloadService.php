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
            ];
        }

        $observations = ClassObservation::query()
            ->whereIn('class_schedule_id', $scheduleIds)
            ->when($filters['schedule_id'] ?? null, fn ($query, $value) => $query->where('class_schedule_id', $value))
            ->when($filters['date'] ?? null, fn ($query, $value) => $query->whereDate('observation_date', $value))
            ->orderBy('observation_date')
            ->get();

        $attendances = Attendance::query()
            ->whereIn('class_schedule_id', $scheduleIds)
            ->when($filters['schedule_id'] ?? null, fn ($query, $value) => $query->where('class_schedule_id', $value))
            ->when($filters['date'] ?? null, fn ($query, $value) => $query->whereDate('date', $value))
            ->orderBy('date')
            ->get();

        return [
            'year_id' => $yearId,
            'generated_at' => now()->toISOString(),
            'observations' => ClassObservationResource::collection($observations),
            'attendances' => AttendanceResource::collection($attendances),
        ];
    }
}
