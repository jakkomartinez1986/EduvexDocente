<?php

declare(strict_types=1);

namespace App\Services\Api\V1\TeacherManagement;

use App\Http\Resources\Api\V1\TeacherManagement\ScheduleResource;
use App\Models\Identity\Users\Teacher;
use App\Models\TeacherManagement\Academics\ClassSchedule;
use App\Services\AcademicYearService;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Consulta del horario del docente para el año lectivo, con las mismas
 * relaciones y orden que la vista web de timeline (día y hora de inicio).
 */
final class ScheduleService
{
    public function __construct(private readonly AcademicYearService $academicYearService) {}

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function index(Teacher $teacher, array $validated): array
    {
        $yearId = $this->resolveYearId($validated);

        $schedules = $this->schedules($teacher, $yearId, $validated);

        return [
            'year_id' => $yearId,
            'generated_at' => now()->toISOString(),
            'schedules' => ScheduleResource::collection($schedules),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolveYearId(array $validated): int
    {
        if (($validated['year_id'] ?? null) !== null) {
            return (int) $validated['year_id'];
        }

        $activeYearId = $this->academicYearService->getActiveYearId();

        if ($activeYearId === null) {
            throw new NotFoundHttpException('No existe un año lectivo activo.');
        }

        return $activeYearId;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return Collection<int, ClassSchedule>
     */
    private function schedules(Teacher $teacher, int $yearId, array $validated): Collection
    {
        return ClassSchedule::query()
            ->with('subject.area', 'grade.nivel.shift', 'trimester', 'calendarDay')
            ->where('teacher_id', $teacher->id)
            ->where('year_id', $yearId)
            ->when($validated['schedule_type'] ?? null, fn ($query, $value) => $query->where('schedule_type', $value))
            ->when($validated['day'] ?? null, fn ($query, $value) => $query->where('day', $value))
            ->orderBy('day')
            ->orderBy('start_time')
            ->get();
    }
}
