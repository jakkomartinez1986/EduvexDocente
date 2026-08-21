<?php

declare(strict_types=1);

namespace App\Services\TeacherManagement;

use App\Models\Identity\Users\Student;
use App\Models\Management\Enrollments\StudentEnrollment;
use App\Models\Setting\YearSettings\CalendarDay;
use App\Models\TeacherManagement\Academics\ClassSchedule;
use App\Models\TeacherManagement\Attendances\Attendance;
use App\Models\TeacherManagement\Attendances\ClassObservation;

final class AttendanceService
{
    public const MODAL_PAGE_SIZE = 8;

    public function loadStudentsForAttendance(int $scheduleId, int $yearId): array
    {
        $schedule = ClassSchedule::with('grade')->findOrFail($scheduleId);

        $students = Student::whereHas('enrollments', function ($q) use ($schedule, $yearId) {
            $q->where('grade_id', $schedule->grade_id)
                ->where('year_id', $yearId);
        })->with('user')->get();

        return $students->map(fn ($s) => [
            'id' => $s->id,
            'name' => $s->user->full_name ?? trim(($s->user->lastname ?? '').' '.($s->user->name ?? '')),
            'code' => $s->student_code,
        ])->toArray();
    }

    public function loadExistingStatuses(int $scheduleId, string $date, array $students): array
    {
        $studentIds = collect($students)->pluck('id')->toArray();

        $existing = Attendance::where('class_schedule_id', $scheduleId)
            ->where('date', $date)
            ->whereIn('student_id', $studentIds)
            ->get()
            ->keyBy('student_id');

        return collect($students)
            ->mapWithKeys(fn ($s) => [$s['id'] => $existing->get($s['id'])?->status ?? 'P'])
            ->toArray();
    }

    public function loadExistingObservation(int $scheduleId, string $date): array
    {
        $existingObs = ClassObservation::where('class_schedule_id', $scheduleId)
            ->where('observation_date', $date)
            ->first();

        return [
            'classtopic' => $existingObs?->classtopic ?? '',
            'observation' => $existingObs?->observation ?? '',
            'novedad' => $existingObs?->novedad ?? '',
            'novedad_type' => $existingObs?->novedad_type ?? '',
        ];
    }

    public function loadStudentsForAttendanceCreate(int $scheduleId, int $yearId): array
    {
        $schedule = ClassSchedule::with('grade')->findOrFail($scheduleId);

        $enrollments = StudentEnrollment::where('grade_id', $schedule->grade_id)
            ->where('year_id', $yearId)
            ->where('status', 'active')
            ->with('student.user')
            ->get();

        return $enrollments->toArray();
    }

    public function loadExistingStatusesCreate(int $scheduleId, string $date, array $enrollments): array
    {
        $studentIds = collect($enrollments)->pluck('student_id')->toArray();

        $existing = Attendance::where('class_schedule_id', $scheduleId)
            ->where('date', $date)
            ->whereIn('student_id', $studentIds)
            ->get()
            ->keyBy('student_id');

        $statuses = [];
        $times = [];
        $observations = [];

        foreach ($enrollments as $enrollment) {
            $sid = $enrollment['student_id'];
            $record = $existing->get($sid);
            $statuses[$sid] = $record?->status ?? 'P';
            $times[$sid] = $record?->arrival_time?->format('H:i') ?? date('H:i');
            $observations[$sid] = $record?->observation ?? '';
        }

        return [
            'statuses' => $statuses,
            'times' => $times,
            'observations' => $observations,
        ];
    }

    public function getPaginatedStudents(array $students, array $statuses, int $page): array
    {
        $start = $page * self::MODAL_PAGE_SIZE;
        $sliced = array_slice($students, $start, self::MODAL_PAGE_SIZE);

        return array_map(fn ($s) => [
            'id' => $s['id'],
            'name' => $s['name'],
            'code' => $s['code'],
            'status' => $statuses[$s['id']] ?? 'P',
        ], $sliced);
    }

    public function getTotalPages(int $totalStudents): int
    {
        return max(1, (int) ceil($totalStudents / self::MODAL_PAGE_SIZE));
    }

    public function saveAttendance(
        int $scheduleId,
        string $date,
        int $yearId,
        int $userId,
        array $statuses,
        ?string $classtopic = null,
        ?string $observation = null,
        ?string $novedad = null,
        ?string $novedadType = null,
    ): void {
        $calendarDay = CalendarDay::where('date', $date)->first();

        $observationModel = ClassObservation::updateOrCreate(
            [
                'class_schedule_id' => $scheduleId,
                'observation_date' => $date,
            ],
            [
                'teacher_id' => auth()->user()->teacher?->id,
                'year_id' => $yearId,
                'classtopic' => $classtopic,
                'observation' => $observation ?: 'Asistencia tomada desde el horario del docente.',
                'class_observation' => $observation,
                'novedad' => $novedad,
                'novedad_type' => $novedadType,
            ]
        );

        foreach ($statuses as $studentId => $status) {
            if ($status === 'P' || $status === '') {
                continue;
            }

            Attendance::updateOrCreate(
                [
                    'class_schedule_id' => $scheduleId,
                    'student_id' => $studentId,
                    'date' => $date,
                ],
                [
                    'class_observation_id' => $observationModel->id,
                    'calendarday_id' => $calendarDay?->id,
                    'year_id' => $yearId,
                    'status' => trim($status),
                    'recorded_by' => $userId,
                ]
            );
        }
    }

    public function saveAttendanceCreate(
        int $scheduleId,
        string $date,
        int $yearId,
        int $userId,
        array $statuses,
        array $observations = [],
    ): void {
        $calendarDay = CalendarDay::where('date', $date)->first();

        foreach ($statuses as $studentId => $status) {
            if ($status === 'P' || $status === '') {
                continue;
            }

            Attendance::updateOrCreate(
                [
                    'class_schedule_id' => $scheduleId,
                    'student_id' => $studentId,
                    'date' => $date,
                ],
                [
                    'calendarday_id' => $calendarDay?->id,
                    'status' => trim($status),
                    'observation' => $observations[$studentId] ?? null,
                    'recorded_by' => $userId,
                    'year_id' => $yearId,
                ]
            );
        }
    }

    public function markAllPresent(array $students): array
    {
        return collect($students)
            ->mapWithKeys(fn ($s) => [$s['id'] => 'P'])
            ->toArray();
    }
}
