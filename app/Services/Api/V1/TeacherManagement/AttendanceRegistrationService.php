<?php

declare(strict_types=1);

namespace App\Services\Api\V1\TeacherManagement;

use App\Models\Identity\Users\Student;
use App\Models\Identity\Users\Teacher;
use App\Models\Management\Enrollments\StudentEnrollment;
use App\Models\Setting\YearSettings\CalendarDay;
use App\Models\TeacherManagement\Academics\ClassSchedule;
use App\Models\TeacherManagement\Attendances\Attendance;
use App\Models\TeacherManagement\Attendances\ClassObservation;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Registro transaccional de asistencias por clase: observación de la clase,
 * estados por estudiante y resolución del estado Presente como ausencia de
 * registro (misma semántica que la web de registro de asistencia).
 */
final class AttendanceRegistrationService
{
    /**
     * Vista previa para el registro: horario, observación existente y
     * estado actual de cada estudiante en la fecha dada.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function detail(Teacher $teacher, array $validated): array
    {
        $schedule = $this->ownSchedule($teacher, (int) $validated['schedule_id']);
        $date = (string) Carbon::parse($validated['date'])->toDateString();

        $observation = ClassObservation::query()
            ->where('class_schedule_id', $schedule->id)
            ->whereDate('observation_date', $date)
            ->first();

        $studentIds = $this->enrolledStudentIds((int) $schedule->year_id, (int) $schedule->grade_id);

        $attendances = $studentIds->isEmpty()
            ? collect()
            : Attendance::query()
                ->where('class_schedule_id', $schedule->id)
                ->whereDate('date', $date)
                ->whereIn('student_id', $studentIds)
                ->get()
                ->keyBy('student_id');

        $students = $studentIds->isEmpty()
            ? collect()
            : Student::with('user')
                ->whereIn('id', $studentIds)
                ->orderBy(User::select('lastname')->whereColumn('users.id', 'students.user_id'))
                ->orderBy(User::select('name')->whereColumn('users.id', 'students.user_id'))
                ->get();

        return $this->payload($schedule, $date, $observation, $students, $attendances);
    }

    /**
     * Guarda la observación de la clase y los estados de asistencia de la
     * fecha. Los estudiantes sin estado distinto de Presente quedan sin
     * registro activo (Presente implícito; el registro previo se elimina con
     * soft delete para conservar el tombstone que necesita el sync). Todo
     * corre dentro de una transacción serializada por horario, de modo que
     * snapshots concurrentes del mismo día no pueden duplicar filas (H-06).
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function register(Teacher $teacher, array $validated): array
    {
        return DB::transaction(function () use ($teacher, $validated): array {
            $schedule = $this->ownSchedule($teacher, (int) $validated['schedule_id'], lock: true);
            $date = (string) Carbon::parse($validated['date'])->toDateString();

            $studentIds = $this->enrolledStudentIds((int) $schedule->year_id, (int) $schedule->grade_id);
            $this->assertStudentsEnrolled((array) $validated['statuses'], $studentIds);

            $calendarDay = CalendarDay::query()->whereDate('date', $date)->first();

            $classObservation = $validated['observation'] ?? null;

            $observation = ClassObservation::query()
                ->where('class_schedule_id', $schedule->id)
                ->whereDate('observation_date', $date)
                ->first();

            $observationValues = [
                'teacher_id' => $teacher->id,
                'year_id' => $schedule->year_id,
                'classtopic' => $validated['classtopic'],
                'observation' => $classObservation !== null && trim((string) $classObservation) !== ''
                    ? (string) $classObservation
                    : 'Tema de Clase.',
                'class_observation' => $classObservation,
            ];

            if ($observation !== null) {
                $observation->update($observationValues);
            } else {
                try {
                    $observation = ClassObservation::create([
                        'class_schedule_id' => $schedule->id,
                        'observation_date' => $date,
                        ...$observationValues,
                    ]);
                } catch (UniqueConstraintViolationException) {
                    $observation = ClassObservation::query()
                        ->where('class_schedule_id', $schedule->id)
                        ->whereDate('observation_date', $date)
                        ->firstOrFail();
                    $observation->update($observationValues);
                }
            }

            $statuses = (array) $validated['statuses'];
            $observations = (array) ($validated['observations'] ?? []);

            // Una sola lectura de las filas activas del día (en vez de un
            // SELECT por estudiante) y escrituras agrupadas: soft delete por
            // instancia (dispara el observer que publica el tombstone de
            // sync) y un único INSERT para los nuevos estados.
            $existingByStudent = $studentIds->isEmpty()
                ? collect()
                : Attendance::query()
                    ->where('class_schedule_id', $schedule->id)
                    ->whereDate('date', $date)
                    ->whereIn('student_id', $studentIds)
                    ->get()
                    ->keyBy('student_id');

            $toDelete = [];
            $recordedRows = [];
            $now = now();

            foreach ($studentIds as $studentId) {
                $studentId = (int) $studentId;
                $status = trim((string) ($statuses[(string) $studentId] ?? 'P'));
                $attendance = $existingByStudent->get($studentId);

                if ($status === 'P' || $status === '') {
                    if ($attendance !== null) {
                        $toDelete[] = $attendance;
                    }

                    continue;
                }

                $attendanceValues = [
                    'class_observation_id' => $observation->id,
                    'calendarday_id' => $calendarDay?->id,
                    'year_id' => $schedule->year_id,
                    'status' => $status,
                    'observation' => trim((string) ($observations[(string) $studentId] ?? '')) ?: null,
                    'recorded_by' => $teacher->user_id,
                ];

                if ($attendance !== null) {
                    $attendance->update($attendanceValues);

                    continue;
                }

                $recordedRows[] = [
                    'class_schedule_id' => $schedule->id,
                    'student_id' => $studentId,
                    'date' => $date,
                    ...$attendanceValues,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach ($toDelete as $attendance) {
                $attendance->delete();
            }

            if ($recordedRows !== []) {
                try {
                    Attendance::insert($recordedRows);
                } catch (UniqueConstraintViolationException) {
                    // Carrera contra un proceso sin lock (p. ej. canal web):
                    // reintento fila a fila, respetando la fila activa previa.
                    foreach ($recordedRows as $row) {
                        try {
                            Attendance::create($row);
                        } catch (UniqueConstraintViolationException) {
                            Attendance::query()
                                ->where('class_schedule_id', $row['class_schedule_id'])
                                ->where('student_id', $row['student_id'])
                                ->whereDate('date', $row['date'])
                                ->firstOrFail()
                                ->update([
                                    'class_observation_id' => $row['class_observation_id'],
                                    'calendarday_id' => $row['calendarday_id'],
                                    'year_id' => $row['year_id'],
                                    'status' => $row['status'],
                                    'observation' => $row['observation'],
                                    'recorded_by' => $row['recorded_by'],
                                ]);
                        }
                    }
                }
            }

            return $this->detail($teacher, $validated);
        });
    }

    /**
     * @return Collection<int, int>
     */
    private function enrolledStudentIds(int $yearId, int $gradeId): Collection
    {
        return StudentEnrollment::query()
            ->where('year_id', $yearId)
            ->where('grade_id', $gradeId)
            ->pluck('student_id')
            ->unique()
            ->values();
    }

    /**
     * @param  array<string, mixed>  $statuses
     * @param  Collection<int, int>  $studentIds
     */
    private function assertStudentsEnrolled(array $statuses, Collection $studentIds): void
    {
        foreach (array_keys($statuses) as $studentId) {
            if (! $studentIds->contains((int) $studentId)) {
                throw ValidationException::withMessages([
                    'statuses' => 'Uno o más estudiantes no están matriculados en este curso.',
                ]);
            }
        }
    }

    private function ownSchedule(Teacher $teacher, int $scheduleId, bool $lock = false): ClassSchedule
    {
        $query = ClassSchedule::query()->with('subject.area', 'grade.nivel.shift');

        $schedule = $lock ? $query->lockForUpdate()->find($scheduleId) : $query->find($scheduleId);

        if (! $schedule || $schedule->teacher_id !== $teacher->id) {
            throw new NotFoundHttpException('No se encontró el horario de clase para este docente.');
        }

        return $schedule;
    }

    /**
     * @param  Collection<int, Student>  $students
     * @param  Collection<int, Attendance>  $attendances
     * @return array<string, mixed>
     */
    private function payload(
        ClassSchedule $schedule,
        string $date,
        ?ClassObservation $observation,
        Collection $students,
        Collection $attendances,
    ): array {
        $studentRows = $students->map(function (Student $student) use ($attendances): array {
            $attendance = $attendances->get($student->id);

            return [
                'student_id' => $student->id,
                'name' => $student->full_name,
                'code' => $student->student_code,
                'status' => $attendance ? trim((string) $attendance->status) : 'P',
                'observation' => $attendance?->observation,
                'arrival_time' => $attendance?->arrival_time?->format('H:i'),
                'has_record' => $attendance !== null,
            ];
        })->values()->all();

        $subject = $schedule->subject;
        $grade = $schedule->grade;

        return [
            'generated_at' => now()->toISOString(),
            'schedule' => [
                'id' => $schedule->id,
                'schedule_type' => $schedule->schedule_type,
                'day' => $schedule->day,
                'start_time' => $schedule->start_time ? Carbon::parse($schedule->start_time)->format('H:i') : null,
                'end_time' => $schedule->end_time ? Carbon::parse($schedule->end_time)->format('H:i') : null,
                'classroom' => $schedule->classroom,
                'is_active' => (bool) $schedule->is_active,
                'subject' => $subject ? [
                    'id' => $subject->id,
                    'subject_name' => $subject->subject_name,
                    'area_id' => $subject->area_id,
                    'area_name' => $subject->area?->area_name,
                ] : null,
                'grade' => $grade ? [
                    'id' => $grade->id,
                    'grade_name' => $grade->grade_name,
                    'section' => $grade->section,
                    'nivel_name' => $grade->nivel?->nivel_name,
                    'shift_name' => $grade->nivel?->shift?->shift_name,
                ] : null,
            ],
            'date' => $date,
            'observation' => $observation ? [
                'classtopic' => $observation->classtopic,
                'observation' => $observation->observation,
                'class_observation' => $observation->class_observation,
                'novedad' => $observation->novedad,
                'novedad_type' => $observation->novedad_type,
            ] : null,
            'students' => $studentRows,
            'summary' => $this->summary($studentRows),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $students
     * @return array<string, int>
     */
    private function summary(array $students): array
    {
        $total = count($students);
        $recorded = collect($students)->filter(fn (array $student): bool => $student['has_record'])->count();
        $absent = collect($students)->filter(fn (array $student): bool => in_array($student['status'], ['I', 'AI', 'AA'], true))->count();
        $late = collect($students)->filter(fn (array $student): bool => $student['status'] === 'A')->count();

        return [
            'total' => $total,
            'recorded' => $recorded,
            'absent' => $absent,
            'late' => $late,
            'present' => max(0, $total - $absent - $late),
        ];
    }
}
