<?php

declare(strict_types=1);

namespace App\Actions\TeacherManagement;

use App\Models\Setting\YearSettings\CalendarDay;
use App\Models\TeacherManagement\Academics\ClassSchedule;
use App\Models\TeacherManagement\Attendances\Attendance;
use App\Models\TeacherManagement\Attendances\ClassObservation;
use App\Services\Academic\PdfReportCache;
use App\Support\Database\BulkWrite;

final class SaveAttendanceAction
{
    public function __construct(private readonly PdfReportCache $pdfCache) {}

    public function handle(
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

        $observationModel = ClassObservation::query()
            ->where('class_schedule_id', $scheduleId)
            ->whereDate('observation_date', $date)
            ->first() ?? new ClassObservation;

        $observationModel->fill([
            'class_schedule_id' => $scheduleId,
            'observation_date' => $date,
            'teacher_id' => auth()->user()->teacher?->id,
            'year_id' => $yearId,
            'classtopic' => $classtopic,
            'observation' => $observation ?: 'Asistencia tomada desde el horario del docente.',
            'class_observation' => $observation,
            'novedad' => $novedad,
            'novedad_type' => $novedadType,
        ])->save();

        $studentIds = [];

        foreach ($statuses as $studentId => $status) {
            if ($status === 'P' || $status === '') {
                continue;
            }

            $studentIds[(int) $studentId] = trim($status);
        }

        $existing = $studentIds === []
            ? collect()
            : Attendance::query()
                ->where('class_schedule_id', $scheduleId)
                ->whereDate('date', $date)
                ->whereIn('student_id', array_keys($studentIds))
                ->get()
                ->keyBy('student_id');

        $changedMap = [];
        $rows = [];
        $now = now();

        foreach ($studentIds as $studentIdVal => $status) {
            $attendance = $existing->get($studentIdVal);

            $attendanceValues = [
                'class_observation_id' => $observationModel->id,
                'calendarday_id' => $calendarDay?->id,
                'year_id' => $yearId,
                'status' => $status,
                'recorded_by' => $userId,
            ];

            if ($attendance !== null) {
                $differs = self::differs($attendance->status, $status)
                    || self::differs($attendance->class_observation_id, $observationModel->id)
                    || self::differs($attendance->year_id, $yearId);

                if ($differs) {
                    $changedMap[$studentIdVal] = $attendanceValues;
                }

                continue;
            }

            $rows[] = [
                'class_schedule_id' => $scheduleId,
                'student_id' => $studentIdVal,
                'date' => $date,
                ...$attendanceValues,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($changedMap !== []) {
            $statusBy = [];
            $calendarDayIdBy = [];

            foreach ($changedMap as $studentIdVal => $attendanceValues) {
                $statusBy[$studentIdVal] = $attendanceValues['status'];
                $calendarDayIdBy[$studentIdVal] = $attendanceValues['calendarday_id'];
            }

            BulkWrite::caseUpdate(
                Attendance::query()
                    ->where('class_schedule_id', $scheduleId)
                    ->whereDate('date', $date),
                'student_id',
                [
                    'status' => $statusBy,
                    'calendarday_id' => $calendarDayIdBy,
                ],
                [
                    'class_observation_id' => $observationModel->id,
                    'year_id' => $yearId,
                    'recorded_by' => $userId,
                ],
            );
        }

        if ($rows !== []) {
            BulkWrite::insertBatch(Attendance::class, $rows, function (array $row) use ($date, $scheduleId): void {
                Attendance::query()
                    ->where('class_schedule_id', $scheduleId)
                    ->where('student_id', $row['student_id'])
                    ->whereDate('date', $date)
                    ->firstOrFail()
                    ->update([
                        'class_observation_id' => $row['class_observation_id'],
                        'calendarday_id' => $row['calendarday_id'],
                        'year_id' => $row['year_id'],
                        'status' => $row['status'],
                        'recorded_by' => $row['recorded_by'],
                    ]);
            });
        }

        $schedule = ClassSchedule::find($scheduleId);
        if ($schedule !== null) {
            $this->pdfCache->invalidateForSubjectGrade((int) $schedule->subject_id, (int) $schedule->grade_id);
        }
        foreach (array_keys($statuses) as $studentId) {
            $this->pdfCache->invalidateForStudent((int) $studentId);
        }
    }

    /**
     * Comparación tolerante a ids bigint (string en PDO pgsql) y nulos.
     */
    private static function differs(mixed $current, mixed $incoming): bool
    {
        if ($current === null || $incoming === null) {
            return $current !== $incoming;
        }

        return is_numeric($current) && is_numeric($incoming)
            ? (string) $current !== (string) $incoming
            : $current !== $incoming;
    }
}
