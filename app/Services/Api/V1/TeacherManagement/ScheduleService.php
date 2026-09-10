<?php

declare(strict_types=1);

namespace App\Services\Api\V1\TeacherManagement;

use App\Actions\TeacherManagement\SaveClassScheduleAction;
use App\Http\Resources\Api\V1\TeacherManagement\ScheduleResource;
use App\Models\Identity\Users\Teacher;
use App\Models\TeacherManagement\Academics\ClassSchedule;
use App\Services\AcademicYearService;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Consulta y CRUD del horario del docente para el año lectivo, con las mismas
 * relaciones y orden que la vista web de timeline (día y hora de inicio).
 */
final class ScheduleService
{
    public function __construct(
        private readonly AcademicYearService $academicYearService,
        private readonly SaveClassScheduleAction $saveClassScheduleAction,
    ) {}

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
     */
    public function store(Teacher $teacher, array $validated): ClassSchedule
    {
        $data = $this->payload($teacher, $validated);

        $this->assertNoIntegralSupportConflict($teacher, $data);

        $schedule = $this->saveClassScheduleAction->handle($data);

        if ($schedule->subject->subject_name === 'Acompañamiento integral en el aula') {
            $this->saveClassScheduleAction->assignTutorRoleIfNeeded($teacher->id);
        }

        return $this->loadRelations($schedule);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function update(Teacher $teacher, int $scheduleId, array $validated): ClassSchedule
    {
        $schedule = $this->ownSchedule($teacher, $scheduleId);

        $data = $this->payload($teacher, $validated);

        $this->assertNoIntegralSupportConflict($teacher, $data, $scheduleId);

        $this->saveClassScheduleAction->handle($data, $scheduleId);

        return $this->loadRelations($schedule->refresh());
    }

    public function destroy(Teacher $teacher, int $scheduleId): void
    {
        $this->ownSchedule($teacher, $scheduleId)->delete();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function payload(Teacher $teacher, array $validated): array
    {
        $payload = $validated;
        $payload['teacher_id'] = $teacher->id;
        $payload['day'] = $payload['day'] === 'MIÉRCOLES' ? 'MIERCOLES' : $payload['day'];

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertNoIntegralSupportConflict(Teacher $teacher, array $data, ?int $excludeScheduleId = null): void
    {
        $conflict = $this->saveClassScheduleAction->validateIntegralSupport(
            teacherId: $teacher->id,
            subjectId: (int) $data['subject_id'],
            yearId: (int) $data['year_id'],
            gradeId: (int) $data['grade_id'],
            excludeScheduleId: $excludeScheduleId,
        );

        if ($conflict) {
            throw new ConflictHttpException('Este docente ya tiene asignada la hora de Acompañamiento integral en otro curso. Solo se permite en un curso.');
        }
    }

    private function ownSchedule(Teacher $teacher, int $scheduleId): ClassSchedule
    {
        return ClassSchedule::query()
            ->where('teacher_id', $teacher->id)
            ->findOrFail($scheduleId);
    }

    private function loadRelations(ClassSchedule $schedule): ClassSchedule
    {
        return $schedule->load('subject.area', 'grade.nivel.shift', 'trimester', 'calendarDay');
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
